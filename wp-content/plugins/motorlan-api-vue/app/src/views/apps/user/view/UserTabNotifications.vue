<script lang="ts" setup>
const notifications = ref([
  {
    type: 'New for you',
    email: true,
    browser: false,
    app: false,
  },
  {
    type: 'Account activity',
    email: false,
    browser: true,
    app: true,
  },
  {
    type: 'A new browser used to sign in',
    email: true,
    browser: true,
    app: true,
  },
  {
    type: 'A new device is linked',
    email: false,
    browser: true,
    app: false,
  },
])
</script>

<template>
  <VCard
    class="user-tab-notification"
    title="Notifications"
    subtitle="You will receive notification for the below selected items."
  >
    <VCardText class="px-0 user-tab-notification__body">
      <VDivider />
      <VTable class="text-no-wrap user-tab-notification__table">
        <thead>
          <tr>
            <th scope="col">
              TYPE
            </th>
            <th scope="col">
              EMAIL
            </th>
            <th scope="col">
              BROWSER
            </th>
            <th scope="col">
              APP
            </th>
          </tr>
        </thead>

        <tbody>
          <tr
            v-for="notification in notifications"
            :key="notification.type"
          >
            <td class="text-high-emphasis">
              {{ notification.type }}
            </td>
            <td>
              <VCheckbox v-model="notification.email" />
            </td>
            <td>
              <VCheckbox v-model="notification.browser" />
            </td>
            <td>
              <VCheckbox v-model="notification.app" />
            </td>
          </tr>
        </tbody>
      </VTable>
      <VDivider />
    </VCardText>

    <VCardText class="d-flex flex-wrap gap-4">
      <VBtn>Save changes</VBtn>
      <VBtn
        color="secondary"
        variant="tonal"
      >
        Discard
      </VBtn>
    </VCardText>
  </VCard>
</template>

<style scoped>
.user-tab-notification__body {
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
}

.user-tab-notification__table {
  min-width: 100%;
}

@media (max-width: 599px) {
  .user-tab-notification__table :deep(th),
  .user-tab-notification__table :deep(td) {
    padding-block: 0.75rem;
    padding-inline: 0.75rem;
  }
}
</style>
