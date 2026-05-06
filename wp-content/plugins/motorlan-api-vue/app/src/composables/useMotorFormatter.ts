
/**
 * Composable para formatear nombres de motores de manera consistente
 * 
 * Formato: MARCA MODELO POTENCIA
 * Ejemplo: MITSUBISHI HC-KFS73G1 750 KW
 */
export const useMotorFormatter = () => {
	/**
		* Formatea el nombre completo del motor basado en sus datos
		*/
	const formatMotorName = (publication: Record<string, any>): string => {
		const parts: string[] = []

/*
		// 1. Tipo de producto (de taxonomía 'tipo')
		if (publication.tipo && Array.isArray(publication.tipo) && publication.tipo.length > 0) {
			const tipoName = publication.tipo[0].name || publication.tipo[0].slug
			if (tipoName) parts.push(tipoName.toUpperCase())
		}
*/

		// 2. Marca (puede venir como objeto, string, o necesitar buscar)
		const acf = publication.acf || {}
		if (publication.marca_name) {
			parts.push(publication.marca_name.toUpperCase())
		} else if (acf.marca) {
			// Si marca es un objeto con name
			if (typeof acf.marca === 'object' && acf.marca.name) {
				parts.push(acf.marca.name.toUpperCase())
			}
			// Si marca es string directo
			else if (typeof acf.marca === 'string' && isNaN(Number(acf.marca))) {
				parts.push(acf.marca.toUpperCase())
			}
		}

		// 3. Modelo/Referencia
		if (acf.tipo_o_referencia) {
			parts.push(acf.tipo_o_referencia.toUpperCase())
		}

		// 4. Potencia o Par (priorizar potencia)
		if (acf.potencia) {
			parts.push(`${acf.potencia} KW`)
		} else if (acf.par_nominal) {
			parts.push(`${acf.par_nominal} NM`)
		}

		// Si no hay suficientes datos, retornar el título original
		if (parts.length === 0 && publication.title) {
			return publication.title
		}

		return parts.filter(Boolean).join(' ')
	}

	/**
		* Genera un slug basado en los datos del motor
		* Compatible con la función PHP motorlan_generate_publicacion_slug
		*/
	const generateSlug = (data: Record<string, any>): string => {
		const parts: string[] = []
		const acf = data.acf || {}

		// 1. Marca
		if (data.marca_name) {
			parts.push(data.marca_name)
		} else if (acf.marca && typeof acf.marca === 'object' && acf.marca.name) {
			parts.push(acf.marca.name)
		}

		// 2. Referencia/Modelo
		if (acf.tipo_o_referencia) {
			parts.push(acf.tipo_o_referencia)
		}

		// 3. Potencia o Par
		if (acf.potencia) {
			parts.push(`${acf.potencia}KW`)
		} else if (acf.par_nominal) {
			parts.push(`${acf.par_nominal}Nm`)
		}

		// Slugify
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

	/**
		* Genera una vista previa del nombre formateado desde un formState (formularios)
		*/
	const getFormattedPreview = (formState: Record<string, any>, marcas: any[] = [], tipos: any[] = []): string => {
		const parts: string[] = []
		const acf = formState.acf || {}

/*
		// 1. Tipo de producto
		if (formState.tipo && Array.isArray(formState.tipo) && formState.tipo.length > 0 && tipos.length > 0) {
			const tipoObj = tipos.find(t => t.value === formState.tipo[0])
			if (tipoObj) parts.push(tipoObj.title.toUpperCase())
		}
*/

		// 2. Marca
		if (acf.marca && marcas.length > 0) {
			const marcaObj = marcas.find(m => m.value === acf.marca)
			if (marcaObj) parts.push(marcaObj.title.toUpperCase())
		}

		// 3. Referencia
		if (acf.tipo_o_referencia) {
			parts.push(acf.tipo_o_referencia.toUpperCase())
		}

		// 4. Potencia o Par
		if (acf.potencia) {
			parts.push(`${acf.potencia} KW`)
		} else if (acf.par_nominal) {
			parts.push(`${acf.par_nominal} NM`)
		}

		if (parts.length === 0) {
			return formState.title || 'Vista previa no disponible'
		}

		return parts.filter(Boolean).join(' ')
	}

	/**
		* Identifica campos faltantes para el formato completo
		*/
	const getMissingFields = (publication: Record<string, any>): string[] => {
		const missing: string[] = []
		const acf = publication.acf || {}

		if (!publication.tipo || !Array.isArray(publication.tipo) || publication.tipo.length === 0) {
			missing.push('tipo')
		}

		if (!publication.marca_name && (!acf.marca || (typeof acf.marca === 'number'))) {
			missing.push('marca')
		}

		if (!acf.tipo_o_referencia) {
			missing.push('tipo_o_referencia')
		}

		if (!acf.potencia && !acf.par_nominal) {
			missing.push('potencia o par_nominal')
		}

		if (!acf.velocidad) {
			missing.push('velocidad')
		}

		return missing
	}

	return {
		formatMotorName,
		generateSlug,
		getFormattedPreview,
		getMissingFields,
	}
}
