<script setup lang="ts">
definePage({
  meta: {
    public: true,
  },
})

const router = useRouter()

router.push({ name: 'dashboard-purchases-purchases' })
</script>

<template>
  <div></div>
</template>
