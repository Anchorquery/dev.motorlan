export interface User {
	id: number
	email: string
	name: string
	username?: string
	avatar?: string
	role?: string
	[key: string]: any
}
