export interface Favorite {
  id: number
  title: string
  date: string
  status: string
  acf: {
    motor_uuid?: string
    [key: string]: any
  }
}
