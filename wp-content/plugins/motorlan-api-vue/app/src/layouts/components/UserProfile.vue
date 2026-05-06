<script setup lang="ts">
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'
import { useUserStore } from '@/@core/stores/user'

const router = useRouter()
const ability = useAbility()
const userStore = useUserStore()

// Get user data from store
const user = computed(() => userStore.getUser)

const logout = async () => {
  try {
    // Call backend to destroy WordPress session
    await fetch('/wp-json/motorlan/v1/logout', {
      method: 'POST',
      credentials: 'include',
    })
  }
  catch (e) {
    console.error('Error calling logout endpoint:', e)
  }

  // Clear Pinia store state
  userStore.logout()

  // Clear abilities
  ability.update([])

  // Redirect to login with full page reload to clear all state
  window.location.href = '/login'
}

interface ProfileItem {
  type: string
  icon?: string
  title?: string
  to?: { name: string }
  badgeProps?: any
}

const userProfileList: ProfileItem[] = [
  { type: 'divider' },
  { type: 'navItem', icon: 'tabler-user', title: 'Profile', to: { name: 'dashboard-user-profile' } },
  { type: 'navItem', icon: 'tabler-settings', title: 'Settings', to: { name: 'dashboard-user-account' } },
  { type: 'divider' },
]
</script>

<template>
  <IconBtn
    v-if="user"
    id="user-profile-btn"
    class="ms-n1"
  >
    <VBadge
      dot
      bordered
      location="bottom right"
      offset-x="1"
      offset-y="2"
      color="success"
    >
      <VAvatar
        size="38"
        color="primary"
        variant="tonal"
      >
        <VIcon icon="tabler-user" />
      </VAvatar>
    </VBadge>

    <!-- SECTION Menu -->
    <VMenu
      activator="parent"
      width="240"
      location="bottom end"
      offset="12px"
    >
      <VList>
        <VListItem>
          <div class="d-flex gap-2 align-center">
            <VListItemAction>
              <VBadge
                dot
                location="bottom right"
                offset-x="3"
                offset-y="3"
                color="success"
                bordered
              >
                <VAvatar
                  color="primary"
                  variant="tonal"
                >
                  <VIcon icon="tabler-user" />
                </VAvatar>
              </VBadge>
            </VListItemAction>

            <div>
              <h6 class="text-h6 font-weight-medium">
                {{ user.display_name }}
              </h6>
              <VListItemSubtitle class="text-capitalize text-disabled">
                {{ user.isAdmin ? 'Administrador' : 'Usuario' }}
              </VListItemSubtitle>
            </div>
          </div>
        </VListItem>

        <PerfectScrollbar :options="{ wheelPropagation: false }">
          <template
            v-for="item in userProfileList"
            :key="item.title"
          >
            <VListItem
              v-if="item.type === 'navItem'"
              :to="item.to"
            >
              <template #prepend>
                <VIcon
                  :icon="item.icon"
                  size="22"
                />
              </template>

              <VListItemTitle>{{ item.title }}</VListItemTitle>

              <template
                v-if="item.badgeProps"
                #append
              >
                <VBadge
                  rounded="sm"
                  class="me-3"
                  v-bind="item.badgeProps"
                />
              </template>
            </VListItem>

            <VDivider
              v-else
              class="my-2"
            />
          </template>

          <div class="px-4 py-2">
            <VBtn
              block
              size="small"
              color="error"
              append-icon="tabler-logout"
              @click="logout"
            >
              Cerrar sesión
            </VBtn>
          </div>
        </PerfectScrollbar>
      </VList>
    </VMenu>
    <!-- !SECTION -->
  </IconBtn>
</template>
