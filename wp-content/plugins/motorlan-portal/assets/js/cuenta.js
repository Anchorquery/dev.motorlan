// Mi perfil (UserProfile.vue + ChangePasswordDialog.vue). Lo que va en el navegador:
//  - mlAvatar: la foto se reduce antes de subirla (lado mayor 800 px, JPEG 0,8, como la
//    SPA) y se envía multipart a POST /motorlan/v1/profile (campo avatar).
//  - mlChangePassword: dos pasos contra la API (código de 6 cifras por email). La API
//    vuelve a emitir la cookie al cambiarla, así que se renueva el nonce de la página.
//  - mlEmailConfirm: si el email cambia, la contraseña actual se pide ANTES de enviar
//    (diálogo); el formulario lo guarda el servidor (CuentaController).

import { fetchRest, renewNonce } from './nonce.js'
import { passwordState } from './auth.js'
import { mix } from './chat.js'

const app = () => window.motorlanPortal || {}
const api = (cfg) => String(cfg.apiBase || app().apiBase || '/wp-json/motorlan/v1/').replace(/\/$/, '')
const toast = (text, color = 'success') => { if (window.Alpine) window.Alpine.store('toast').show(text, color) }
const messageOf = (body, fallback) => (body && typeof body.message === 'string' && body.message) || fallback

const MAX_SIDE = 800

/** Imagen → Blob JPEG con el lado mayor ≤ 800 px (resizeImage de la SPA). */
export const resizeImage = (file, maxSide = MAX_SIDE, quality = 0.8) => new Promise((resolve, reject) => {
  const url = URL.createObjectURL(file)
  const img = new Image()
  img.onload = () => {
    URL.revokeObjectURL(url)
    const scale = Math.min(1, maxSide / Math.max(img.width, img.height))
    const canvas = document.createElement('canvas')
    canvas.width = Math.max(1, Math.round(img.width * scale))
    canvas.height = Math.max(1, Math.round(img.height * scale))
    const ctx = canvas.getContext('2d')
    ctx.fillStyle = '#fff' // PNG con transparencia → fondo blanco en JPEG (no negro)
    ctx.fillRect(0, 0, canvas.width, canvas.height)
    ctx.drawImage(img, 0, 0, canvas.width, canvas.height)
    canvas.toBlob((blob) => (blob ? resolve(blob) : reject(new Error('toBlob'))), 'image/jpeg', quality)
  }
  img.onerror = () => { URL.revokeObjectURL(url); reject(new Error('image')) }
  img.src = url
})

// x-data="mlAvatar({ avatar, apiBase, i18n })"
export const mlAvatar = (cfg = {}) => ({
  url: cfg.avatar || '',
  busy: false,
  drag: false,
  i18n: cfg.i18n || {},

  pickFile() { this.$refs.input.click() },
  onChange(e) {
    const file = e.target.files && e.target.files[0]
    e.target.value = ''
    if (file) this.upload(file)
  },
  onDrop(e) {
    this.drag = false
    const file = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0]
    if (file) this.upload(file)
  },

  async upload(file) {
    if (this.busy) return
    if (!/^image\//.test(file.type)) {
      toast(this.i18n.notImage || 'Elige una imagen.', 'error')
      return
    }
    this.busy = true
    try {
      const blob = await resizeImage(file)
      const form = new FormData()
      form.append('avatar', blob, 'avatar.jpg')
      // Sin Content-Type: el navegador pone multipart con su boundary.
      const res = await fetchRest(`${api(cfg)}/profile`, { method: 'POST', headers: { Accept: 'application/json' }, body: form })
      const body = await res.json().catch(() => ({}))
      if (!res.ok || !body.avatar) {
        toast(messageOf(body, this.i18n.avatarError || 'Error al actualizar el avatar'), 'error')
        return
      }
      this.url = String(body.avatar)
      // El avatar del menú de la barra también.
      document.querySelectorAll('[data-ml-user-avatar]').forEach((el) => {
        const img = document.createElement('img')
        img.src = this.url
        img.alt = ''
        const old = el.querySelector('img, .ml-icon')
        if (old) old.replaceWith(img)
        else el.prepend(img)
      })
      toast(this.i18n.avatarUpdated || 'Avatar actualizado correctamente')
    } catch (e) {
      toast(this.i18n.imageError || 'Ha ocurrido un error al procesar la imagen.', 'error')
    } finally {
      this.busy = false
    }
  },
})

// x-data="mlChangePassword({ email, apiBase, i18n })" — diálogo #ml-password.
export const mlChangePassword = (cfg = {}) => mix(passwordState(cfg), {
  step: 1,
  code: '',
  loading: false,
  error: '',

  reset() {
    this.step = 1
    this.password = ''
    this.confirm = ''
    this.code = ''
    this.error = ''
    this.touched = false
    this.loading = false
  },
  open() {
    this.reset()
    window.dispatchEvent(new CustomEvent('ml-dialog', { detail: 'ml-password' }))
  },
  close() { window.dispatchEvent(new CustomEvent('ml-dialog-close', { detail: 'ml-password' })) },

  async post(path, payload) {
    const res = await fetchRest(`${api(cfg)}/${path}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify(payload),
    })
    const body = await res.json().catch(() => ({}))
    return { ok: res.ok, body }
  },

  // Paso 1: la API valida la contraseña nueva (también que no lleve el nombre ni el
  // email) y manda el código.
  async request() {
    this.touched = true
    this.error = ''
    if (!this.password || !this.confirm) { this.error = this.i18n.required || 'Este campo es obligatorio'; return }
    if (!this.valid) { this.error = this.i18n.weak || 'La contraseña no cumple los requisitos'; return }
    if (!this.match) { this.error = this.i18n.mismatch || 'Las contraseñas no coinciden'; return }
    this.loading = true
    try {
      const r = await this.post('profile/change-password-request', { email: cfg.email || '', password: this.password })
      if (!r.ok) { this.error = messageOf(r.body, this.i18n.sendCodeError || 'Error al enviar el código de verificación.'); return }
      this.step = 2
      this.$nextTick(() => { const el = document.getElementById('ml-password-code'); if (el) el.focus() })
    } catch (e) {
      this.error = this.i18n.sendCodeError || 'Error al enviar el código de verificación.'
    } finally {
      this.loading = false
    }
  },

  async verify() {
    this.error = ''
    const code = this.code.replace(/\s+/g, '')
    if (!/^\d{6}$/.test(code)) { this.error = this.i18n.invalidCode || 'El código de verificación es incorrecto o ha expirado.'; return }
    this.loading = true
    try {
      const r = await this.post('profile/change-password-confirm', { password: this.password, code })
      if (!r.ok) { this.error = messageOf(r.body, this.i18n.invalidCode || 'El código de verificación es incorrecto o ha expirado.'); return }
      // Cookie nueva = nonce de la página inválido: uno nuevo antes de la siguiente acción.
      await renewNonce()
      this.password = ''
      this.confirm = ''
      this.code = ''
      this.step = 3
    } catch (e) {
      this.error = this.i18n.invalidCode || 'El código de verificación es incorrecto o ha expirado.'
    } finally {
      this.loading = false
    }
  },
})

// x-data="mlEmailConfirm({ original, open, error, i18n })" en el <form> de los datos.
// Con el email cambiado y sin contraseña, no se envía: se abre el diálogo #ml-email-password.
// (Así no gasta intentos del límite de la API, que cuenta también los envíos sin contraseña.)
export const mlEmailConfirm = (cfg = {}) => ({
  original: String(cfg.original || '').trim().toLowerCase(),
  email: cfg.email || '',
  password: '',
  error: cfg.error || '',
  isCompany: Boolean(cfg.isCompany),
  i18n: cfg.i18n || {},

  init() {
    if (cfg.open) this.$nextTick(() => this.ask())
  },
  get changed() {
    const next = String(this.email || '').trim().toLowerCase()
    return next !== '' && next !== this.original
  },
  ask() { window.dispatchEvent(new CustomEvent('ml-dialog', { detail: 'ml-email-password' })) },
  guard(e) {
    if (this.changed && !this.password) {
      e.preventDefault()
      this.ask()
    }
  },
  confirm() {
    if (!this.password) { this.error = this.i18n.passwordRequired || 'Introduce tu contraseña actual.'; return }
    this.error = ''
    window.dispatchEvent(new CustomEvent('ml-dialog-close', { detail: 'ml-email-password' }))
    this.$refs.form.requestSubmit()
  },
  cancel() {
    this.password = ''
    this.error = ''
    window.dispatchEvent(new CustomEvent('ml-dialog-close', { detail: 'ml-email-password' }))
  },
})
