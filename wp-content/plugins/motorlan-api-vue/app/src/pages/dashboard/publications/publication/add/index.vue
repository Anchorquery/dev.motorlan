<script setup lang="ts">
import { computed, ref, onMounted, onBeforeUnmount, watch } from 'vue'
import { onBeforeRouteLeave, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { VForm } from 'vuetify/components'

// Components
import StepTypeSelection from '@/pages/dashboard/publications/components/StepTypeSelection.vue'
import StepBasicInfo from '@/pages/dashboard/publications/components/StepBasicInfo.vue'
import StepTechSpecs from '@/pages/dashboard/publications/components/StepTechSpecs.vue'
import StepMedia from '@/pages/dashboard/publications/components/StepMedia.vue'
import StepLocationCondition from '@/pages/dashboard/publications/components/StepLocationCondition.vue'
import StepDocumentation from '@/pages/dashboard/publications/components/StepDocumentation.vue'
import { findTipoForUiSlug, uiSlugForTipo } from '@/utils/tipoMapping'

// Composables
import {
  MOTOR_SPEC_FIELDS,
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
import { useUserStore } from '@/@core/stores/user'

const { t } = useI18n()
const router = useRouter()
const { showToast } = useToast()
const userStore = useUserStore()
const { formState, setFormState } = usePublicationForm()

const currentStep = ref(0)
const isFormValid = ref(false)
// Individual refs for each step's form
const step1Form = ref<VForm | null>(null)
const step2Form = ref<VForm | null>(null)
const step3Form = ref<VForm | null>(null)
const step4Form = ref<VForm | null>(null)
const step5Form = ref<VForm | null>(null)

const isLoading = ref(false)
// Alta completada: no avisar de "cambios sin guardar" al salir.
const isSubmitted = ref(false)

// Id por intento de alta: el backend lo usa para no crear duplicados si se reintenta
// (doble clic, timeout del proxy...) y para descartar altas que quedaron a medias.
const requestId = (typeof crypto !== 'undefined' && typeof (crypto as any).randomUUID === 'function')
  ? (crypto as any).randomUUID() as string
  : `${Date.now()}-${Math.random().toString(36).slice(2)}`

const steps = computed(() => [
    { title: t('add_publication.steps.type', 'Tipo'), icon: 'tabler-category-2' },
    { title: t('add_publication.steps.basic_info', 'Info Básica'), icon: 'tabler-info-circle' },
    { title: t('add_publication.steps.specs', 'Especificaciones'), icon: 'tabler-list-details' },
    { title: t('add_publication.steps.media', 'Multimedia'), icon: 'tabler-photo' },
    { title: t('add_publication.steps.details', 'Detalles'), icon: 'tabler-map-pin' },
    { title: t('add_publication.steps.docs', 'Documentación'), icon: 'tabler-file' },
])

// Data for selects
const marcas = ref<{ title: string; value: number }[]>([])
const tipos = ref<{ title: string; value: number; slug: string }[]>([])
const countries = ref<{ title: string; value: string }[]>([])

// Guarda la opción de UI elegida (motor-ac/motor-dc/regulador/otro-repuesto) aunque
// la lista de tipos aún no haya cargado, para poder resolver el término después.
const selectedTypeUi = ref<string | null>(null)

// ---------------------------------------------------------------------------
// Borrador local (sessionStorage): si la sesión caduca a mitad del asistente o se
// recarga la página, no se pierde lo escrito. Los archivos no se pueden guardar.
// ---------------------------------------------------------------------------
const draftKey = computed(() => `motorlan_add_publication_draft_${userStore.getUser?.id || 'anon'}`)

const readDraft = (): any => {
  try {
    const raw = sessionStorage.getItem(draftKey.value)

    return raw ? JSON.parse(raw) : null
  }
  catch {
    return null
  }
}

const clearDraft = () => {
  try {
    sessionStorage.removeItem(draftKey.value)
  }
  catch { /* almacenamiento no disponible */ }
}

let draftTimer: ReturnType<typeof setTimeout> | null = null
const saveDraft = () => {
  if (draftTimer)
    clearTimeout(draftTimer)
  draftTimer = setTimeout(() => {
    if (isSubmitted.value)
      return
    try {
      const { motor_image, motor_gallery, documentacion_adicional, ...acfText } = formState.value.acf
      sessionStorage.setItem(draftKey.value, JSON.stringify({
        selectedTypeUi: selectedTypeUi.value,
        currentStep: currentStep.value,
        title: formState.value.title,
        tipo: formState.value.tipo,
        acf: acfText,
      }))
    }
    catch { /* almacenamiento lleno o bloqueado: no es crítico */ }
  }, 500)
}

const restoreDraft = () => {
  const draft = readDraft()
  if (!draft || (!draft.selectedTypeUi && !draft.acf?.tipo_o_referencia))
    return

  setFormState({
    title: draft.title || '',
    tipo: Array.isArray(draft.tipo) ? draft.tipo : [],
    acf: { ...formState.value.acf, ...(draft.acf || {}) },
  })
  selectedTypeUi.value = draft.selectedTypeUi || null
  // Las imágenes no se recuperan: como mucho, volver al paso de Multimedia.
  currentStep.value = Math.min(Number(draft.currentStep) || 0, 3)
  showToast(t('add_publication.toasts.draft_restored', 'Hemos recuperado los datos que estabas rellenando. Vuelve a añadir las imágenes y los documentos.'), 'info')
}

const isDirty = computed(() => {
  const acf = formState.value.acf

  return !!selectedTypeUi.value || !!acf.tipo_o_referencia || !!acf.marca || !!acf.motor_image || !!acf.descripcion
})

watch([formState, selectedTypeUi, currentStep], () => {
  if (isDirty.value)
    saveDraft()
}, { deep: true })

const onBeforeUnload = (e: BeforeUnloadEvent) => {
  if (isDirty.value && !isSubmitted.value) {
    e.preventDefault()
    e.returnValue = ''
  }
}

onBeforeRouteLeave(to => {
  if (isSubmitted.value || !isDirty.value)
    return true

  // Sesión caducada: el borrador queda guardado y se recupera al volver tras iniciar sesión.
  if (to.path.includes('login'))
    return true

  // eslint-disable-next-line no-alert
  const ok = window.confirm(t('add_publication.leave_confirm', 'Tienes una publicación a medias. Si sales se perderán los datos. ¿Quieres salir?'))
  if (ok)
    clearDraft()

  return ok
})

// Load initial data. Se guarda la promesa para que el paso 0 pueda esperarla si el
// usuario elige un tipo antes de que lleguen los datos.
const initialLoad = ref<Promise<void> | null>(null)

onMounted(() => {
  window.addEventListener('beforeunload', onBeforeUnload)

  initialLoad.value = (async () => {
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

      if (!tipos.value.length)
        showToast(t('add_publication.toasts.fetch_types_error', 'No se pudieron cargar los tipos.'), 'error')

      // Check if user is logged in
      if (!userStore.getIsLoggedIn) {
           showToast(t('add_publication.toasts.login_required', 'Debes iniciar sesión.'), 'error')
           router.push('/login')

           return
      }

      restoreDraft()
    }
    catch (e) {
        console.error(e)
        showToast(t('add_publication.toasts.fetch_error', 'Error al cargar datos iniciales'), 'error')
    }
  })()
})

onBeforeUnmount(() => {
  window.removeEventListener('beforeunload', onBeforeUnload)
  if (draftTimer)
    clearTimeout(draftTimer)
  // Liberar las previsualizaciones blob: de las imágenes
  revokePublicationBlobUrls(formState.value.acf)
})

// Resuelve el término real (formState.tipo) a partir de la opción de UI.
const resolveTipoFromUi = () => {
    if (!selectedTypeUi.value || !tipos.value || tipos.value.length === 0) return
    const typeObj = findTipoForUiSlug(tipos.value, selectedTypeUi.value)
    if (typeObj)
        setFormState({ tipo: [typeObj.value] })
}

// Sync type selection with formState.tipo
const handleTypeSelect = (slug: string) => {
    selectedTypeUi.value = slug

    // Motor: fijar la alimentación. Regulador/otros: limpiar las specs de motor que
    // se hubieran rellenado antes de cambiar de tipo (acababan en el título y el slug).
    const isMotorType = slug === 'motor-ac' || slug === 'motor-dc'
    const clearedSpecs = Object.fromEntries(MOTOR_SPEC_FIELDS.map(field => [field, null]))

    setFormState({
        acf: {
            ...formState.value.acf,
            ...(isMotorType
                ? { tipo_de_alimentacion: slug === 'motor-dc' ? 'dc' : 'ac' }
                : clearedSpecs),
        },
    })

    resolveTipoFromUi()
}

// Si el usuario selecciona el tipo antes de que termine la carga async de `tipos`,
// resolvemos el término en cuanto la lista esté disponible (evita que Especificaciones
// Técnicas muestre por error "solo aplican a motores").
watch(tipos, () => resolveTipoFromUi())

// Computed for selected type slug to avoid complex template logic and potential undefined errors
const selectedTypeSlug = computed(() => {
    if (formState.value?.tipo?.length && tipos.value?.length) {
        const typeObj = tipos.value.find(t => t.value === formState.value.tipo[0])
        if (typeObj)
            return uiSlugForTipo(typeObj.slug, formState.value.acf.tipo_de_alimentacion)
    }

    // Fallback mientras se resuelve el término: refleja la opción elegida en la UI.
    return selectedTypeUi.value
})


const nextStep = async () => {
    // Validate current step
    let validStep = true

    if (currentStep.value === 0) {
        // Si los tipos aún no han llegado (TTFB lento), esperar a la carga inicial.
        if (!tipos.value.length && initialLoad.value)
            await initialLoad.value

        // Sin término `tipo` resuelto, Especificaciones no muestra los campos de motor
        // y el backend rechazaría la publicación al final del asistente.
        resolveTipoFromUi()
        if (!formState.value.tipo?.length) {
            showToast(tipos.value.length
                ? t('add_publication.toasts.type_required', 'Selecciona el tipo de publicación')
                : t('add_publication.toasts.fetch_types_error', 'No se pudieron cargar los tipos.'), 'error')
            return
        }
    }
    else if (currentStep.value === 1 && step1Form.value) {
        const { valid } = await step1Form.value.validate()
        validStep = valid
    } else if (currentStep.value === 2 && step2Form.value) {
        const { valid } = await step2Form.value.validate()
        validStep = valid
    } else if (currentStep.value === 3 && step3Form.value) {
         const { valid } = await step3Form.value.validate()
         validStep = valid
    } else if (currentStep.value === 4 && step4Form.value) {
         const { valid } = await step4Form.value.validate()
         validStep = valid
    } else if (currentStep.value === 5 && step5Form.value) {
         const { valid } = await step5Form.value.validate()
         validStep = valid
    }

    if(!validStep) return

    if (currentStep.value < steps.value.length - 1) {
        currentStep.value++
    } else {
        submitForm('publish')
    }
}

const prevStep = () => {
    if (isLoading.value) return
    if (currentStep.value > 0) currentStep.value--
}

const submitForm = async (status: string) => {
    // Guarda de reentrada: el :loading del botón no bloquea Enter/teclado.
    if (isLoading.value || isSubmitted.value) return
    isLoading.value = true
    setFormState({ status })

    try {
        const formData = new FormData()

        // Append basic fields. El slug y el autor los decide el backend.
        formData.append('title', formState.value.title)
        formData.append('status', formState.value.status)
        formData.append('client_request_id', requestId)

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

        // Fotos de móvil (4-12 MB) → JPEG ~2000 px antes de subir.
        const mainFile = motor_image?.file ? await compressImageFile(motor_image.file) : null
        const galleryFiles = await Promise.all(
            (motor_gallery || []).filter(img => img.file).map(img => compressImageFile(img.file as File)),
        )

        const docFiles: File[] = []
        const docNames: any[] = []
        if (documentacion_adicional && documentacion_adicional.length) {
            documentacion_adicional.forEach(doc => {
                if (doc.archivo instanceof File) {
                     docFiles.push(doc.archivo)
                     docNames.push({ nombre: doc.nombre })
                } else if (doc.archivo && 'file' in doc.archivo && doc.archivo.file instanceof File) {
                     docFiles.push(doc.archivo.file)
                     docNames.push({ nombre: doc.nombre })
                }
            })
        }

        // Límites del servidor: avisar antes de enviar (PHP descartaría la petición entera).
        const allFiles = [mainFile, ...galleryFiles, ...docFiles].filter(Boolean) as File[]
        const tooBig = findOversizedFile(allFiles)
        if (tooBig) {
            showToast(t('add_publication.validation.file_too_big', { name: tooBig.name, max: formatBytes(getUploadLimits().maxFile) }, `"${tooBig.name}" supera el tamaño máximo permitido (${formatBytes(getUploadLimits().maxFile)}).`), 'error')
            return
        }
        if (exceedsTotalUploadSize(allFiles)) {
            showToast(t('add_publication.validation.total_too_big', { max: formatBytes(getUploadLimits().maxPost) }, `Los archivos superan en total el máximo permitido (${formatBytes(getUploadLimits().maxPost)}). Quita alguna imagen o documento.`), 'error')
            return
        }

        // Main Image
        if (mainFile)
            formData.append('motor_image', mainFile)

        // Gallery
        galleryFiles.forEach(file => formData.append('motor_gallery[]', file))

        // Docs
        docFiles.forEach(file => formData.append('documentacion_adicional_archivos[]', file))
        if (docNames.length) {
             formData.append('documentacion_adicional_nombres', JSON.stringify(docNames))
        }

        const { data } = assertApiOk(await useApi('/wp-json/motorlan/v1/publicaciones', {
            method: 'POST',
            body: formData
        }).json())

        const out: any = data.value || {}

        isSubmitted.value = true
        clearDraft()

        if (out.status === 'draft' && status !== 'draft')
            showToast(t('add_publication.toasts.saved_as_draft', 'No se pudo subir la imagen principal: se ha guardado como borrador. Edítala para añadirla y enviarla a revisión.'), 'warning')
        else if (Array.isArray(out.upload_errors) && out.upload_errors.length)
            showToast(t('add_publication.toasts.partial_upload', 'Publicación creada, pero algunos archivos no se subieron. Puedes añadirlos editándola.'), 'warning')
        else if (out.status === 'pending')
            showToast(t('add_publication.toasts.created_pending', 'Publicación enviada. Quedará visible cuando un administrador la apruebe.'), 'success')
        else
            showToast(t('add_publication.toasts.create_success', 'Publicación creada con éxito'), 'success')

        router.push('/dashboard/publications/publication/list')

    } catch (e: any) {
        console.error(e)
        // Mostrar el motivo real devuelto por el backend (validación, permisos...)
        const reason = e?.message ? `: ${e.message}` : ''
        showToast(`${t('add_publication.toasts.create_error', 'Error al crear la publicación')}${reason}`, 'error')
    } finally {
        isLoading.value = false
    }
}

const cancelWizard = () => {
    if (isLoading.value) return
    router.push('/dashboard/publications/publication/list')
}

</script>

<template>
  <div class="add-publication-wrapper">
    <!-- Mobile-only compact progress -->
    <div class="d-md-none mb-4 mobile-progress">
        <div class="d-flex align-center justify-space-between mb-2">
            <span class="text-caption font-weight-bold text-primary">
                {{ t('add_publication.step_x_of_y', { current: currentStep + 1, total: steps.length }, 'Paso ' + (currentStep + 1) + ' de ' + steps.length) }}
            </span>
            <span class="text-caption text-medium-emphasis">
                {{ steps[currentStep]?.title }}
            </span>
        </div>
        <VProgressLinear
            :model-value="((currentStep + 1) / steps.length) * 100"
            color="primary"
            height="6"
            rounded
        />
    </div>

    <!-- Stepper Header (desktop only) -->
    <VCard class="mb-6 d-none d-md-block">
        <VCardText class="pa-4">
            <div class="publication-stepper d-flex justify-space-between align-center overflow-x-auto pb-1">
                <div 
                    v-for="(step, index) in steps" 
                    :key="index"
                    class="d-flex flex-column align-center px-4 step-item"
                    :class="{ 
                        'text-primary': currentStep >= index, 
                        'text-medium-emphasis': currentStep < index,
                        'active-step': currentStep === index
                    }"
                    @click="index < currentStep && !isLoading ? currentStep = index : null"
                    style="cursor: pointer;"
                >
                    <div class="step-icon mb-2 rounded-circle d-flex align-center justify-center border"
                         :class="currentStep >= index ? 'bg-primary border-primary text-white' : 'border-medium-emphasis'"
                         style="width: 40px; height: 40px;"
                    >
                        <VIcon :icon="step.icon" size="20" />
                    </div>
                    <span class="publication-stepper__title text-caption font-weight-bold text-no-wrap">{{ step.title }}</span>
                </div>
            </div>
        </VCardText>
    </VCard>

    <!-- Form Content -->
    <VCard elevation="2" class="mb-6">
        <VCardText class="pa-0 pa-md-6 add-pub-card-body">
            
            <VWindow v-model="currentStep">
                
                <!-- Step 0: Type Selection -->
                <VWindowItem :value="0">
                     <StepTypeSelection 
                        :model-value="selectedTypeSlug"
                        @update:model-value="handleTypeSelect"
                        @next="nextStep"
                     />
                </VWindowItem>

                <!-- Step 1: Basic Info -->
                <VWindowItem :value="1">
                    <VForm ref="step1Form" @submit.prevent>
                        <StepBasicInfo
                            :form-state="formState"
                            :marcas="marcas"
                            :tipos="tipos"
                            @update:form-state="setFormState"
                        />
                    </VForm>
                </VWindowItem>

                <!-- Step 2: Tech Specs -->
                <VWindowItem :value="2">
                    <VForm ref="step2Form" @submit.prevent>
                        <StepTechSpecs
                             :form-state="formState"
                             :tipos="tipos"
                             @update:form-state="setFormState"
                        />
                    </VForm>
                </VWindowItem>

                <!-- Step 3: Media -->
                <VWindowItem :value="3">
                    <VForm ref="step3Form" @submit.prevent>
                        <StepMedia
                            :form-state="formState"
                            @update:form-state="setFormState"
                        />
                    </VForm>
                </VWindowItem>

                <!-- Step 4: Details (Loc & Condition) -->
                <VWindowItem :value="4">
                    <VForm ref="step4Form" @submit.prevent>
                        <StepLocationCondition
                            :form-state="formState"
                            :country-options="countries"
                            @update:form-state="setFormState"
                        />
                    </VForm>
                </VWindowItem>
                
                 <!-- Step 5: Documentation -->
                <VWindowItem :value="5">
                    <VForm ref="step5Form" @submit.prevent>
                        <StepDocumentation
                            :form-state="formState"
                            @update:form-state="setFormState"
                        />
                    </VForm>
                </VWindowItem>

            </VWindow>

        </VCardText>
        
        <VCardActions class="pa-6 border-t d-flex justify-space-between publication-form-actions">
             <VBtn 
                v-if="currentStep > 0"
                variant="tonal" 
                color="secondary"
                prepend-icon="tabler-arrow-left"
                :disabled="isLoading"
                @click="prevStep"
             >
                {{ t('add_publication.buttons.back', 'Atrás') }}
             </VBtn>
             <div v-else></div> <!-- Spacer -->

             <div class="d-flex gap-4">
                 <VBtn
                    v-if="currentStep > 0"
                    variant="text"
                    color="secondary"
                    :disabled="isLoading"
                    @click="cancelWizard"
                 >
                     {{ t('add_publication.buttons.cancel', 'Cancelar') }}
                 </VBtn>

                 <VBtn
                    v-if="currentStep < steps.length - 1 && currentStep > 0"
                    color="primary"
                    append-icon="tabler-arrow-right"
                    @click="nextStep"
                 >
                    {{ t('add_publication.buttons.next', 'Siguiente') }}
                 </VBtn>
                 
                 <VBtn
                    v-if="currentStep === steps.length - 1"
                    color="primary"
                    prepend-icon="tabler-check"
                    :loading="isLoading"
                    :disabled="isLoading"
                    @click="nextStep"
                 >
                     {{ t('add_publication.buttons.publish', 'Publicar') }}
                 </VBtn>
             </div>
        </VCardActions>
    </VCard>
  </div>
</template>

<style lang="scss" scoped>
.add-publication-wrapper {
    max-width: 100%;
    overflow-x: hidden;
}

/* Clip VRow negative-margin overflow from step components */
.add-pub-card-body {
    overflow-x: hidden;
}

.publication-stepper {
    scrollbar-width: thin;
}

.step-item {
    min-width: 100px;
    opacity: 0.7;
    transition: all 0.3s ease;
    flex: 0 0 auto;
    
    &.active-step {
        opacity: 1;
        transform: scale(1.05);
        font-weight: bold;
    }
}

.step-icon {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.publication-stepper__title {
    text-align: center;
    line-height: 1.15;
}

.publication-form-actions :deep(.v-btn) {
    min-width: 160px;
}

@media (max-width: 959px) {
    .add-publication-wrapper {
        /* Space for fixed mobile nav (58px) + safe area */
        padding-bottom: calc(58px + env(safe-area-inset-bottom, 12px) + 1rem);
    }

    .publication-stepper {
        gap: 0;
    }

    .step-item {
        min-width: 52px;
        padding-inline: 2px;
    }

    .step-icon {
        width: 28px !important;
        height: 28px !important;
    }

    .publication-stepper__title {
        font-size: 0.58rem;
        white-space: normal;
        max-width: 52px;
        text-align: center;
        line-height: 1.1;
    }

    .publication-form-actions {
        flex-direction: row;
        flex-wrap: wrap;
        gap: 0.5rem;
        padding: 0.75rem !important;
    }

    .publication-form-actions :deep(.v-btn) {
        flex: 1 1 auto;
        min-width: 0;
    }

    /* Kill VRow negative margins completely — VCol 12px padding provides spacing */
    .add-pub-card-body :deep(.v-row) {
        margin-left: 0 !important;
        margin-right: 0 !important;
    }

    /* StepTypeSelection cards: reduce hover transform on mobile */
    .add-pub-card-body :deep(.type-card:hover) {
        transform: none;
    }

    /* StepTypeSelection icon wrapper: smaller on mobile */
    .add-pub-card-body :deep(.icon-wrapper) {
        width: 60px !important;
        height: 60px !important;
    }

    /* StepTypeSelection: reduce top padding for title */
    .add-pub-card-body :deep(.step-type-selection .text-center.mb-8) {
        margin-bottom: 1rem !important;
    }

    /* StepDocumentation doc row: stack on mobile */
    .add-pub-card-body :deep(.d-flex.gap-4.mb-4.align-center.pa-4) {
        flex-wrap: wrap;
        gap: 0.5rem !important;
        padding: 0.75rem !important;
    }
}
</style>
