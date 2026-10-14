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
  tipo: number[]
  acf: {
    marca: number | null
    marca_custom: string
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
    precio_de_venta: number | null
    precio_negociable: string
    stock: number
    documentacion_adicional: PublicationDocument[]
    [key: string]: any
  }
  author: number | null
}

// 'mostrar_precio' eliminado: no existe en ACF (creaba metas sueltas).
// 'posibilidad_de_alquiler' usa las choices reales de ACF ('Sí' / 'No').
const defaultState: PublicationFormState = {
  id: null,
  title: '',
  status: 'publish',
  tipo: [],
  acf: {
    marca: null,
    marca_custom: '',
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
    posibilidad_de_alquiler: 'No',
    tipo_de_alimentacion: 'ac',
    precio_de_venta: null,
    precio_negociable: 'no',
    stock: 1,
    documentacion_adicional: [],
  },
  author: null,
}

/** Máximo de imágenes de galería (ACF max = 5; el backend lo valida). */
export const MAX_GALLERY_IMAGES = 5

/** Campos de especificaciones de motor (se limpian al elegir un tipo que no es motor). */
export const MOTOR_SPEC_FIELDS = ['potencia', 'velocidad', 'par_nominal', 'voltaje', 'intensidad'] as const

const hasValue = (v: any) => v !== null && v !== undefined && String(v).trim() !== ''

/**
 * Regla de Vuetify: vacío o número > 0 (también la usa el backend).
 */
export const positiveNumberRule = (message: string) => (v: any) => !hasValue(v) || Number(String(v).replace(',', '.')) > 0 || message

/**
 * Regla de Vuetify: vacío o número >= 0.
 */
export const nonNegativeNumberRule = (message: string) => (v: any) => !hasValue(v) || Number(String(v).replace(',', '.')) >= 0 || message

/**
 * Límites de subida expuestos por WordPress en wpData (0 = desconocido: no se comprueba en cliente).
 */
export const getUploadLimits = () => {
  const wp = (typeof window !== 'undefined') ? (window as any).wpData : null

  return {
    maxFile: Number(wp?.max_upload_size) || 0,
    maxPost: Number(wp?.post_max_size) || 0,
  }
}

/**
 * Devuelve el primer archivo que supera el tamaño máximo por archivo, o null.
 */
export const findOversizedFile = (files: File[]): File | null => {
  const { maxFile } = getUploadLimits()
  if (!maxFile)
    return null

  return files.find(f => f.size > maxFile) || null
}

/**
 * ¿La suma de archivos supera post_max_size? (margen de 256 KB para el resto del formulario)
 */
export const exceedsTotalUploadSize = (files: File[]): boolean => {
  const { maxPost } = getUploadLimits()
  if (!maxPost)
    return false

  const total = files.reduce((acc, f) => acc + f.size, 0)

  return total > maxPost - 256 * 1024
}

/**
 * Formatea bytes como "8 MB" para los mensajes.
 */
export const formatBytes = (bytes: number): string => {
  if (!bytes)
    return '0 B'
  const units = ['B', 'KB', 'MB', 'GB']
  const i = Math.min(units.length - 1, Math.floor(Math.log(bytes) / Math.log(1024)))

  return `${Math.round((bytes / 1024 ** i) * 10) / 10} ${units[i]}`
}

/**
 * Reduce en el navegador las fotos grandes (móvil: 4-12 MB) a JPEG de ~2000 px antes de
 * subirlas. Evita superar upload_max_filesize/post_max_size y los timeouts del servidor al
 * generar miniaturas. Si algo falla (formato no decodificable, navegador antiguo) se
 * devuelve el archivo original y el backend decide.
 */
export const compressImageFile = async (file: File, maxDim = 2000, quality = 0.85): Promise<File> => {
  try {
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size <= 1024 * 1024)
      return file
    if (typeof createImageBitmap !== 'function' || typeof document === 'undefined')
      return file

    const bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' } as any)
    const scale = Math.min(1, maxDim / Math.max(bitmap.width, bitmap.height))
    const width = Math.max(1, Math.round(bitmap.width * scale))
    const height = Math.max(1, Math.round(bitmap.height * scale))

    const canvas = document.createElement('canvas')
    canvas.width = width
    canvas.height = height
    const ctx = canvas.getContext('2d')
    if (!ctx) {
      bitmap.close?.()

      return file
    }

    // Fondo blanco: las transparencias de PNG no se vuelven negras en JPEG
    ctx.fillStyle = '#ffffff'
    ctx.fillRect(0, 0, width, height)
    ctx.drawImage(bitmap, 0, 0, width, height)
    bitmap.close?.()

    const blob: Blob | null = await new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', quality))
    if (!blob || blob.size >= file.size)
      return file

    const baseName = file.name.replace(/\.[^.]+$/, '') || 'imagen'

    return new File([blob], `${baseName}.jpg`, { type: 'image/jpeg', lastModified: file.lastModified })
  }
  catch (e) {
    console.warn('[publication] No se pudo optimizar la imagen, se sube la original', e)

    return file
  }
}

/**
 * Libera las URLs blob: de las previsualizaciones de imágenes (DropZone las crea por archivo).
 */
export const revokePublicationBlobUrls = (acf: PublicationFormState['acf'] | null | undefined) => {
  if (!acf)
    return
  const items: (PublicationFile | null | undefined)[] = [acf.motor_image, ...(acf.motor_gallery || [])]
  items.forEach(item => {
    if (item?.url && item.url.startsWith('blob:'))
      URL.revokeObjectURL(item.url)
  })
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
