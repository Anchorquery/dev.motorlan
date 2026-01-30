export interface Email {
	id: number
	from: {
		email: string
		name: string
		avatar: string
	}
	to: {
		name: string
		email: string
	}[]
	subject: string
	message: string
	time: string
	isRead: boolean
	isStarred: boolean
	labels: ('personal' | 'company' | 'important' | 'private')[]
	folder: 'inbox' | 'sent' | 'draft' | 'spam' | 'trash'
	isDeleted: boolean
	attachments: {
		fileName: string
		thumbnail: string
		size: string
	}[]
}
