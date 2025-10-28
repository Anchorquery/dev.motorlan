export function getOrCreateGuestId(storageKey = 'ml_guest_id'): string {
  try {
    const existing = localStorage.getItem(storageKey)
    if (existing && existing.trim().length)
      return existing

    const id = createSimpleId()
    localStorage.setItem(storageKey, id)
    return id
  }
  catch {
    // Fallback if localStorage is unavailable
    return createSimpleId()
  }
}

export function getStoredGuestName(storageKey = 'ml_guest_name'): string | null {
  try {
    const existing = localStorage.getItem(storageKey)
    return existing && existing.trim().length ? existing.trim() : null
  }
  catch {
    return null
  }
}

export function setStoredGuestName(name: string, storageKey = 'ml_guest_name') {
  try {
    if (name && name.trim().length)
      localStorage.setItem(storageKey, name.trim())
  }
  catch {
    // ignore
  }
}

function createSimpleId(): string {
  // Lightweight UUID-like generator (not cryptographically strong)
  const s4 = () => Math.floor((1 + Math.random()) * 0x10000).toString(16).substring(1)

  return `${Date.now().toString(36)}-${s4()}${s4()}-${s4()}-${s4()}-${s4()}${s4()}${s4()}`
}

