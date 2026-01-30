<script lang="ts" setup>
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'
import type { ChatContact as TypeChatContact } from '@/plugins/fake-api/handlers/apps/chat/types'
import { useChat } from './useChat'
import ChatContact from './ChatContact.vue'
import { useChatStore } from './useChatStore'

const props = defineProps<{
  search: string
  isDrawerOpen: boolean
}>()

const emit = defineEmits<{
  (e: 'openChatOfContact', id: TypeChatContact['id']): void
  (e: 'showUserProfile'): void
  (e: 'close'): void
  (e: 'update:search', value: string): void
}>()

const { resolveAvatarBadgeVariant } = useChat()
const search = useVModel(props, 'search', emit)

const store = useChatStore()
</script>

<template>
  <!-- 👉 Chat list header -->
  <div
    v-if="store.profileUser"
    class="chat-list-header d-flex align-center px-4 py-3 bg-surface border-b"
  >
    <VBadge
      dot
      location="bottom right"
      offset-x="2"
      offset-y="2"
      :color="resolveAvatarBadgeVariant(store.profileUser.status)"
      bordered
      class="me-3"
    >
      <VAvatar
        size="44"
        class="cursor-pointer elevation-1"
        @click="$emit('showUserProfile')"
      >
        <VImg
          :src="store.profileUser.avatar"
          alt="John Doe"
        />
      </VAvatar>
    </VBadge>

    <VTextField
      id="search"
      v-model="search"
      placeholder="Buscar..."
      prepend-inner-icon="tabler-search"
      density="compact"
      variant="outlined"
      class="chat-list-search flex-grow-1"
      hide-details
      rounded="pill"
      bg-color="surface"
    />

    <IconBtn
      v-if="$vuetify.display.smAndDown"
      @click="$emit('close')"
      class="ms-2"
    >
      <VIcon
        icon="tabler-x"
        class="text-medium-emphasis"
      />
    </IconBtn>
  </div>
  
  <PerfectScrollbar
    tag="ul"
    class="d-flex flex-column gap-y-1 chat-contacts-list px-3 py-3 list-none flex-grow-1"
    :options="{ wheelPropagation: false }"
  >
    <li class="list-none mb-2 mt-1">
      <span class="chat-contact-header text-uppercase text-caption font-weight-bold text-medium-emphasis ps-4">
        Chats Recientes
      </span>
    </li>

    <ChatContact
      v-for="contact in store.chatsContacts"
      :key="`chat-${contact.id}`"
      :user="contact"
      is-chat-contact
      class="mb-1 rounded-lg"
      @click="$emit('openChatOfContact', contact.id)"
    />

    <div
      v-show="!store.chatsContacts.length"
      class="no-chat-items-text text-disabled text-center py-4 text-caption"
    >
      No hay chats recientes
    </div>

    <li class="list-none mt-4 mb-2">
      <span class="chat-contact-header text-uppercase text-caption font-weight-bold text-medium-emphasis ps-4">
        Contactos
      </span>
    </li>

    <ChatContact
      v-for="contact in store.contacts"
      :key="`chat-${contact.id}`"
      :user="contact"
      class="mb-1 rounded-lg"
      @click="$emit('openChatOfContact', contact.id)"
    />

    <div
      v-show="!store.contacts.length"
      class="no-chat-items-text text-disabled text-center py-4 text-caption"
    >
      No hay contactos encontrados
    </div>
  </PerfectScrollbar>
</template>

<style lang="scss">
.chat-contacts-list {
  --chat-content-spacing-x: 12px;

  .chat-contact-header {
    letter-spacing: 0.08em;
  }
}

.chat-list-search {
  .v-field__outline__start,
  .v-field__outline__end {
    border-color: rgba(var(--v-border-color), var(--v-border-opacity)) !important;
  }
}
</style>
