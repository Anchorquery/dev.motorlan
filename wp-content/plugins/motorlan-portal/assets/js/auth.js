// Pantallas de acceso (views/partials/acceso/*). El envío lo hace HTMX (hx-post a la
// misma URL) y valida el servidor con la API; aquí solo va lo que la SPA hacía en el
// navegador antes de enviar: requisitos de contraseña, pasos del registro y tokens del
// enlace del email (llegan en el fragmento #…, que no viaja al servidor).

// Mismas 5 reglas que register.vue / PasswordRequirements.vue y que
// motorlan_validate_password_strength en la API.
export const passwordChecks = (p = '') => ({
  length: p.length >= 8,
  upper: /[A-Z]/.test(p),
  lower: /[a-z]/.test(p),
  digit: /[0-9]/.test(p),
  special: /[^A-Za-z0-9]/.test(p),
})

const passwordState = (cfg = {}) => ({
  password: '',
  confirm: '',
  touched: false,
  i18n: cfg.i18n || {},
  get checks() { return passwordChecks(this.password) },
  get score() { return Object.values(this.checks).filter(Boolean).length },
  get color() {
    const s = this.score
    if (s <= 2) return 'error'
    if (s === 3) return 'warning'
    if (s === 4) return 'info'
    return 'success'
  },
  get valid() { return this.score === 5 },
  get match() { return this.password.length > 0 && this.password === this.confirm },
})

// Une objetos conservando los getters (el spread `...` los evaluaría una sola vez).
const merge = (...parts) => parts.reduce((o, p) => Object.defineProperties(o, Object.getOwnPropertyDescriptors(p)), {})

// Restablecer contraseña: x-data="mlPassword({ i18n: { rules, mismatch } })" en el <form>.
export const mlPassword = (cfg = {}) => merge(passwordState(cfg), {
  guard(e) {
    if (this.valid && this.match) return
    e.preventDefault()
    this.touched = true
  },
})

// Registro en 3 pasos (register.vue). cfg: { step (0-2), first_name, last_name, email, i18n }
export const mlAuth = (cfg = {}) => merge(passwordState(cfg), {
  step: Math.min(Math.max(cfg.step || 0, 0), 2),
  first_name: cfg.first_name || '',
  last_name: cfg.last_name || '',
  email: cfg.email || '',
  privacy: false,
  clientError: '',
  fieldError: '',
  passwordError: '',
  confirmError: '',

  init() {
    this.$watch('password', () => { this.passwordError = ''; this.clientError = '' })
    this.$watch('confirm', () => { this.confirmError = '' })
    this.$watch('step', () => this.$nextTick(() => {
      const first = this.$el.querySelector('.ml-auth-step:not([style*="display: none"]) input:not([type=hidden])')
      if (first) first.focus()
    }))
  },

  t(key) { return this.i18n[key] || '' },

  next() {
    this.clientError = ''
    if (this.step === 0) {
      this.fieldError = ''
      if (!this.first_name.trim() || !this.last_name.trim() || !this.email.trim()) {
        this.fieldError = this.t('required')
        return
      }
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.email.trim())) {
        this.fieldError = this.t('email')
        return
      }
      this.step = 1
      return
    }
    if (this.step === 1) {
      if (!this.valid) {
        this.clientError = this.t('rules')
        return
      }
      if (!this.match) {
        this.confirmError = this.t('mismatch')
        return
      }
      const lower = this.password.toLowerCase()
      const personal = [this.first_name.trim(), this.email.trim()].filter((v) => v.length >= 3).map((v) => v.toLowerCase())
      if (personal.some((v) => lower.includes(v))) {
        this.passwordError = this.t('personal')
        return
      }
      this.step = 2
    }
  },

  back() { if (this.step > 0) this.step-- },
  // Cabecera del stepper: solo hacia atrás (los pasos siguientes exigen validar).
  go(i) { if (i < this.step) this.step = i },

  guard(e) {
    if (this.step === 2 && this.privacy && this.valid && this.match) return
    e.preventDefault()
    if (this.step === 2 && !this.privacy) this.clientError = this.t('terms')
  },
})

// Lee parámetros del enlace del email en el fragmento (#key=…&login=…) o, en enlaces
// antiguos, en la query; los copia a los <input data-token="nombre [alternativa]"> del
// componente y limpia la URL (como reset-password.vue / verify-email.vue).
//   params: nombres a leer · required: los que deben existir (si no, missing = true)
//   submit: envía el formulario del componente en cuanto tiene los valores
// Con email_change, el <input data-token-mode> pasa a "email_change".
export const mlAuthToken = (cfg = {}) => ({
  missing: false,

  init() {
    const hash = new URLSearchParams(window.location.hash.replace(/^#/, ''))
    const query = new URLSearchParams(window.location.search)
    const read = (name) => hash.get(name) || query.get(name) || ''
    const values = {}
    ;(cfg.params || []).forEach((name) => { values[name] = read(name) })

    if (window.location.search || window.location.hash) {
      window.history.replaceState(window.history.state, '', window.location.pathname)
    }

    this.$el.querySelectorAll('[data-token]').forEach((input) => {
      const names = input.dataset.token.split(/\s+/)
      const found = names.find((n) => values[n])
      if (!found) return
      input.value = values[found]
      if (found === 'email_change') {
        const mode = this.$el.querySelector('[data-token-mode]')
        if (mode) mode.value = 'email_change'
      }
    })

    const filled = (name) => values[name] || (this.$el.querySelector(`[data-token~="${name}"]`)?.value ?? '')
    this.missing = (cfg.required || []).some((name) => !filled(name))

    if (cfg.submit) {
      const form = this.$el.querySelector('form')
      // Tras el arranque de HTMX (DOMContentLoaded), para que el envío vaya por hx-post.
      const go = () => requestAnimationFrame(() => form && form.requestSubmit())
      if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', go, { once: true })
      else go()
    }
  },
})
