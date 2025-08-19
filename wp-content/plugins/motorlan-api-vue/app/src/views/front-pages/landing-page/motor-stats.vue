<script setup lang="ts">
import check from '@images/svg/Check.svg'
import diamond from '@images/svg/Diamond.svg'
import laptop from '@images/svg/laptop.svg'
import user from '@images/svg/user.svg'

const statData = [
  { title: 'Support Tickets Resolved', value: '7.1k+', icon: laptop, color: 'primary' },
  { title: 'Join creatives community', value: '50k+', icon: user, color: 'success' },
  { title: 'Highly Rated Motors', value: '4.8/5', icon: diamond, color: 'info' },
  { title: 'Money Back Guarantee', value: '100%', icon: check, color: 'warning' },
]
</script>

<template>
  <div :style="{ 'background-color': 'rgb(var(--v-theme-surface))' }">
    <VContainer>
      <div class="py-12">
        <VRow>
          <VCol
            v-for="(motor, index) in statData"
            :key="index"
            cols="12"
            sm="6"
            lg="3"
          >
            <VCard
              flat
              :style="{ border: `1px solid rgba(var(--v-theme-${motor.color}))` }"
            >
              <VCardText class="text-center">
                <VIcon
                  :color="motor.color"
                  :icon="motor.icon"
                  size="64"
                  class="mb-4"
                />
                <h3 class="text-h3">
                  {{ motor.value }}
                </h3>
                <p class="text-body-1 font-weight-medium mb-0 text-wrap">
                  {{ motor.title }}
                </p>
              </VCardText>
            </VCard>
          </VCol>
        </VRow>
      </div>
    </VContainer>
  </div>
</template>
