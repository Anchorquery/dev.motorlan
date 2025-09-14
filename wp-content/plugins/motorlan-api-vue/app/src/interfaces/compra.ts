import type { Publicacion } from '@/interfaces/publicacion'
import type { User } from '@/interfaces/user'

export interface Compra {
  id: number
  title: string
  uuid: string
  status: string
  acf: {
    usuario: User
    motor: Publicacion
    fecha_compra: string
    vendedor: User
    precio_compra: number
    estado: 'pending' | 'completed' | 'cancelled'
    [key: string]: any
  }
}