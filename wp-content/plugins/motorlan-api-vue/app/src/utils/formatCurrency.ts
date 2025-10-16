/**
 * Formatea un valor numérico o string a formato de moneda en euros (EUR) para España.
 * Devuelve null si el valor no es válido o no puede ser convertido.
 * 
 * Ejemplos:
 * - formatCurrency(1234.56) => "1.234,56 €"
 * - formatCurrency("1234,56") => "1.234,56 €"
 * - formatCurrency(null) => null
 */
export function formatCurrency(value: unknown): string | null {
  if (value === null || value === undefined || value === '')
    return null

  // Crea el formateador de moneda
  const euroFormatter = new Intl.NumberFormat('es-ES', {
    style: 'currency',
    currency: 'EUR',
    minimumFractionDigits: 2,
  })

  if (typeof value === 'number' && Number.isFinite(value)) {
    return euroFormatter.format(value)
  }

  if (typeof value === 'string') {
    const normalized = value.replace(/[^\d,.-]/g, '').replace(/\./g, '').replace(',', '.')
    const parsed = Number(normalized)
    if (!Number.isNaN(parsed)) {
      return euroFormatter.format(parsed)
    }
    // Retorna el string original prefijado con símbolo de euro si no es numérico
    return `€ ${value}`
  }

  return null
}