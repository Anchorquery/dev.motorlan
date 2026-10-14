<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { VForm } from 'vuetify/components'

// Components
import StepBasicInfo from '@/pages/dashboard/publications/components/StepBasicInfo.vue'
import StepTechSpecs from '@/pages/dashboard/publications/components/StepTechSpecs.vue'
import StepMedia from '@/pages/dashboard/publications/components/StepMedia.vue'
import StepLocationCondition from '@/pages/dashboard/publications/components/StepLocationCondition.vue'
import StepDocumentation from '@/pages/dashboard/publications/components/StepDocumentation.vue'
import { useMotorFormatter } from '@/composables/useMotorFormatter'
import { storeUrl } from '@/utils/urls'

// Composables
import {
  compressImageFile,
  exceedsTotalUploadSize,
  findOversizedFile,
  formatBytes,
  getUploadLimits,
  revokePublicationBlobUrls,
  usePublicationForm,
} from '@/composables/usePublicationForm'
import { assertApiOk, useApi } from '@/composables/useApi'
import { useToast } from '@/composables/useToast'
import { requiredValidator } from '@/@core/utils/validators'
import { useUserStore } from '@/@core/stores/user'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const { showToast } = useToast()
const { formState, setFormState, isMotor } = usePublicationForm()
const userStore = useUserStore()
const { formatMotorName, getFormattedPreview } = useMotorFormatter()

const motorUuid = route.params.uuid as string
const formRef = ref<VForm | null>(null)
const isFormValid = ref(false)
const isLoading = ref(false)
const activeTab = ref(0)
const postId = ref<number | null>(null)

// Snapshot de los campos que forman el slug/URL (mismo criterio que el backend:
// marca, referencia, potencia, par nominal y tipo).
const slugSnapshot = () => JSON.stringify([
    formState.value.acf.marca ?? null,
    formState.value.acf.marca_custom || '',
    String(formState.value.acf.tipo_o_referencia ?? '').trim(),
    String(formState.value.acf.potencia ?? ''),
    String(formState.value.acf.par_nominal ?? ''),
    [...(formState.value.tipo || [])],
])
const originalSlugFields = ref('')

const slugFieldsChanged = computed(() => !!originalSlugFields.value && slugSnapshot() !== originalSlugFields.value)

// Data for selects
// Data for selects
const marcas = ref<{ title: string; value: number }[]>([])
const tipos = ref<{ title: string; value: number; slug: string }[]>([])
const countries = ref<{ title: string; value: string }[]>([])

// Load Data
onMounted(async () => {
  try {
    const marcasFetch = useApi('/wp-json/motorlan/v1/marcas', { immediate: false }).get().json()
    const tiposFetch = useApi('/wp-json/motorlan/v1/tipos', { immediate: false }).get().json()
    const countriesFetch = useApi('/wp-json/motorlan/v1/countries', { immediate: false }).get().json()

    await Promise.all([
       marcasFetch.execute(),
       tiposFetch.execute(),
       countriesFetch.execute(),
    ])

    // Con updateDataOnError, data puede ser el objeto de error: comprobar que sea un array.
    if (Array.isArray(marcasFetch.data.value)) marcas.value = marcasFetch.data.value.map((m: any) => ({ title: m.name, value: m.term_id }))
    if (Array.isArray(tiposFetch.data.value)) tipos.value = tiposFetch.data.value.map((t: any) => ({ title: t.name, value: t.term_id, slug: t.slug }))
    if (Array.isArray(countriesFetch.data.value)) countries.value = countriesFetch.data.value
    
    if (motorUuid) {
        await fetchPublication(motorUuid)
    }

  } catch (e) {
      console.error(e)
      showToast(t('edit_publication.fetch_error', 'Error al cargar datos'), 'error')
  }
})

const fetchPublication = async (uuid: string) => {
    try {
        // assertApiOk: con 403/404 antes se construía el formulario sobre el objeto de error
        // y quedaba editable con valores por defecto.
        const { data } = assertApiOk(await useApi(`/wp-json/motorlan/v1/publicaciones/uuid/${uuid}`).get().json())
        const post = data.value

        if (!post || !post.id) {
             showToast(t('edit_publication.not_found', 'Publicación no encontrada'), 'error')
             router.push('/dashboard/publications/publication/list')
             return
        }
        
        postId.value = post.id
        
        // Map API data to formState
        const mappedState = {
            id: post.id,
            title: post.title,
            slug: post.slug,
            status: post.status,
            tipo: post.tipo ? post.tipo.map((t: any) => t.id) : [],
            author: post.author,
            acf: {
                ...formState.value.acf, // Default values
                ...post.acf // Overwrite
            }
        }
        
        // Normalizations
        if (mappedState.acf.marca && typeof mappedState.acf.marca === 'object' && mappedState.acf.marca.id) {
            mappedState.acf.marca = Number(mappedState.acf.marca.id)
        } else if (mappedState.acf.marca) {
            mappedState.acf.marca = Number(mappedState.acf.marca)
        }
        
        // Handle pending custom brand
        if (post.pending_brand) {
            mappedState.acf.marca = -1
            mappedState.acf.marca_custom = post.pending_brand
        }

        // Mapping Images properly for DropZone
        if (post.acf?.motor_image) {
             mappedState.acf.motor_image = {
                 url: post.acf.motor_image.url,
                 id: post.acf.motor_image.id
             }
        }
        
        if (Array.isArray(post.acf?.motor_gallery)) {
            mappedState.acf.motor_gallery = post.acf.motor_gallery.map((img: any) => ({
                url: img.url,
                id: img.id
            }))
        }
        
         if (mappedState.acf.stock === null || mappedState.acf.stock === undefined) mappedState.acf.stock = 1
        if (!mappedState.acf.documentacion_adicional) mappedState.acf.documentacion_adicional = []
        
        
        // Load legacy mappings (country, condition) if needed - logic copied from original file
        const countryMap: { [key: string]: string } = { 'España': 'es', 'spain': 'es', 'Spain': 'es', 'Portugal': 'pt', 'portugal': 'pt', 'Francia': 'fr', 'France': 'fr', 'france': 'fr' }
        const conditionMap: { [key: string]: string } = { 'Nuevo': 'new', 'Usado': 'used', 'Restaurado': 'restored' }
        
        if (typeof mappedState.acf.pais === 'string' && countryMap[mappedState.acf.pais]) mappedState.acf.pais = countryMap[mappedState.acf.pais]
        if (typeof mappedState.acf.estado_del_articulo === 'string' && conditionMap[mappedState.acf.estado_del_articulo]) mappedState.acf.estado_del_articulo = conditionMap[mappedState.acf.estado_del_articulo]

        setFormState(mappedState)

        // Guardar snapshot de campos que afectan slug
        originalSlugFields.value = slugSnapshot()

    } catch (e: any) {
        console.error(e)
        showToast(e?.message || t('edit_publication.fetch_error', 'Error al cargar publicación'), 'error')
        router.push('/dashboard/publications/publication/list')
    }
}

// "Actualizar" conserva pausada/vendida (antes siempre enviaba 'publish': reactivaba lo
// vendido o lo mandaba a revisión al corregir una errata).
const updateTargetStatus = computed(() => ['paused', 'sold'].includes(formState.value.status) ? formState.value.status : 'publish')

const updatePublication = async (status: string) => {
    // Guarda de reentrada (doble clic / Enter)
    if (isLoading.value) return

    const { valid } = await formRef.value?.validate() ?? { valid: false }
    if (!valid) {
        showToast(t('edit_publication.required_fields_error', 'Por favor, rellena los campos obligatorios'), 'error')
        return
    }

    isLoading.value = true
    
    try {
        const formData = new FormData()

        // Append basic fields. El slug lo genera el backend (mismo formato que en el alta)
        // y solo cambia si cambian marca/referencia/potencia/par/tipo.
        formData.append('title', formState.value.title)
        formData.append('status', status)
        
        // Taxonomies
        formData.append('tipo', JSON.stringify(formState.value.tipo))
        
        // ACF - separate files
        const { motor_image, motor_gallery, documentacion_adicional, marca_custom, ...acfRest } = formState.value.acf
        // Si es marca personalizada (Otros), enviar el nombre en vez del ID
        if (acfRest.marca === -1 && marca_custom) {
          acfRest.marca = null
          formData.append('marca_custom', marca_custom)
        }
        formData.append('acf', JSON.stringify(acfRest))
        
        // Archivos nuevos: optimizar fotos y comprobar límites del servidor antes de enviar
        const newMainFile = motor_image?.file ? await compressImageFile(motor_image.file) : null
        const newGalleryFiles = await Promise.all(
            (motor_gallery || []).filter(img => img.file).map(img => compressImageFile(img.file as File)),
        )
        const newDocFiles: File[] = []
        ;(documentacion_adicional || []).forEach(doc => {
            if (doc.archivo instanceof File) newDocFiles.push(doc.archivo)
            else if (Array.isArray(doc.archivo) && doc.archivo[0] instanceof File) newDocFiles.push(doc.archivo[0])
        })
        const allNewFiles = [newMainFile, ...newGalleryFiles, ...newDocFiles].filter(Boolean) as File[]
        const tooBig = findOversizedFile(allNewFiles)
        if (tooBig) {
            showToast(t('add_publication.validation.file_too_big', { name: tooBig.name, max: formatBytes(getUploadLimits().maxFile) }, `"${tooBig.name}" supera el tamaño máximo permitido (${formatBytes(getUploadLimits().maxFile)}).`), 'error')
            return
        }
        if (exceedsTotalUploadSize(allNewFiles)) {
            showToast(t('add_publication.validation.total_too_big', { max: formatBytes(getUploadLimits().maxPost) }, `Los archivos superan en total el máximo permitido (${formatBytes(getUploadLimits().maxPost)}). Quita alguna imagen o documento.`), 'error')
            return
        }

        // Main Image logic
        if (motor_image) {
            if (newMainFile) {
                 formData.append('motor_image', newMainFile)
            } else if (motor_image.id) {
                 formData.append('motor_image_id', motor_image.id.toString())
            }
        } else {
             formData.append('motor_image_id', '') // Delete image if null
        }
        
        // Gallery logic
        const existingGalleryIds: number[] = []
        if (motor_gallery && motor_gallery.length) {
            motor_gallery.forEach(img => {
                if (!img.file && img.id)
                    existingGalleryIds.push(img.id)
            })
        }
        newGalleryFiles.forEach(file => formData.append('motor_gallery[]', file))
        formData.append('motor_gallery_ids', existingGalleryIds.join(','))
        
        // Docs logic
        const existingDocsLikes: any[] = []
        const newDocNames: any[] = []
        
        if (documentacion_adicional && documentacion_adicional.length) {
            documentacion_adicional.forEach(doc => {
                 let fileToSend: File | null = null
                 
                 // Handle various structures that dropzone or file input might give
                 if (doc.archivo instanceof File) fileToSend = doc.archivo
                 else if (Array.isArray(doc.archivo) && doc.archivo[0] instanceof File) fileToSend = doc.archivo[0]
                 
                 if (fileToSend) {
                     formData.append('documentacion_adicional_archivos[]', fileToSend)
                     newDocNames.push({ nombre: doc.nombre })
                 } else if (doc.archivo && !Array.isArray(doc.archivo) && 'id' in doc.archivo && doc.archivo.id) {
                     existingDocsLikes.push({ nombre: doc.nombre, archivo: doc.archivo.id })
                 } else if (doc.archivo && 'url' in doc.archivo && doc.archivo.url) {
                      // Keep existing by ID if possible, sometimes file handling is tricky with just URL
                       if ('id' in doc.archivo && doc.archivo.id) {
                           existingDocsLikes.push({ nombre: doc.nombre, archivo: doc.archivo.id })
                       }
                 }
            })
        }
        
        formData.append('documentacion_adicional_ids', JSON.stringify(existingDocsLikes))
        formData.append('documentacion_adicional_nombres', JSON.stringify(newDocNames))
        
        // API Call
        const { data: responseData } = assertApiOk(await useApi(`/wp-json/motorlan/v1/publicaciones/uuid/${motorUuid}`, {
            method: 'POST',
            body: formData
        }).json())

        // Reflejar el estado guardado (p. ej. publish -> pending para no-admin)
        if (responseData.value?.status)
            formState.value.status = responseData.value.status

        // Actualizar slug con el valor real del backend
        if (responseData.value?.slug) {
            formState.value.slug = responseData.value.slug
        }

        // Actualizar snapshot para que el aviso de URL desaparezca
        originalSlugFields.value = slugSnapshot()

        const uploadErrors = responseData.value?.upload_errors
        if (Array.isArray(uploadErrors) && uploadErrors.length)
            showToast(t('edit_publication.partial_upload', { files: uploadErrors.join(' · ') }, 'Publicación actualizada, pero algunos archivos no se subieron: {files}'), 'warning')
        else if (responseData.value?.status === 'pending' && status !== 'pending')
            showToast(t('edit_publication.sent_to_review', 'Cambios guardados. La publicación queda en revisión hasta que un administrador la apruebe.'), 'info')
        else
            showToast(t('edit_publication.update_success', 'Publicación actualizada'), 'success')
        
    } catch (e: any) {
        console.error(e)
        showToast(t('edit_publication.update_error', { message: e?.message || '' }), 'error')
    } finally {
        isLoading.value = false
    }
}

onBeforeUnmount(() => {
    // Liberar las previsualizaciones blob: de las imágenes nuevas
    revokePublicationBlobUrls(formState.value.acf)
})


// Page Title
const pageTitle = computed(() => {
    let title = t('edit_publication.title', 'Editar Publicación')
    if (formState.value.tipo && tipos.value.length) {
        const type = tipos.value.find(t => t.value === formState.value.tipo[0])
        if (type) {
            let typeTitle = type.title
            if (type.slug === 'motor') {
                const supplyType = formState.value.acf.tipo_de_alimentacion
                typeTitle = supplyType === 'dc' ? t('select_publication_type.motor_dc_title') : t('select_publication_type.motor_ac_title')
            }
            title += `: ${typeTitle}`
        }
    }
    return title
})

const getMainImageUrl = () => {
    const img = formState.value.acf.motor_image
    // DropZone ya crea la URL blob: crear otra en cada render filtraba memoria
    return img?.url || ''
}

const isReadOnly = computed(() => {
    // Si es admin, nunca es read-only por estado (siempre puede editar)
    if (userStore.isAdmin) return false;
    
    // Si no es admin y el estado es 'pending' (En revisión), es read-only
    return formState.value.status === 'pending';
})

const statusLabel = computed(() => {
    const statusKey = formState.value.status || 'unknown';
    // Mapeo seguro usando las claves existentes en publication_list.status_options
    const options: Record<string, string> = {
        'publish': t('publication_list.status_options.published', 'Publicado'),
        'draft': t('publication_list.status_options.draft', 'Borrador'),
        'pending': t('publication_list.status_options.pending', 'En revisión'),
        'paused': t('publication_list.status_options.paused', 'Pausado'),
        'sold': t('publication_list.status_options.sold', 'Vendido'),
        'trash': t('publication_list.status_options.trash', 'Papelera'),
    };
    
    return options[statusKey] || t('publication_list.status_options.unknown', 'Desconocido');
})

const statusColor = computed(() => {
    switch (formState.value.status) {
        case 'publish': return 'success';
        case 'pending': return 'warning'; // En revisión - Naranja
        case 'draft': return 'secondary'; // Borrador - Gris
        case 'paused': return 'info';
        case 'sold': return 'error';
        case 'trash': return 'error';
        default: return 'default';
    }
})
</script>

<template>
  <div class="edit-publication-wrapper">
    <VAlert
        v-if="isReadOnly"
        type="warning"
        variant="tonal"
        class="mb-6"
    >
        {{ t('edit_publication.pending_review_warning') }}
    </VAlert>

    <VForm ref="formRef" v-model="isFormValid" @submit.prevent :disabled="isReadOnly">
        
        <!-- Header -->
        <div class="edit-publication-header mb-6">
          <!-- Title row -->
          <div class="edit-publication-header__title-row">
            <span class="text-h5 font-weight-bold text-premium-title edit-publication-header__title-text">{{ pageTitle }}</span>
            <span class="text-caption text-medium-emphasis edit-publication-header__note">
              <i18n-t keypath="publication_edit_form.required_fields_note"><template #asterisk><span class="text-error">*</span></template></i18n-t>
            </span>
          </div>
          <!-- Actions row -->
          <div class="edit-publication-header__actions">
            <VBtn
              variant="tonal"
              color="secondary"
              size="small"
              @click="router.push('/dashboard/publications/publication/list')"
            >
              {{ t('edit_publication.discard', 'Descartar') }}
            </VBtn>
            <VBtn
              variant="tonal"
              color="secondary"
              size="small"
              :disabled="isReadOnly || isLoading"
              @click="updatePublication('draft')"
            >
              {{ t('edit_publication.save_draft', 'Guardar Borrador') }}
            </VBtn>
            <VBtn
              color="primary"
              size="small"
              :loading="isLoading"
              :disabled="isReadOnly || isLoading"
              @click="updatePublication(updateTargetStatus)"
            >
              {{ t('edit_publication.update_publication', 'Actualizar') }}
            </VBtn>
          </div>
        </div>
        
        <VRow class="mx-0 mx-md-n3">
            <!-- Left Column: Main Content -->
             <VCol cols="12" md="8" class="px-0 px-md-3">
                 <!-- Tabs for better organization in Edit mode -->
                <!-- Mobile-only compact status strip -->
                <div class="edit-pub-mobile-strip d-flex d-md-none align-center gap-3 mb-4 flex-wrap">
                  <VChip size="small" :color="statusColor" label>{{ statusLabel }}</VChip>
                  <span v-if="formState.acf.precio_de_venta" class="text-body-2 font-weight-bold text-primary">
                    {{ formState.acf.precio_de_venta }}€
                  </span>
                  <VChip v-if="formState.acf.stock !== undefined" size="small" :color="formState.acf.stock > 0 ? 'success' : 'error'" variant="tonal">
                    {{ formState.acf.stock }} u.
                  </VChip>
                </div>

                <VCard class="mb-6">
                    <VTabs
                      v-model="activeTab"
                      grow
                      color="primary"
                      class="edit-publication-tabs"
                    >
                        <VTab :value="0"><VIcon start icon="tabler-info-circle"/><span class="tab-lbl"> {{ t('edit_publication.tabs.basic', 'Básico') }}</span></VTab>
                        <VTab :value="1"><VIcon start icon="tabler-list-details"/><span class="tab-lbl"> {{ t('edit_publication.tabs.specs', 'Espec.') }}</span></VTab>
                        <VTab :value="2"><VIcon start icon="tabler-photo"/><span class="tab-lbl"> {{ t('edit_publication.tabs.media', 'Fotos') }}</span></VTab>
                        <VTab :value="3"><VIcon start icon="tabler-file"/><span class="tab-lbl"> {{ t('edit_publication.tabs.docs', 'Docs') }}</span></VTab>
                    </VTabs>

                    <VCardText class="pa-0 pa-md-6 edit-pub-card-body">
                         <VWindow v-model="activeTab">
                             <!-- Basic Info -->
                            <VWindowItem eager :value="0">
                                <StepBasicInfo
                                    :form-state="formState"
                                    :marcas="marcas"
                                    :tipos="tipos"
                                    @update:form-state="setFormState"
                                />
                                <div class="mt-6">
                                     <StepLocationCondition
                                        :form-state="formState"
                                        :country-options="countries"
                                        @update:form-state="setFormState"
                                    />
                                </div>
                            </VWindowItem>
                            
                            <!-- Tech Specs -->
                             <VWindowItem eager :value="1">
                                <StepTechSpecs
                                     :form-state="formState"
                                     :tipos="tipos"
                                     :show-type-selector="true"
                                     @update:form-state="setFormState"
                                />
                             </VWindowItem>
                             
                             <!-- Media -->
                             <VWindowItem eager :value="2">
                                  <StepMedia
                                    :form-state="formState"
                                    @update:form-state="setFormState"
                                />
                             </VWindowItem>
                             
                             <!-- Docs -->
                             <VWindowItem eager :value="3">
                                 <StepDocumentation
                                    :form-state="formState"
                                    @update:form-state="setFormState"
                                />
                             </VWindowItem>
                         </VWindow>
                    </VCardText>
                </VCard>
                
                 <!-- Separate Description Card always visible? Or put in Basic? -->
                 <!-- Let's put description in a separate card below tabs if it's very important, or inside Basic Info tab. 
                      User requested "improve view", tabs are usually cleaner for large forms.
                  -->
             </VCol>
             
             <!-- Right Column: Status/Quick Actions — hidden on mobile (compact strip shown instead) -->
             <VCol cols="12" md="4" class="px-0 px-md-3 d-none d-md-flex flex-column">
                  <!-- Maybe some analytics or quick status info here -->
                  <!-- Publication Summary Sidebar -->
      <VCard class="mb-6 edit-publication-sidebar">
                       <VCardItem class="pb-4">
                           <template #prepend>
                               <div class="d-flex align-center justify-center rounded bg-light-primary" style="width: 80px; height: 80px; overflow: hidden;">
                                    <VImg 
                                        v-if="getMainImageUrl()" 
                                        :src="getMainImageUrl()" 
                                        cover
                                    />
                                    <VIcon v-else icon="tabler-photo" size="40" color="primary" />
                               </div>
                           </template>
                           <VCardTitle class="text-h6 font-weight-bold mb-1" style="line-height: 1.4; white-space: normal;">
                               {{ getFormattedPreview(formState, marcas, tipos) || formState.title || t('edit_publication.no_title', 'Sin título') }}
                           </VCardTitle>
                           <VCardSubtitle class="text-body-2">
                               ID: #{{ postId }}
                           </VCardSubtitle>
                       </VCardItem>
                        
                       <VDivider />

                       <VCardText class="py-4">
                           <div class="edit-publication-sidebar__row d-flex justify-space-between align-center mb-3">
                               <span class="text-body-2 text-medium-emphasis">{{ t('add_publication.post_details.price') }}:</span>
                               <span v-if="formState.acf.precio_negociable === 'yes' && formState.acf.precio_de_venta" class="text-h6 font-weight-bold text-primary">
                                   {{ formState.acf.precio_de_venta }}€
                                   <VChip size="x-small" color="info" variant="tonal" class="ml-1">{{ t('add_publication.reference_chip') }}</VChip>
                               </span>
                               <span v-else-if="formState.acf.precio_negociable === 'yes'" class="text-h6 font-weight-bold text-warning">
                                   {{ t('publication_list_table.consult_price', 'Consultar precio') }}
                               </span>
                               <span v-else-if="formState.acf.precio_de_venta" class="text-h6 font-weight-bold text-primary">
                                   {{ formState.acf.precio_de_venta }}€
                               </span>
                               <span v-else class="text-h6 font-weight-bold text-primary">
                                   {{ t('add_publication.post_details.no_price', 'Consultar') }}
                               </span>
                           </div>
                           <div class="edit-publication-sidebar__row d-flex justify-space-between align-center mb-3">
                               <span class="text-body-2 text-medium-emphasis">{{ t('add_publication.post_details.stock') }}:</span>
                               <VChip size="small" :color="formState.acf.stock > 0 ? 'success' : 'error'" variant="tonal">
                                   {{ formState.acf.stock }} u.
                               </VChip>
                           </div>
                           <div class="edit-publication-sidebar__row d-flex justify-space-between align-center">
                               <span class="text-body-2 text-medium-emphasis">{{ t('edit_publication.status_title') }}:</span>
                               <VChip 
                                :color="statusColor" 
                                size="small" 
                                label
                               >
                                   {{ statusLabel }}
                               </VChip>
                           </div>
                       </VCardText>
                       
                       <VDivider />
                       
                       <VCardActions class="pa-4 flex-column ga-2">
                            <VAlert
                                v-if="slugFieldsChanged"
                                type="info"
                                density="compact"
                                variant="tonal"
                                class="w-100 mb-1"
                            >
                                {{ t('edit_publication.url_will_change', 'La URL se actualizará al guardar') }}
                            </VAlert>
                            <VBtn
                                block
                                variant="outlined"
                                :href="storeUrl(formState.slug || '')"
                                target="_blank"
                                prepend-icon="tabler-external-link"
                                :disabled="!formState.slug || formState.status !== 'publish'"
                            >
                                {{ t('edit_publication.view_on_web', 'Ver en la web') }}
                            </VBtn>
                       </VCardActions>
                  </VCard>
             </VCol>
        </VRow>

    </VForm>
  </div>

</template>

<style scoped>
/* ─── Wrapper ─────────────────────────────────────────────────── */
.edit-publication-wrapper {
  max-width: 100%;
  overflow-x: hidden;
}

/* ─── Header ──────────────────────────────────────────────────── */
.edit-publication-header {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.75rem;
}

.edit-publication-header__title-row {
  flex: 1 1 100%;
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 0.5rem;
}

.edit-publication-header__title-text {
  flex: 1 1 auto;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.edit-publication-header__actions {
  flex: 1 1 100%;
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.edit-publication-header__actions :deep(.v-btn) {
  flex: 1 1 auto;
}

/* ─── Card body: clip inner VRow negative-margin overflow ─────── */
.edit-pub-card-body {
  overflow-x: hidden;
}

/* ─── Tabs ────────────────────────────────────────────────────── */
.edit-publication-tabs {
  overflow-x: auto;
}

/* ─── Sidebar (desktop only) ──────────────────────────────────── */
.edit-publication-sidebar {
  position: sticky;
  top: 1.5rem;
}

.edit-publication-sidebar__row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

/* ─── Desktop overrides ───────────────────────────────────────── */
@media (min-width: 960px) {
  .edit-publication-header {
    flex-wrap: nowrap;
    align-items: flex-start;
  }

  .edit-publication-header__title-row {
    flex: 1 1 auto;
  }

  .edit-publication-header__actions {
    flex: 0 0 auto;
    flex-wrap: nowrap;
  }

  .edit-publication-header__actions :deep(.v-btn) {
    flex: 0 0 auto;
    white-space: nowrap;
  }
}

/* ─── Mobile ──────────────────────────────────────────────────── */
@media (max-width: 959px) {
  /* Space for fixed mobile nav */
  .edit-publication-wrapper {
    padding-bottom: calc(58px + env(safe-area-inset-bottom, 12px) + 1rem);
  }

  /* Hide less critical header note */
  .edit-publication-header__note {
    display: none;
  }

  /* Tab labels: hide on mobile so tabs are icon-only */
  .tab-lbl {
    display: none;
  }

  /* Kill VRow negative margins — VCol 12px padding provides field spacing */
  .edit-pub-card-body :deep(.v-row) {
    margin-left: 0 !important;
    margin-right: 0 !important;
  }

  /* Make action buttons equal width on mobile */
  .edit-publication-header__actions :deep(.v-btn) {
    min-width: 0;
    font-size: 0.8rem;
    padding-inline: 10px;
  }
}

@media (max-width: 480px) {
  .edit-publication-header__actions :deep(.v-btn) {
    font-size: 0.75rem;
    padding-inline: 8px;
  }
}
</style>
