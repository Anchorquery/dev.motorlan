import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useApi } from '@/composables/useApi'

const countriesCache = ref<{ title: string; value: string }[]>([])
let fetched = false

const fallbackMap: Record<string, string> = {
  'es': 'España',
  'pt': 'Portugal',
  'fr': 'Francia',
  'it': 'Italia',
  'de': 'Alemania',
  'be': 'Bélgica',
  'nl': 'Países Bajos',
  'gb': 'Reino Unido',
  'us': 'Estados Unidos',
  'al': 'Albania',
  'gr': 'Grecia',
  'ad': 'Andorra'
}

export const useCountries = () => {
  const { locale } = useI18n()

  const fetchCountries = async () => {
    if (fetched && countriesCache.value.length > 0) return countriesCache.value

    const lang = locale.value || 'es'
    const { data } = await useApi<{ title: string; value: string }[]>(`/wp-json/motorlan/v1/countries?lang=${lang}`, { immediate: false }).get().json().execute()
    if (data.value) {
      countriesCache.value = data.value
      fetched = true
    }

    return countriesCache.value
  }

  const getCountryName = (code: string | null | undefined): string => {
    if (!code) return ''
    const normalizedCode = String(code).toLowerCase().trim()
    
    // Try cache first
    const country = countriesCache.value.find((c: { title: string; value: string }) => String(c.value).toLowerCase() === normalizedCode)
    if (country) return country.title.toUpperCase()

    // Try fallback map
    if (fallbackMap[normalizedCode]) return fallbackMap[normalizedCode].toUpperCase()

    // Return the code if nothing found
    return normalizedCode.toUpperCase()
  }

  return {
    countries: countriesCache,
    fetchCountries,
    getCountryName,
  }
}
