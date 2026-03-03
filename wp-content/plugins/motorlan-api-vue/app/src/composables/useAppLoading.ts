/**
 * Composable para manejar el estado de carga global de la app.
 * Sincroniza el skeleton HTML overlay con el ciclo de vida de Suspense.
 */

const isAppReady = ref(false)
const isFirstLoad = ref(true)
let safetyTimeoutId: ReturnType<typeof setTimeout> | null = null

export function useAppLoading() {
  /**
   * Marca la app como lista y oculta el skeleton overlay.
   * Debe llamarse cuando Suspense resuelve por primera vez.
   */
  function markAppReady() {
    if (isAppReady.value) return

    // Limpiar timeout de seguridad si existe
    if (safetyTimeoutId) {
      clearTimeout(safetyTimeoutId)
      safetyTimeoutId = null
    }

    isAppReady.value = true
    isFirstLoad.value = false

    // Ocultar el skeleton overlay (está fuera de Vue, no fue reemplazado)
    const skeletonOverlay = document.getElementById('motorlan-skeleton-overlay')
    if (skeletonOverlay) {
      skeletonOverlay.classList.add('is-hidden')
      // Remover del DOM después de la transición
      setTimeout(() => {
        skeletonOverlay.remove()
      }, 350)
    }
  }

  /**
   * Inicia un timeout de seguridad para ocultar el skeleton
   * aunque Suspense no resuelva (por si hay errores).
   */
  function startSafetyTimeout(ms = 5000) {
    if (safetyTimeoutId || isAppReady.value) return

    safetyTimeoutId = setTimeout(() => {
      console.warn('[useAppLoading] Safety timeout triggered - forcing app ready')
      markAppReady()
    }, ms)
  }

  /**
   * Resetea el estado (para testing o casos especiales)
   */
  function reset() {
    isAppReady.value = false
    isFirstLoad.value = true
    if (safetyTimeoutId) {
      clearTimeout(safetyTimeoutId)
      safetyTimeoutId = null
    }
  }

  return {
    isAppReady: readonly(isAppReady),
    isFirstLoad: readonly(isFirstLoad),
    markAppReady,
    startSafetyTimeout,
    reset,
  }
}
