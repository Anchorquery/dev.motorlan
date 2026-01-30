import type { ChatContact, ChatContactWithChat, ChatMessage, ChatOut } from '@/plugins/fake-api/handlers/apps/chat/types'
import type { ActiveChat } from './useChat'

export const useChatStore = defineStore('chat', () => {
  const contacts = ref<ChatContact[]>([])
  const chatsContacts = ref<ChatContactWithChat[]>([])
  const profileUser = ref<ChatContact | undefined>()
  const activeChat = ref<ActiveChat>(null)

  const fetchChatsAndContacts = async (q: string) => {
    const { data, error } = await useApi<any>(createUrl('/dashboard/chat/chats-and-contacts', {
      query: { q },
    }))

    if (error.value) {
      console.log(error.value)
    }
    else {
      const { chatsContacts: chats, contacts: conts, profileUser: user } = data.value

      chatsContacts.value = chats
      contacts.value = conts
      profileUser.value = user
    }
  }

  const getChat = async (userId: ChatContact['id']) => {
    const res = await $api(`/dashboard/chat/chats/${userId}`)
    activeChat.value = res
  }

  const sendMsg = async (message: ChatMessage['message']) => {
    const senderId = profileUser.value?.id

    if (!activeChat.value) return

    const response = await $api(`apps/chat/chats/${activeChat.value.contact.id}`, {
      method: 'POST',
      body: { message, senderId },
    })

    const { msg, chat }: { msg: ChatMessage; chat: ChatOut } = response

    if (chat !== undefined) {
      const currentActive = activeChat.value!

      chatsContacts.value.push({
        ...currentActive.contact,
        chat: {
          id: chat.id,
          lastMessage: [] as any,
          unseenMsgs: 0,
          messages: [msg],
        },
      })

      if (activeChat.value) {
        activeChat.value.chat = {
          id: chat.id,
          messages: [msg],
          unseenMsgs: 0,
          userId: activeChat.value.contact.id,
        }
      }
    }
    else {
      activeChat.value?.chat?.messages.push(msg)
    }

    const contact = chatsContacts.value.find((c: ChatContactWithChat) => {
      if (activeChat.value)
        return c.id === activeChat.value.contact.id
      return false
    })

    if (contact) {
      contact.chat.lastMessage = msg
    }
  }

  return {
    contacts,
    chatsContacts,
    profileUser,
    activeChat,
    fetchChatsAndContacts,
    getChat,
    sendMsg,
  }
})
