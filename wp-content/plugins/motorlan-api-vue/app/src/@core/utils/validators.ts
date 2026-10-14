import { isEmpty, isEmptyArray, isNullOrUndefined } from './helpers'
import { getI18n } from '@/plugins/i18n'

// Texto traducido con respaldo en español (antes los mensajes salían en inglés fijo).
// Se resuelve en cada validación, así que sigue el idioma activo.
const vt = (key: string, fallback: string): string => {
  try {
    return getI18n().global.t(key, fallback) as string
  }
  catch {
    return fallback
  }
}

// 👉 Required Validator
export const requiredValidator = (value: unknown) => {
  if (isNullOrUndefined(value) || isEmptyArray(value) || value === false)
    return vt('validators.required', 'Este campo es obligatorio')

  return !!String(value).trim().length || vt('validators.required', 'Este campo es obligatorio')
}

// 👉 Email Validator
export const emailValidator = (value: unknown) => {
  if (isEmpty(value))
    return true

  const re = /^(?:[^<>()[\]\\.,;:\s@"]+(?:\.[^<>()[\]\\.,;:\s@"]+)*|".+")@(?:\[\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\]|(?:[a-z\-\d]+\.)+[a-z]{2,})$/i

  if (Array.isArray(value))
    return value.every(val => re.test(String(val))) || vt('validators.email', 'Introduce un email válido')

  return re.test(String(value)) || vt('validators.email', 'Introduce un email válido')
}

// 👉 Password Validator
// Mismas reglas que el backend (motorlan_validate_password_strength) y el registro:
// cualquier carácter que no sea letra o número cuenta como especial (antes solo
// !@#$%&*() y se rechazaban . - _ que el backend sí acepta).
export const passwordValidator = (password: string) => {
  const p = String(password ?? '')
  const validPassword = p.length >= 8 && /[A-Z]/.test(p) && /[a-z]/.test(p) && /\d/.test(p) && /[^A-Za-z0-9]/.test(p)

  return validPassword || vt('validators.password', 'La contraseña debe tener al menos 8 caracteres, una mayúscula, una minúscula, un número y un carácter especial')
}

// 👉 Confirm Password Validator
export const confirmedValidator = (value: string, target: string) =>

  value === target || vt('validators.confirmed', 'Las contraseñas no coinciden')

// 👉 Between Validator
export const betweenValidator = (value: unknown, min: number, max: number) => {
  const valueAsNumber = Number(value)

  return (Number(min) <= valueAsNumber && Number(max) >= valueAsNumber) || `Enter number between ${min} and ${max}`
}

// 👉 Integer Validator
export const integerValidator = (value: unknown) => {
  if (isEmpty(value))
    return true

  if (Array.isArray(value))
    return value.every(val => /^-?\d+$/.test(String(val))) || 'This field must be an integer'

  return /^-?\d+$/.test(String(value)) || 'This field must be an integer'
}

// 👉 Regex Validator
export const regexValidator = (value: unknown, regex: RegExp | string): string | boolean => {
  if (isEmpty(value))
    return true

  let regeX = regex
  if (typeof regeX === 'string')
    regeX = new RegExp(regeX)

  if (Array.isArray(value))
    return value.every(val => regexValidator(val, regeX))

  return regeX.test(String(value)) || 'The Regex field format is invalid'
}

// 👉 Alpha Validator
export const alphaValidator = (value: unknown) => {
  if (isEmpty(value))
    return true

  return /^[A-Z]*$/i.test(String(value)) || 'The Alpha field may only contain alphabetic characters'
}

// 👉 URL Validator
export const urlValidator = (value: unknown) => {
  if (isEmpty(value))
    return true

  const re = /^https?:\/\/[^\s$.?#].\S*$/

  return re.test(String(value)) || 'URL is invalid'
}

// 👉 Length Validator
export const lengthValidator = (value: unknown, length: number) => {
  if (isEmpty(value))
    return true

  return String(value).length === length || `"The length of the Characters field must be ${length} characters."`
}

// 👉 Alpha-dash Validator
export const alphaDashValidator = (value: unknown) => {
  if (isEmpty(value))
    return true

  const valueAsString = String(value)

  return /^[\w-]*$/.test(valueAsString) || 'All Character are not valid'
}
