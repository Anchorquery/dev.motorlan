<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import SelectType from './select-type.vue'
import AddForm from './form.vue'

const route = useRoute()
const publicationType = computed(() => route.query.type)
</script>

<template>
  <div>
    <AddForm v-if="publicationType" :key="publicationType" />
    <SelectType v-else />
  </div>
</template>
