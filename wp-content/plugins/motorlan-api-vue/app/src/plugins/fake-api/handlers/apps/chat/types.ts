export type ChatStatus = 'online' | 'busy' | 'away' | 'offline'

export interface ChatContact {
	id: number
	fullName: string
	role: string
	about: string
	avatar: string
	status: ChatStatus
}

export interface ChatMessage {
	message: string
	time: string
	senderId: number
	feedback: {
		isSent: boolean
		isDelivered: boolean
		isSeen: boolean
	}
}

export interface Chat {
	id: number
	userId: number
	unseenMsgs: number
	messages: ChatMessage[]
}

export interface ChatContactWithChat extends ChatContact {
	chat: {
		id: number
		lastMessage: ChatMessage
		unseenMsgs: number
		messages: ChatMessage[]
	}
}

export interface ChatOut {
	id: number
	messages: ChatMessage[]
}
