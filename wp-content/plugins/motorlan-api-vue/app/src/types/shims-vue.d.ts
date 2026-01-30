import { ComponentCustomProperties } from 'vue'

declare module '@vue/runtime-core' {
	interface ComponentCustomProperties {
		// Vuetify
		$vuetify: {
			display: {
				xs: boolean
				smAndUp: boolean
				smAndDown: boolean
				mdAndUp: boolean
				mdAndDown: boolean
				lgAndUp: boolean
				width: number
			}
			locale: {
				isRtl: boolean
			}
			theme: {
				current: {
					dark: boolean
				}
			}
		}

		// Ability (CASL)
		$can: (action: string, subject: string) => boolean

		// Global Utils
		avatarText: (value: string) => string
		kFormatter: (num: number) => string | number
		formatCurrency: (value: number) => string
		formatDate: (value: string, options?: unknown) => string
		formatDateToMonthShort: (value: string) => string
		resolveUserRoleVariant: (role: string) => { color: string; icon: string }

		// Validators
		requiredValidator: (value: unknown) => string | boolean
		emailValidator: (value: unknown) => string | boolean
	}
}

declare module 'vue-router' {
	interface RouteMeta {
		action?: string
		subject?: string
		layoutWrapperClasses?: string
		navActiveLink?: string
	}
}

declare global {
	interface Window {
		wpData?: {
			login_endpoint?: string
			rest_nonce?: string
			nonce?: string
			[key: string]: any
		}
	}
}

export { }
