/**
 * @deprecated Id de invitado GLOBAL por navegador: el vendedor de un producto podía deducir
 * con él las salas del mismo invitado con otros vendedores. Usar getOrCreateGuestRoomId().
 */
export function getOrCreateGuestId(storageKey = 'ml_guest_id'): string {
  try {
    const existing = localStorage.getItem(storageKey)
    if (existing && existing.trim().length)
      return existing.trim()

    const id = createStrongId()
    localStorage.setItem(storageKey, id)
    return id
  }
  catch {
    // Fallback if localStorage is unavailable
    return createStrongId()
  }
}

const GUEST_ROOMS_KEY = 'ml_guest_rooms'
const GUEST_ROOM_TOKENS_KEY = 'ml_guest_room_tokens'

const readMap = (key: string): Record<string, string> => {
  try {
    const parsed = JSON.parse(localStorage.getItem(key) || '{}')
    return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {}
  }
  catch {
    return {}
  }
}

const writeMap = (key: string, map: Record<string, string>) => {
  try {
    localStorage.setItem(key, JSON.stringify(map))
  }
  catch {
    // ignore
  }
}

/**
 * Id de invitado DISTINTO por producto y aleatorio de verdad (crypto): con un id global
 * el vendedor de un producto podía deducir las salas del invitado en otros productos.
 */
export function getOrCreateGuestRoomId(productId: number | string): string {
  const pid = String(productId)
  const map = readMap(GUEST_ROOMS_KEY)
  if (typeof map[pid] === 'string' && /^[A-Za-z0-9_-]{1,100}$/.test(map[pid]))
    return map[pid]

  const id = createStrongId()
  map[pid] = id
  writeMap(GUEST_ROOMS_KEY, map)

  return id
}

/**
 * Recuerda la sala de invitado de un producto (p. ej. la recuperada con el enlace del email)
 * para volver a abrirla en visitas posteriores sin el enlace.
 */
export function rememberGuestRoom(productId: number | string, roomKey: string) {
  const match = String(roomKey).match(/^pub-\d+-viewer-([A-Za-z0-9_-]{1,100})$/)
  if (!match || /^\d+$/.test(match[1]))
    return

  const map = readMap(GUEST_ROOMS_KEY)
  map[String(productId)] = match[1]
  writeMap(GUEST_ROOMS_KEY, map)
}

/**
 * Descarta la sala de invitado guardada para un producto y crea otra (p. ej. si se perdió
 * su token y el servidor ya no deja abrirla: la conversación sigue en el enlace del email).
 */
export function resetGuestRoomId(productId: number | string): string {
  const map = readMap(GUEST_ROOMS_KEY)
  delete map[String(productId)]
  writeMap(GUEST_ROOMS_KEY, map)

  return getOrCreateGuestRoomId(productId)
}

/** Token firmado de una sala de invitado (lo entrega el servidor al crearla o con el enlace del email). */
export function getGuestRoomToken(roomKey: string | null | undefined): string | null {
  if (!roomKey)
    return null
  const token = readMap(GUEST_ROOM_TOKENS_KEY)[roomKey]

  return typeof token === 'string' && token.length ? token : null
}

export function setGuestRoomToken(roomKey: string | null | undefined, token: string | null | undefined) {
  if (!roomKey || !token)
    return
  const map = readMap(GUEST_ROOM_TOKENS_KEY)
  if (map[roomKey] === token)
    return
  map[roomKey] = token
  writeMap(GUEST_ROOM_TOKENS_KEY, map)
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

export function getStoredGuestEmail(storageKey = 'ml_guest_email'): string | null {
  try {
    const existing = localStorage.getItem(storageKey)
    return existing && existing.trim().length ? existing.trim() : null
  }
  catch {
    return null
  }
}

export function setStoredGuestEmail(email: string, storageKey = 'ml_guest_email') {
  try {
    if (email && email.trim().length)
      localStorage.setItem(storageKey, email.trim())
  }
  catch {
    // ignore
  }
}

// Id aleatorio con crypto (antes Math.random, predecible). Solo [a-z0-9]: válido como room_key.
function createStrongId(): string {
  try {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function')
      return `g${crypto.randomUUID().replace(/-/g, '')}`

    const bytes = new Uint8Array(16)
    crypto.getRandomValues(bytes)

    return `g${Array.from(bytes, b => b.toString(16).padStart(2, '0')).join('')}`
  }
  catch {
    const s4 = () => Math.floor((1 + Math.random()) * 0x10000).toString(16).substring(1)

    return `g${Date.now().toString(36)}${s4()}${s4()}${s4()}${s4()}${s4()}${s4()}`
  }
}
