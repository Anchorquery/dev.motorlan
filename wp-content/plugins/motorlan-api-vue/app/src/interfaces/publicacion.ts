export interface ImageSizes {
  thumbnail: string
  'thumbnail-width': number
  'thumbnail-height': number
  medium: string
  'medium-width': number
  'medium-height': number
  medium_large: string
  'medium_large-width': number
  'medium_large-height': number
  large: string
  'large-width': number
  'large-height': number
  [key: string]: string | number
}

export interface ImagenDestacada {
  ID: number
  id: number
  title: string
  filename: string
  filesize: number
  url: string
  link: string
  alt: string
  author: string
  description: string
  caption: string
  name: string
  status: string
  uploaded_to: number
  date: string
  modified: string
  menu_order: number
  mime_type: string
  type: string
  subtype: string
  icon: string
  width: number
  height: number
  sizes: ImageSizes
}

export interface Publicacion {
  id: number
  title: string
  slug: string
  imagen_destacada: ImagenDestacada | null | any[]
  acf: {
    marca: string
    tipo_o_referencia: string
    precio_de_venta: number | null
    mostrar_precio: string
    [key: string]: any
  }
  marca_name?: string
  status: string
  tipo: { name: string; slug?: string }[]
  author: {
    id: number | string
    name: string
    first_name?: string
    last_name?: string
    // null salvo para el dueño, admin o participantes de una compra
    nickname?: string | null
    user_login?: string | null
    email?: string | null
    avatar?: string
    acf: {
      // null si aún no tiene valoraciones
      calificacion: number | null
      valoraciones?: number
      ventas?: number
    }
  }
}
