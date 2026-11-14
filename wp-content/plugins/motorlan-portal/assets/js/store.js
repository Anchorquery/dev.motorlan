// Tienda (views/pages/tienda.twig): solo estado visual del formulario de filtros. La
// búsqueda la hace el servidor (HTMX sobre la misma URL); aquí no se pide nada.
//
//  - Modelo y filtros avanzados se excluyen (TiendaFilters.vue): uno deshabilita a los otros.
//  - Con "Motor AC" no hay PAR; al cambiar el tipo de producto se vacía el PAR.
//  - En móvil los filtros son una hoja inferior (sheet) que abre la barra de abajo.
//  - "Limpiar" vacía todo y vuelve a enviar.
export default (init = {}) => ({
  q: init.q || '',
  type: init.type || '',
  ptype: init.ptype || '',
  brand: init.brand || '',
  state: init.state || '',
  par: init.par || '',
  pot: init.pot || '',
  vel: init.vel || '',
  sheet: false,

  init() {
    this.$watch('sheet', (open) => document.documentElement.classList.toggle('overflow-hidden', open))
  },

  get lockAdv() { return this.type.trim() !== '' },
  get lockType() { return !!(this.ptype || this.brand || this.state || this.par || this.pot || this.vel) },
  // Como la SPA: "Motor AC/DC" cuenta como tipo + tecnología.
  get count() {
    const typeCount = this.ptype.startsWith('motor-') ? 2 : (this.ptype ? 1 : 0)
    return [this.q.trim(), this.type.trim(), this.brand, this.state, this.par, this.pot, this.vel].filter(Boolean).length + typeCount
  },

  onType() {
    this.par = ''
    // El listener del formulario (HTMX) lee el DOM en este mismo evento: se vacía ya.
    if (this.$refs.par) this.$refs.par.value = ''
  },

  async reset() {
    this.q = ''
    this.type = ''
    this.ptype = ''
    this.brand = ''
    this.state = ''
    this.par = ''
    this.pot = ''
    this.vel = ''
    await this.$nextTick()
    const form = document.getElementById('ml-store-form')
    if (form) form.requestSubmit()
  },
})
