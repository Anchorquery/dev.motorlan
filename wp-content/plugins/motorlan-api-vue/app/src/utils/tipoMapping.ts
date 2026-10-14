// Mapeo robusto entre el selector "Tipo de Publicación" de la UI
// (motor-ac / motor-dc / regulador / otro-repuesto) y los términos reales de la
// taxonomía `tipo`. Tolera variaciones de slug del término "Otros"
// (otro-repuesto / repuesto / otros) y, como último recurso, toma el término que
// no es motor ni regulador.

export interface TipoTerm {
  title: string
  value: number
  slug: string
}

/**
 * Devuelve el término de la taxonomía que corresponde a la opción de UI elegida.
 */
export function findTipoForUiSlug(tipos: TipoTerm[] | null | undefined, uiSlug: string | null): TipoTerm | undefined {
  if (!tipos || tipos.length === 0 || !uiSlug)
    return undefined

  const bySlug = (s: string) => tipos.find(t => t.slug === s)

  if (uiSlug === 'motor-ac' || uiSlug === 'motor-dc')
    return bySlug('motor')

  if (uiSlug === 'regulador')
    return bySlug('regulador') || tipos.find(t => /regulador/i.test(t.slug) || /regulador/i.test(t.title))

  // "Otros" / repuesto: probar slugs conocidos y, si no, el término que no es motor ni regulador.
  return bySlug('otro-repuesto')
    || bySlug('repuesto')
    || bySlug('otros')
    || tipos.find(t => t.slug !== 'motor' && !/regulador/i.test(t.slug))
}

/**
 * Inverso: dado el slug del término guardado + la alimentación, devuelve el valor
 * de UI para marcar el radio correcto.
 */
export function uiSlugForTipo(tipoSlug: string | null | undefined, alimentacion: string | null | undefined): string | null {
  if (!tipoSlug)
    return null

  if (tipoSlug === 'motor')
    return alimentacion === 'dc' ? 'motor-dc' : 'motor-ac'

  if (/regulador/i.test(tipoSlug))
    return 'regulador'

  // Cualquier otro término no-motor se trata como "Otros".
  return 'otro-repuesto'
}
