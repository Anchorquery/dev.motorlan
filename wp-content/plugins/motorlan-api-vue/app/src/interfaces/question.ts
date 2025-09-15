import type { ImagenDestacada } from './publicacion'

export interface Question {
  id: number
  pregunta: string
  respuesta: string | null
  estado: string
  motor: {
    id: number
    title: string
    uuid: string
    imagen_destacada: ImagenDestacada | null
    acf: {
      marca?: {
        name: string
      }
    }
  }
}