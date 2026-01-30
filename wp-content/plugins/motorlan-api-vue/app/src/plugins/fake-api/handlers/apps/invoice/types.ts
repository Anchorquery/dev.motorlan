export interface Invoice {
	id: number
	invoiceStatus: 'Paid' | 'Downloaded' | 'Draft' | 'Sent' | 'Partial Payment' | 'Past Due'
	issuedDate: string
	total: number
	balance: number
	dueDate: string
	avatar: string
	client: {
		name: string
		companyEmail: string
	}
}
