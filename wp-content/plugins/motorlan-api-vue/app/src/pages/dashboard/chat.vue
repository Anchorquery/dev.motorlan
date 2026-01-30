<script lang="ts" setup>
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'
import { useDisplay, useTheme } from 'vuetify'
import type { ChatContact as TypeChatContact } from '@/plugins/fake-api/handlers/apps/chat/types'
import { themes } from '@/plugins/vuetify/theme'
import ChatActiveChatUserProfileSidebarContent from '@/views/apps/chat/ChatActiveChatUserProfileSidebarContent.vue'
import ChatLeftSidebarContent from '@/views/apps/chat/ChatLeftSidebarContent.vue'
import ChatLog from '@/views/apps/chat/ChatLog.vue'
import ChatUserProfileSidebarContent from '@/views/apps/chat/ChatUserProfileSidebarContent.vue'
import { useChat } from '@/views/apps/chat/useChat'
import { useChatStore } from '@/views/apps/chat/useChatStore'

definePage({
  meta: {
    layoutWrapperClasses: 'layout-content-height-fixed',
  },
})

// composables
const vuetifyDisplays = useDisplay()
const store = useChatStore()
const { isLeftSidebarOpen } = useResponsiveLeftSidebar(vuetifyDisplays.smAndDown)
const { resolveAvatarBadgeVariant } = useChat()

// Perfect scrollbar
const chatLogPS = ref()

const scrollToBottomInChatLog = () => {
  const scrollEl = chatLogPS.value.$el || chatLogPS.value

  scrollEl.scrollTop = scrollEl.scrollHeight
}

// Search query
const q = ref('')

watch(
  q,
  val => store.fetchChatsAndContacts(val),
  { immediate: true },
)

// Open Sidebar in smAndDown when "start conversation" is clicked
const startConversation = () => {
  if (vuetifyDisplays.mdAndUp.value)
    return
  isLeftSidebarOpen.value = true
}

// Chat message
const msg = ref('')

const sendMessage = async () => {
  if (!msg.value)
    return

  await store.sendMsg(msg.value)

  // Reset message input
  msg.value = ''

  // Scroll to bottom
  nextTick(() => {
    scrollToBottomInChatLog()
  })
}

const openChatOfContact = async (userId: TypeChatContact['id']) => {
  await store.getChat(userId)

  // Reset message input
  msg.value = ''

  // Set unseenMsgs to 0
  const contact = store.chatsContacts.find(c => c.id === userId)
  if (contact)
    contact.chat.unseenMsgs = 0

  // if smAndDown =>  Close Chat & Contacts left sidebar
  if (vuetifyDisplays.smAndDown.value)
    isLeftSidebarOpen.value = false

  // Scroll to bottom
  nextTick(() => {
    scrollToBottomInChatLog()
  })
}

// User profile sidebar
const isUserProfileSidebarOpen = ref(false)

// Active chat user profile sidebar
const isActiveChatUserProfileSidebarOpen = ref(false)

// file input
const refInputEl = ref<HTMLElement>()

const { name } = useTheme()

const chatContentContainerBg = computed(() => {
  let color = 'transparent'

  if (themes)
    color = themes?.[name.value].colors?.background as string

  return color
})
</script>

<template>
  <VLayout
    class="chat-app-layout bg-surface rounded-xl elevation-5"
    style="z-index: 0; min-height: 70vh;"
  >
    <!-- 👉 user profile sidebar -->
    <VNavigationDrawer
      v-model="isUserProfileSidebarOpen"
      data-allow-mismatch
      temporary
      touchless
      absolute
      class="user-profile-sidebar"
      location="start"
      width="370"
    >
      <ChatUserProfileSidebarContent @close="isUserProfileSidebarOpen = false" />
    </VNavigationDrawer>

    <!-- 👉 Active Chat sidebar -->
    <VNavigationDrawer
      v-model="isActiveChatUserProfileSidebarOpen"
      data-allow-mismatch
      width="374"
      absolute
      temporary
      location="end"
      touchless
      class="active-chat-user-profile-sidebar"
    >
      <ChatActiveChatUserProfileSidebarContent @close="isActiveChatUserProfileSidebarOpen = false" />
    </VNavigationDrawer>

    <!-- 👉 Left sidebar   -->
    <VNavigationDrawer
      v-model="isLeftSidebarOpen"
      data-allow-mismatch
      absolute
      touchless
      location="start"
      width="370"
      :temporary="$vuetify.display.smAndDown"
      class="chat-list-sidebar border-e"
      :permanent="$vuetify.display.mdAndUp"
    >
      <ChatLeftSidebarContent
        v-model:is-drawer-open="isLeftSidebarOpen"
        v-model:search="q"
        @open-chat-of-contact="openChatOfContact"
        @show-user-profile="isUserProfileSidebarOpen = true"
        @close="isLeftSidebarOpen = false"
      />
    </VNavigationDrawer>

    <!-- 👉 Chat content -->
    <VMain class="chat-content-container">
      <!-- 👉 Right content: Active Chat -->
      <div
        v-if="store.activeChat"
        class="d-flex flex-column h-100 position-relative"
      >
        <!-- 👉 Active chat header -->
        <div class="active-chat-header d-flex align-center bg-surface border-b elevation-1 z-index-10 px-6 py-3">
          <!-- Sidebar toggler -->
          <IconBtn
            class="d-md-none me-3"
            variant="text"
            color="default"
            @click="isLeftSidebarOpen = true"
          >
            <VIcon icon="tabler-menu-2" size="24" />
          </IconBtn>

          <!-- avatar -->
          <div
            class="d-flex align-center cursor-pointer flex-grow-1"
            @click="isActiveChatUserProfileSidebarOpen = true"
          >
            <VBadge
              dot
              location="bottom right"
              offset-x="2"
              offset-y="2"
              :color="resolveAvatarBadgeVariant(store.activeChat.contact.status)"
              bordered
              class="active-chat-badge"
            >
              <VAvatar
                size="44"
                :variant="!store.activeChat.contact.avatar ? 'tonal' : undefined"
                :color="!store.activeChat.contact.avatar ? resolveAvatarBadgeVariant(store.activeChat.contact.status) : undefined"
                class="cursor-pointer elevation-1"
              >
                <VImg
                  v-if="store.activeChat.contact.avatar"
                  :src="store.activeChat.contact.avatar"
                  :alt="store.activeChat.contact.fullName"
                />
                <span v-else class="text-h6 font-weight-medium text-uppercase">{{ avatarText(store.activeChat.contact.fullName) }}</span>
              </VAvatar>
            </VBadge>

            <div class="ms-4 overflow-hidden">
              <div class="text-subtitle-1 font-weight-bold mb-n1 text-high-emphasis">
                {{ store.activeChat.contact.fullName }}
              </div>
              <span class="text-caption text-medium-emphasis">
                {{ store.activeChat.contact.role }}
              </span>
            </div>
          </div>
          
          <!-- Header right content -->
          <div class="d-sm-flex align-center d-none gap-2">
            <IconBtn variant="text" color="medium-emphasis">
              <VIcon icon="tabler-phone" />
            </IconBtn>
            <IconBtn variant="text" color="medium-emphasis">
              <VIcon icon="tabler-video" />
            </IconBtn>
            <IconBtn variant="text" color="medium-emphasis">
              <VIcon icon="tabler-search" />
            </IconBtn>
            <IconBtn variant="text" color="medium-emphasis">
              <VIcon icon="tabler-dots-vertical" />
            </IconBtn>
          </div>
        </div>

        <!-- Chat log -->
        <PerfectScrollbar
          ref="chatLogPS"
          tag="ul"
          :options="{ wheelPropagation: false }"
          class="flex-grow-1 chat-log-scrollbar"
        >
          <ChatLog />
        </PerfectScrollbar>

        <!-- Message form -->
        <VForm
          class="chat-log-message-form ma-4 bg-surface elevation-3 rounded-xl overflow-hidden d-flex align-end border"
          @submit.prevent="sendMessage"
        >
            <!-- Attachments -->
            <div class="d-flex align-center gap-1 ps-2 py-2">
                 <IconBtn @click="refInputEl?.click()" variant="text" color="medium-emphasis" size="small">
                  <VIcon
                    icon="tabler-paperclip"
                    size="22"
                  />
                  <VTooltip activator="parent">Adjuntar archivo</VTooltip>
                </IconBtn>
                 <IconBtn variant="text" color="medium-emphasis" size="small">
                    <VIcon
                    icon="tabler-microphone"
                    size="22"
                  />
                   <VTooltip activator="parent">Mensaje de voz</VTooltip>
                </IconBtn>
            </div>

          <VTextarea
            :key="store.activeChat?.contact.id"
            v-model="msg"
            variant="plain"
            density="comfortable"
            class="chat-message-input flex-grow-1 px-2"
            placeholder="Escribe un mensaje..."
            auto-grow
            rows="1"
            max-rows="5"
            hide-details
            autofocus
            @keydown.enter.exact.prevent="sendMessage"
          />

           <div class="pe-2 py-2">
              <VBtn
                icon="tabler-send"
                variant="flat"
                color="primary"
                size="small"
                :disabled="!msg"
                @click="sendMessage"
                class="elevation-2"
              />
           </div>

          <input
            ref="refInputEl"
            type="file"
            name="file"
            accept=".jpeg,.png,.jpg,GIF"
            hidden
          >
        </VForm>
      </div>

      <!-- 👉 Start conversation -->
      <div
        v-else
        class="d-flex h-100 align-center justify-center flex-column bg-surface-container-lowest"
      >
        <div class="bg-surface rounded-circle pa-8 elevation-3 mb-6">
            <VIcon
            size="64"
            color="primary"
            icon="tabler-messages"
            />
        </div>
        
        <h3 class="text-h5 font-weight-bold mb-2 text-high-emphasis">
            ¡Comienza a conectar!
        </h3>
        
        <p class="text-center text-medium-emphasis mb-6" style="max-width: 400px;">
          Selecciona un contacto de la izquierda para ver el historial o iniciar una nueva conversación.
        </p>

        <VBtn
          v-if="$vuetify.display.smAndDown"
          rounded="pill"
          color="primary"
          prepend-icon="tabler-message-2"
          @click="startConversation"
          class="elevation-2"
        >
          Ver Contactos
        </VBtn>
      </div>
    </VMain>
  </VLayout>
</template>

<style lang="scss">
@use "@styles/variables/vuetify";
@use "@core/scss/base/mixins";
@use "@layouts/styles/mixins" as layoutsMixins;

// Variables
$chat-app-header-height: 72px;

.chat-app-layout {
  border-radius: vuetify.$card-border-radius;
  @include mixins.elevation(vuetify.$card-elevation);

  $sel-chat-app-layout: &;

  @at-root {
    .skin--bordered {
      @include mixins.bordered-skin($sel-chat-app-layout);
    }
  }

  .active-chat-user-profile-sidebar,
  .user-profile-sidebar {
    .v-navigation-drawer__content {
      display: flex;
      flex-direction: column;
    }
  }

  .chat-list-sidebar {
    .v-navigation-drawer__content {
      display: flex;
      flex-direction: column;
    }
  }
}

.chat-content-container {
  /* stylelint-disable-next-line value-keyword-case */
  background-color: transparent; // Let the background behind shine or use surface-container

  // Adjust the padding so text field height stays 48px
  .chat-message-input {
    .v-field__input {
      font-size: 0.9375rem !important;
      line-height: 1.5 !important;
      padding-block: 0.75rem !important; // Adjust vertical centering
    }
    textarea {
        max-height: 150px;
        overflow-y: auto !important;
    }
  }
}

.chat-log-scrollbar {
    background-color: rgba(var(--v-theme-surface-variant), 0.05); // Subtle background for chat area
}

.active-chat-badge {
    .v-badge__badge {
         border: 2px solid rgb(var(--v-theme-surface));
         height: 12px;
         width: 12px;
         border-radius: 50%;
    }
}
</style>
