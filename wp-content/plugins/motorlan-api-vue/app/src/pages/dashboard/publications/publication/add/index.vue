<script setup lang="ts">
// @ts-nocheck
import { computed } from 'vue'

import { useRoute } from 'vue-router'
import SelectType from './select-type.vue'
import AddForm from './form.vue'

const route = useRoute()
const publicationType = computed(() => {
  const type = route.query.type
  
  return Array.isArray(type) ? type[0] : type
})
</script>

<template>
  <div>
    <AddForm v-if="publicationType" :key="publicationType" />
    <SelectType v-else />
  </div>
</template>
