export interface Review {
  id: number
  title: string
  date: string
  status: string
  acf: {
    rating?: number
    [key: string]: any
  }
}
