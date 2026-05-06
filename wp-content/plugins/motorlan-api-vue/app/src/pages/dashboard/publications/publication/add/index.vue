<script setup lang="ts">
import { computed, ref, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { VForm } from 'vuetify/components'

// Components
import StepTypeSelection from '@/pages/dashboard/publications/components/StepTypeSelection.vue'
import StepBasicInfo from '@/pages/dashboard/publications/components/StepBasicInfo.vue'
import StepTechSpecs from '@/pages/dashboard/publications/components/StepTechSpecs.vue'
import StepMedia from '@/pages/dashboard/publications/components/StepMedia.vue'
import StepLocationCondition from '@/pages/dashboard/publications/components/StepLocationCondition.vue'
import StepDocumentation from '@/pages/dashboard/publications/components/StepDocumentation.vue'

// Composables
import { usePublicationForm } from '@/composables/usePublicationForm'
import { useApi } from '@/composables/useApi'
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
const categories = ref<{ title: string; value: number }[]>([])
const tipos = ref<{ title: string; value: number; slug: string }[]>([])

// Load initial data
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
    
    // Check if user is logged in
    if (!userStore.getIsLoggedIn) {
         showToast(t('add_publication.toasts.login_required', 'Debes iniciar sesión.'), 'error')
         router.push('/login')
    } else if (userStore.getUser?.id) {
         // Set Author
         setFormState({ author: userStore.getUser.id })
    }

  } catch (e) {
      console.error(e)
      showToast(t('add_publication.toasts.fetch_error', 'Error al cargar datos iniciales'), 'error')
  }
})

// Sync type selection with formState.tipo
const handleTypeSelect = (slug: string) => {
    // Find ID from slug
    if (!tipos.value) return 
    
    const typeObj = tipos.value.find(t => t.slug === slug)
    if (typeObj) {
        setFormState({ tipo: [typeObj.value] })
    }
}

// Computed for selected type slug to avoid complex template logic and potential undefined errors
const selectedTypeSlug = computed(() => {
    if (!formState.value || !formState.value.tipo || formState.value.tipo.length === 0) return null
    if (!tipos.value) return null
    
    const selectedId = formState.value.tipo[0]
    const typeObj = tipos.value.find(t => t.value === selectedId)
    return typeObj ? typeObj.slug : null
})


const nextStep = async () => {
    // Validate current step
    let validStep = true
    
    if (currentStep.value === 1 && step1Form.value) {
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
    if (currentStep.value > 0) currentStep.value--
}

const submitForm = async (status: string) => {
    isLoading.value = true
    setFormState({ status })
    
    try {
        const formData = new FormData()
        
        // Build slug
        const slug = buildPublicationSlug()
        
        // Append basic fields
        formData.append('title', formState.value.title)
        formData.append('status', formState.value.status)
        if (slug) formData.append('slug', slug)
        if (formState.value.author) formData.append('author', formState.value.author.toString())
        
        // Taxonomies
        formData.append('categories', JSON.stringify(formState.value.categories))
        formData.append('tipo', JSON.stringify(formState.value.tipo))
        
        // ACF - separate files
        const { motor_image, motor_gallery, documentacion_adicional, ...acfRest } = formState.value.acf
        formData.append('acf', JSON.stringify(acfRest))
        
        // Main Image
        if (motor_image && motor_image.file) {
            formData.append('motor_image', motor_image.file)
        }
        
        // Gallery
        if (motor_gallery && motor_gallery.length) {
            motor_gallery.forEach(img => {
                if (img.file) formData.append('motor_gallery[]', img.file)
            })
        }
        
        // Docs
        const docNames: any[] = []
        if (documentacion_adicional && documentacion_adicional.length) {
            documentacion_adicional.forEach(doc => {
                if (doc.archivo instanceof File) {
                     formData.append('documentacion_adicional_archivos[]', doc.archivo)
                     docNames.push({ nombre: doc.nombre })
                } else if (doc.archivo && 'file' in doc.archivo && doc.archivo.file instanceof File) {
                     formData.append('documentacion_adicional_archivos[]', doc.archivo.file)
                     docNames.push({ nombre: doc.nombre })
                }
            })
        }
        if (docNames.length) {
             formData.append('documentacion_adicional_nombres', JSON.stringify(docNames))
        }

        await useApi('/wp-json/motorlan/v1/publicaciones', {
            method: 'POST',
            body: formData
        })
        
        showToast(t('add_publication.toasts.create_success', 'Publicación creada con éxito'), 'success')
        router.push('/dashboard/publications/publication/list')

    } catch (e: any) {
        console.error(e)
        showToast(t('add_publication.toasts.create_error', e.message), 'error')
    } finally {
        isLoading.value = false
    }
}

const buildPublicationSlug = () => {
      const slugify = (val: string) => val.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '')
      
      const parts = [
        formState.value.title,
        formState.value.acf.tipo_o_referencia,
        formState.value.acf.potencia ? `${formState.value.acf.potencia} kW` : null,
        formState.value.acf.velocidad ? `${formState.value.acf.velocidad} rpm` : null,
      ].filter(Boolean) as string[]

      return parts.length ? slugify(parts.join(' ')) : ''
}

// Auto-generate title helper
watch(() => [formState.value.tipo, formState.value.acf.marca, formState.value.acf.tipo_o_referencia, formState.value.acf.potencia, formState.value.acf.velocidad], () => {
    // Future implementation
})

</script>

<template>
  <div class="add-publication-wrapper">
    <!-- Stepper Header -->
    <VCard class="mb-6">
        <VCardText>
            <div class="d-flex justify-space-between align-center overflow-x-auto pb-2">
                <div 
                    v-for="(step, index) in steps" 
                    :key="index"
                    class="d-flex flex-column align-center px-4 step-item"
                    :class="{ 
                        'text-primary': currentStep >= index, 
                        'text-medium-emphasis': currentStep < index,
                        'active-step': currentStep === index
                    }"
                    @click="index < currentStep ? currentStep = index : null"
                    style="cursor: pointer;"
                >
                    <div class="step-icon mb-2 rounded-circle d-flex align-center justify-center border"
                         :class="currentStep >= index ? 'bg-primary border-primary text-white' : 'border-medium-emphasis'"
                         style="width: 40px; height: 40px;"
                    >
                        <VIcon :icon="step.icon" size="20" />
                    </div>
                    <span class="text-caption font-weight-bold text-no-wrap">{{ step.title }}</span>
                </div>
            </div>
        </VCardText>
    </VCard>

    <!-- Form Content -->
    <VCard elevation="2" class="mb-6">
        <VCardText class="pa-6">
            
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
                            :categories="categories"
                            @update:form-state="setFormState" 
                        />
                    </VForm>
                </VWindowItem>

                <!-- Step 2: Tech Specs -->
                <VWindowItem :value="2">
                    <VForm ref="step2Form" @submit.prevent>
                        <StepTechSpecs
                             :form-state="formState"
                             :is-motor="true"
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
        
        <VCardActions class="pa-6 border-t d-flex justify-space-between">
             <VBtn 
                v-if="currentStep > 0"
                variant="tonal" 
                color="secondary"
                prepend-icon="tabler-arrow-left"
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
                    @click="router.push('/dashboard/publications/publication/list')"
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
                    @click="submitForm('publish')"
                 >
                     {{ t('add_publication.buttons.publish', 'Publicar') }}
                 </VBtn>
             </div>
        </VCardActions>
    </VCard>
  </div>
</template>

<style lang="scss" scoped>
.step-item {
    min-width: 100px;
    opacity: 0.7;
    transition: all 0.3s ease;
    
    &.active-step {
        opacity: 1;
        transform: scale(1.05);
        font-weight: bold;
    }
}

.step-icon {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
</style>
