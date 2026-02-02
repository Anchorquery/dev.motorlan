import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useApi } from '@/composables/useApi'
import { useToast } from '@/composables/useToast'
import { useUserStore } from '@/@core/stores/user'

export interface PublicationFile {
  file?: File
  url?: string
  id?: number
  name?: string
  filename?: string
}

export interface PublicationDocument {
  nombre: string
  archivo: PublicationFile | File | null
}

export interface PublicationFormState {
  id?: number | null
  title: string
  slug?: string
  status: string
  categories: number[]
  tipo: number[]
  acf: {
    marca: number | null
    tipo_o_referencia: string
    motor_image: PublicationFile | null
    motor_gallery: PublicationFile[]
    potencia: number | null
    velocidad: number | null
    par_nominal: number | null
    voltaje: number | null
    intensidad: number | null
    pais: string | null
    provincia: string
    estado_del_articulo: string
    informe_de_reparacion: number | null
    descripcion: string
    posibilidad_de_alquiler: string
    tipo_de_alimentacion: string
    servomotores: boolean
    regulacion_electronica_drivers: boolean
    precio_de_venta: number | null
    precio_negociable: string
    stock: number
    documentacion_adicional: PublicationDocument[]
    [key: string]: any
  }
  author: number | null
}

const defaultState: PublicationFormState = {
  id: null,
  title: '',
  status: 'publish',
  categories: [],
  tipo: [],
  acf: {
    marca: null,
    tipo_o_referencia: '',
    motor_image: null,
    motor_gallery: [],
    potencia: null,
    velocidad: null,
    par_nominal: null,
    voltaje: null,
    intensidad: null,
    pais: null,
    provincia: '',
    estado_del_articulo: 'new',
    informe_de_reparacion: null,
    descripcion: '',
    posibilidad_de_alquiler: 'no',
    tipo_de_alimentacion: 'ac',
    servomotores: false,
    regulacion_electronica_drivers: false,
    precio_de_venta: null,
    precio_negociable: 'no',
    stock: 1,
    documentacion_adicional: [],
  },
  author: null,
}

export const usePublicationForm = () => {
  const { t } = useI18n()
  const { showToast } = useToast()
  const userStore = useUserStore()

  const formState = ref<PublicationFormState>(JSON.parse(JSON.stringify(defaultState)))
  const isLoading = ref(false)
  const isEditMode = computed(() => !!formState.value.id)

  const resetForm = () => {
    formState.value = JSON.parse(JSON.stringify(defaultState))
  }

  const setFormState = (data: Partial<PublicationFormState>) => {
    formState.value = { ...formState.value, ...data }
  }

  const isMotor = computed(() => {
    // This relies on the 'tipos' list being available or checking against a known ID/slug
    // We'll trust the consumer to pass the right 'tipo' array or handle logic externally
    // For now, let's assume if the logic is needed, it will be handled by the component using this.
    return true // Placeholder, logic should be in the component context with access to 'tipos'
  })

  return {
    formState,
    isLoading,
    isEditMode,
    isMotor,
    resetForm,
    setFormState,
  }
}
