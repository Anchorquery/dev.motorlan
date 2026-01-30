<script lang="ts" setup>
import type { ChatOut } from '@/plugins/fake-api/handlers/apps/chat/types'
import { useChatStore } from './useChatStore'

const store = useChatStore()

interface MessageGroup {
  senderId: ChatOut['messages'][number]['senderId']
  messages: Omit<ChatOut['messages'][number], 'senderId'>[]
}

const contact = computed(() => ({
  id: store.activeChat?.contact.id,
  avatar: store.activeChat?.contact.avatar,
}))

// Feedback icon
const resolveFeedbackIcon = (feedback: ChatOut['messages'][number]['feedback']) => {
  if (feedback.isSeen)
    return { icon: 'tabler-checks', color: 'success' }
  else if (feedback.isDelivered)
    return { icon: 'tabler-checks', color: undefined }
  else
    return { icon: 'tabler-check', color: undefined }
}

const msgGroups = computed(() => {
  let messages: ChatOut['messages'] = []

  const _msgGroups: MessageGroup[] = []

  if (store.activeChat!.chat) {
    messages = store.activeChat!.chat.messages

    let msgSenderId = messages[0].senderId

    let msgGroup: MessageGroup = {
      senderId: msgSenderId,
      messages: [],
    }

    messages.forEach((msg, index) => {
      if (msgSenderId === msg.senderId) {
        msgGroup.messages.push({
          message: msg.message,
          time: msg.time,
          feedback: msg.feedback,
        })
      }
      else {
        msgSenderId = msg.senderId
        _msgGroups.push(msgGroup)
        msgGroup = {
          senderId: msg.senderId,
          messages: [
            {
              message: msg.message,
              time: msg.time,
              feedback: msg.feedback,
            },
          ],
        }
      }

      if (index === messages.length - 1)
        _msgGroups.push(msgGroup)
    })
  }

  return _msgGroups
})
</script>

<template>
  <div class="chat-log pa-6">
    <div
      v-for="(msgGrp, index) in msgGroups"
      :key="msgGrp.senderId + String(index)"
      class="chat-group d-flex align-start"
      :class="[{
        'flex-row-reverse': msgGrp.senderId !== contact.id,
        'mb-8': msgGroups.length - 1 !== index,
      }]"
    >
      <div
        class="chat-avatar"
        :class="msgGrp.senderId !== contact.id ? 'ms-4' : 'me-4'"
      >
        <VAvatar size="38" class="elevation-2">
          <VImg :src="msgGrp.senderId === contact.id ? contact.avatar : store.profileUser?.avatar" />
        </VAvatar>
      </div>
      <div
        class="chat-body d-inline-flex flex-column"
        :class="msgGrp.senderId !== contact.id ? 'align-end' : 'align-start'"
      >
        <div
          v-for="(msgData, msgIndex) in msgGrp.messages"
          :key="msgData.time"
          class="chat-content py-3 px-5 elevation-1 position-relative"
          style="max-width: 100%; width: fit-content;"
          :style="msgGrp.senderId === contact.id ? 'background-color: rgb(var(--v-theme-surface));' : ''"
          :class="[
            msgGrp.senderId === contact.id ? 'chat-left text-high-emphasis' : 'bg-primary text-white chat-right',
            msgGrp.messages.length - 1 !== msgIndex ? 'mb-2' : 'mb-1',
          ]"
        >
          <p class="mb-0 text-body-1" style="white-space: pre-wrap;">{{ msgData.message }}</p>
        </div>
        <div 
            class="d-flex align-center mt-1"
            :class="{ 'flex-row-reverse': msgGrp.senderId !== contact.id, 'justify-end': msgGrp.senderId !== contact.id }"
        >
          <span 
            class="text-caption text-disabled" 
            :class="msgGrp.senderId !== contact.id ? 'me-2' : 'ms-2'"
            style="font-size: 0.7rem;"
          >
            {{ formatDate(msgGrp.messages[msgGrp.messages.length - 1].time, { hour: 'numeric', minute: 'numeric' }) }}
          </span>
          <VIcon
            v-if="msgGrp.senderId !== contact.id"
            size="16"
            :color="resolveFeedbackIcon(msgGrp.messages[msgGrp.messages.length - 1].feedback).color"
            class="opacity-80"
          >
            {{ resolveFeedbackIcon(msgGrp.messages[msgGrp.messages.length - 1].feedback).icon }}
          </VIcon>
        </div>
      </div>
    </div>
  </div>
</template>

<style lang="scss">
.chat-log {
  .chat-body {
    max-inline-size: 75%;

    .chat-content {
      border-radius: 12px;
      p {
        overflow-wrap: anywhere;
        line-height: 1.5;
      }

      &.chat-left {
        border-start-end-radius: 12px;
        border-end-end-radius: 12px;
        border-start-start-radius: 4px; 
        border-end-start-radius: 12px;
        
        // Premium look tweaks
        &:first-child { border-start-start-radius: 12px; }
        &:last-child { border-end-start-radius: 4px; }
      }

      &.chat-right {
        border-start-start-radius: 12px;
        border-end-start-radius: 12px;
        border-start-end-radius: 12px;
        border-end-end-radius: 4px;

         // Premium look tweaks
        &:first-child { border-start-end-radius: 12px; }
        &:last-child { border-end-end-radius: 4px; }
      }
    }
  }
}
</style>
