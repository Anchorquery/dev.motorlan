type MotorLike = {
  title?: string
  marca_name?: string
  acf?: {
    marca?: string | { name?: string }
    tipo_o_referencia?: string
    potencia?: string | number
    par_nominal?: string | number
    velocidad?: string | number
    [key: string]: unknown
  }
}

type BrandOption = {
  title: string
  value: number | string
}

export const useMotorFormatter = () => {
  const formatMotorName = (motor: MotorLike) => {
    const parts: string[] = []
    const acf = motor.acf || {}

    if (motor.marca_name) {
      parts.push(motor.marca_name.toUpperCase())
    }
    else if (acf.marca) {
      if (typeof acf.marca === 'object' && acf.marca.name) {
        parts.push(acf.marca.name.toUpperCase())
      }
      else if (typeof acf.marca === 'string' && Number.isNaN(Number(acf.marca))) {
        parts.push(acf.marca.toUpperCase())
      }
    }

    if (acf.tipo_o_referencia)
      parts.push(acf.tipo_o_referencia.toUpperCase())

    if (acf.potencia) {
      parts.push(`${acf.potencia} KW`)
    }
    else if (acf.par_nominal) {
      parts.push(`${acf.par_nominal} NM`)
    }

    return parts.length === 0 && motor.title ? motor.title : parts.filter(Boolean).join(' ')
  }

  const generateSlug = (motor: MotorLike) => {
    const parts: string[] = []
    const acf = motor.acf || {}

    if (motor.marca_name) {
      parts.push(motor.marca_name)
    }
    else if (acf.marca && typeof acf.marca === 'object' && acf.marca.name) {
      parts.push(acf.marca.name)
    }

    if (acf.tipo_o_referencia)
      parts.push(acf.tipo_o_referencia)

    if (acf.potencia) {
      parts.push(`${acf.potencia}KW`)
    }
    else if (acf.par_nominal) {
      parts.push(`${acf.par_nominal}Nm`)
    }

    return parts
      .filter(Boolean)
      .join(' ')
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .toLowerCase()
      .trim()
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '')
  }

  const getFormattedPreview = (motor: MotorLike, marcas: BrandOption[] = [], tipos: BrandOption[] = []) => {
    const parts: string[] = []
    const acf = motor.acf || {}

    if (acf.marca && marcas.length > 0) {
      const match = marcas.find(item => item.value === acf.marca)
      if (match)
        parts.push(match.title.toUpperCase())
    }

    if (acf.tipo_o_referencia)
      parts.push(acf.tipo_o_referencia.toUpperCase())

    if (acf.potencia) {
      parts.push(`${acf.potencia} KW`)
    }
    else if (acf.par_nominal) {
      parts.push(`${acf.par_nominal} NM`)
    }

    return parts.length === 0 ? motor.title || 'Vista previa no disponible' : parts.filter(Boolean).join(' ')
  }

  const getMissingFields = (motor: MotorLike) => {
    const missing: string[] = []
    const acf = motor.acf || {}

    if (!acf.tipo_o_referencia)
      missing.push('tipo_o_referencia')

    if (!motor.marca_name && (!acf.marca || typeof acf.marca === 'number'))
      missing.push('marca')

    if (!acf.potencia && !acf.par_nominal)
      missing.push('potencia o par_nominal')

    if (!acf.velocidad)
      missing.push('velocidad')

    return missing
  }

  return {
    formatMotorName,
    generateSlug,
    getFormattedPreview,
    getMissingFields,
  }
}
