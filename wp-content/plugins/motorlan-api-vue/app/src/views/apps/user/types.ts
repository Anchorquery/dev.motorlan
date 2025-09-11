export interface UserParams {
  q: string
  role: string
  plan: string
  status: string
  options: object
}

export interface PersonalData {
  nombre: string
  apellidos: string
  email: string
  telefono: string
  avatar?: string
}

export interface CompanyData {
  nombre: string
  direccion: string
  cp: string
  persona_contacto: string
  email_contacto: string
  tel_contacto: string
  cif_nif: string
}

export interface UserProfile {
  personal_data: PersonalData
  company_data: CompanyData
}
