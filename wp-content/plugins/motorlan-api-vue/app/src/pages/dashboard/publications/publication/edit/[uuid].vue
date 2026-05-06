<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { VForm } from 'vuetify/components'

// Components
import StepBasicInfo from '@/pages/dashboard/publications/components/StepBasicInfo.vue'
import StepTechSpecs from '@/pages/dashboard/publications/components/StepTechSpecs.vue'
import StepMedia from '@/pages/dashboard/publications/components/StepMedia.vue'
import StepLocationCondition from '@/pages/dashboard/publications/components/StepLocationCondition.vue'
import StepDocumentation from '@/pages/dashboard/publications/components/StepDocumentation.vue'

// Composables
import { usePublicationForm } from '@/composables/usePublicationForm'
import { useApi } from '@/composables/useApi'
import { useToast } from '@/composables/useToast'
import { requiredValidator } from '@/@core/utils/validators'
import { useUserStore } from '@/@core/stores/user'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const { showToast } = useToast()
const { formState, setFormState, isMotor } = usePublicationForm()
const userStore = useUserStore()

const motorUuid = route.params.uuid as string
const formRef = ref<VForm | null>(null)
const isFormValid = ref(false)
const isLoading = ref(false)
const activeTab = ref(0)
const postId = ref<number | null>(null)

// Data for selects
// Data for selects
const marcas = ref<{ title: string; value: number }[]>([])
const categories = ref<{ title: string; value: number }[]>([])
const tipos = ref<{ title: string; value: number; slug: string }[]>([])

// Load Data
onMounted(async () => {
  try {
    const marcasFetch = useApi('/wp-json/motorlan/v1/marcas', { immediate: false }).get().json()
    const categoriesFetch = useApi('/wp-json/motorlan/v1/publicacion-categories', { immediate: false }).get().json()
    const tiposFetch = useApi('/wp-json/motorlan/v1/tipos', { immediate: false }).get().json()

    await Promise.all([
       marcasFetch.execute(),
       categoriesFetch.execute(),
       tiposFetch.execute(),
    ])

    if (marcasFetch.data.value) marcas.value = marcasFetch.data.value.map((m: any) => ({ title: m.name, value: m.term_id }))
    if (categoriesFetch.data.value) categories.value = categoriesFetch.data.value.map((c: any) => ({ title: c.name, value: c.term_id }))
    if (tiposFetch.data.value) tipos.value = tiposFetch.data.value.map((t: any) => ({ title: t.name, value: t.term_id, slug: t.slug }))
    
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
        const { data } = await useApi(`/wp-json/motorlan/v1/publicaciones/uuid/${uuid}`).get().json()
        const post = data.value
        
        if (!post) {
             showToast(t('edit_publication.not_found', 'Publicación no encontrada'), 'error')
             return
        }
        
        postId.value = post.id
        
        // Map API data to formState
        const mappedState = {
            id: post.id,
            title: post.title,
            status: post.status,
            categories: post.categories ? post.categories.map((c: any) => c.id) : [],
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
        
        // Mapping Images properly for DropZone
        if (post.acf.motor_image) {
             mappedState.acf.motor_image = {
                 url: post.acf.motor_image.url,
                 id: post.acf.motor_image.id
             }
        }
        
        if (post.acf.motor_gallery) {
            mappedState.acf.motor_gallery = post.acf.motor_gallery.map((img: any) => ({
                url: img.url,
                id: img.id
            }))
        }
        
         if (mappedState.acf.stock === null || mappedState.acf.stock === undefined) mappedState.acf.stock = 1
        if (!mappedState.acf.documentacion_adicional) mappedState.acf.documentacion_adicional = []
        
        
        // Load legacy mappings (country, condition) if needed - logic copied from original file
        const countryMap: { [key: string]: string } = { 'España': 'spain', 'España': 'spain', 'Spain': 'spain', 'Portugal': 'portugal', 'Francia': 'france', 'France': 'france' }
        const conditionMap: { [key: string]: string } = { 'Nuevo': 'new', 'Usado': 'used', 'Restaurado': 'restored' }
        
        if (typeof mappedState.acf.pais === 'string' && countryMap[mappedState.acf.pais]) mappedState.acf.pais = countryMap[mappedState.acf.pais]
        if (typeof mappedState.acf.estado_del_articulo === 'string' && conditionMap[mappedState.acf.estado_del_articulo]) mappedState.acf.estado_del_articulo = conditionMap[mappedState.acf.estado_del_articulo]

        setFormState(mappedState)
        
    } catch (e) {
        console.error(e)
        showToast(t('edit_publication.fetch_error', 'Error al cargar publicación'), 'error')
    }
}

const updatePublication = async (status: string) => {
    const { valid } = await formRef.value?.validate() ?? { valid: false }
    if (!valid) {
        showToast(t('edit_publication.required_fields_error', 'Por favor, rellena los campos obligatorios'), 'error')
        return
    }

    isLoading.value = true
    
    try {
        const formData = new FormData()
        
         // Build slug
        const slug = buildPublicationSlug()
        
        // Append basic fields
        formData.append('title', formState.value.title)
        formData.append('status', status)
        if (slug) formData.append('slug', slug)
        
        // Taxonomies
        formData.append('categories', JSON.stringify(formState.value.categories))
        formData.append('tipo', JSON.stringify(formState.value.tipo))
        
        // ACF - separate files
        const { motor_image, motor_gallery, documentacion_adicional, ...acfRest } = formState.value.acf
        formData.append('acf', JSON.stringify(acfRest))
        
        // Main Image logic
        if (motor_image) {
            if (motor_image.file) {
                 formData.append('motor_image', motor_image.file)
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
                if (img.file) {
                    formData.append('motor_gallery[]', img.file)
                } else if (img.id) {
                    existingGalleryIds.push(img.id)
                }
            })
        }
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
        await useApi(`/wp-json/motorlan/v1/publicaciones/uuid/${motorUuid}`, {
            method: 'POST',
            body: formData
        })
        
        showToast(t('edit_publication.update_success', 'Publicación actualizada'), 'success')
        
    } catch (e: any) {
        console.error(e)
        showToast(t('edit_publication.update_error', e.message), 'error')
    } finally {
        isLoading.value = false
    }
}

const buildPublicationSlug = () => {
    // Same slug logic as Add
      const slugify = (val: string) => val.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '')
      
      const parts = [
        formState.value.title,
        formState.value.acf.tipo_o_referencia,
        formState.value.acf.potencia ? `${formState.value.acf.potencia} kW` : null,
        formState.value.acf.velocidad ? `${formState.value.acf.velocidad} rpm` : null,
      ].filter(Boolean) as string[]

      return parts.length ? slugify(parts.join(' ')) : ''
}


// Page Title
const pageTitle = computed(() => {
    let title = t('edit_publication.title', 'Editar Publicación')
    if (formState.value.tipo && tipos.value.length) {
        const type = tipos.value.find(t => t.value === formState.value.tipo[0])
        if (type) title += `: ${type.title}`
    }
    return title
})

const getMainImageUrl = () => {
    const img = formState.value.acf.motor_image
    if (img?.url) return img.url
    if (img?.file) return URL.createObjectURL(img.file)
    return ''
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
        'trash': 'Papelera' // Fallback simple
    };
    
    return options[statusKey] || t('publication_list.status_options.unknown', 'Desconocido');
})

const statusColor = computed(() => {
    switch (formState.value.status) {
        case 'publish': return 'success';
        case 'pending': return 'warning'; // En revisión - Naranja
        case 'draft': return 'secondary'; // Borrador - Gris
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
        <div class="d-flex flex-wrap justify-space-between gap-4 mb-6 align-center">
            <VCardTitle class="pa-6 pb-0">
          <div class="d-flex justify-space-between align-center flex-wrap gap-4">
            <span class="text-h5 font-weight-bold text-premium-title">{{ t('Edit Publication') }}</span>
            <span class="text-caption text-medium-emphasis">Los campos marcados con <span class="text-error">*</span> son obligatorios</span>
          </div>
        </VCardTitle>
            <div class="d-flex gap-4">
                 <VBtn 
                    variant="tonal" 
                    color="secondary"
                    @click="router.push('/dashboard/publications/publication/list')"
                 >
                    {{ t('edit_publication.discard', 'Descartar') }}
                 </VBtn>
                 <VBtn
                    variant="tonal"
                    color="secondary"
                    :disabled="isReadOnly"
                    @click="updatePublication('draft')"
                 >
                    {{ t('edit_publication.save_draft', 'Guardar Borrador') }}
                 </VBtn>
                 <VBtn
                    color="primary"
                    :loading="isLoading"
                    :disabled="isReadOnly"
                    @click="updatePublication('publish')"
                 >
                    {{ t('edit_publication.update_publication', 'Actualizar') }}
                 </VBtn>
            </div>
        </div>
        
        <VRow>
            <!-- Left Column: Main Content -->
             <VCol cols="12" md="8">
                 <!-- Tabs for better organization in Edit mode -->
                <VCard class="mb-6">
                    <VTabs v-model="activeTab" grow color="primary">
                        <VTab :value="0"><VIcon start icon="tabler-info-circle"/> {{ t('edit_publication.tabs.basic', 'Básico') }}</VTab>
                        <VTab :value="1"><VIcon start icon="tabler-list-details"/> {{ t('edit_publication.tabs.specs', 'Especificaciones') }}</VTab>
                        <VTab :value="2"><VIcon start icon="tabler-photo"/> {{ t('edit_publication.tabs.media', 'Multimedia') }}</VTab>
                        <VTab :value="3"><VIcon start icon="tabler-file"/> {{ t('edit_publication.tabs.docs', 'Docs') }}</VTab>
                    </VTabs>
                    
                    <VCardText class="pa-6">
                         <VWindow v-model="activeTab">
                             <!-- Basic Info -->
                            <VWindowItem :value="0">
                                <StepBasicInfo 
                                    :form-state="formState" 
                                    :marcas="marcas" 
                                    :categories="categories"
                                    @update:form-state="setFormState" 
                                />
                                <div class="mt-6">
                                     <StepLocationCondition
                                        :form-state="formState"
                                        @update:form-state="setFormState"
                                    />
                                </div>
                            </VWindowItem>
                            
                            <!-- Tech Specs -->
                             <VWindowItem :value="1">
                                <StepTechSpecs
                                     :form-state="formState"
                                     :is-motor="true"
                                     @update:form-state="setFormState"
                                />
                             </VWindowItem>
                             
                             <!-- Media -->
                             <VWindowItem :value="2">
                                  <StepMedia
                                    :form-state="formState"
                                    @update:form-state="setFormState"
                                />
                             </VWindowItem>
                             
                             <!-- Docs -->
                             <VWindowItem :value="3">
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
             
             <!-- Right Column: Status/Quick Actions -->
             <VCol cols="12" md="4">
                  <!-- Maybe some analytics or quick status info here -->
                  <!-- Publication Summary Sidebar -->
                  <VCard class="mb-6">
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
                               {{ formState.title || t('edit_publication.no_title', 'Sin título') }}
                           </VCardTitle>
                           <VCardSubtitle class="text-body-2">
                               ID: #{{ postId }}
                           </VCardSubtitle>
                       </VCardItem>
                        
                       <VDivider />

                       <VCardText class="py-4">
                           <div class="d-flex justify-space-between align-center mb-3">
                               <span class="text-body-2 text-medium-emphasis">{{ t('add_publication.post_details.price') }}:</span>
                               <span class="text-h6 font-weight-bold text-primary">
                                   {{ formState.acf.precio_de_venta ? `${formState.acf.precio_de_venta}€` : '-' }}
                               </span>
                           </div>
                           <div class="d-flex justify-space-between align-center mb-3">
                               <span class="text-body-2 text-medium-emphasis">{{ t('add_publication.post_details.stock') }}:</span>
                               <VChip size="small" :color="formState.acf.stock > 0 ? 'success' : 'error'" variant="tonal">
                                   {{ formState.acf.stock }} u.
                               </VChip>
                           </div>
                           <div class="d-flex justify-space-between align-center">
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
                       
                       <VCardActions class="pa-4">
                            <VBtn 
                                block 
                                variant="outlined" 
                                :href="`/producto/${formState.slug || ''}`" 
                                target="_blank"
                                prepend-icon="tabler-external-link"
                                :disabled="!formState.slug || formState.status !== 'publish'"
                            >
                                Ver en Web
                            </VBtn>
                       </VCardActions>
                  </VCard>
             </VCol>
        </VRow>

    </VForm>
  </div>
</template>
