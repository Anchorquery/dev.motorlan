<script setup lang="ts">
interface Doc { title: string; url: string }

defineProps<{ docs?: Doc[] }>()
</script>

<template>
  <div class="product-docs">
    <h3 class="mb-4">
      Documentación adicional
    </h3>
    <ul v-if="docs && docs.length">
      <li
        v-for="doc in docs"
        :key="doc.url"
        class="d-flex align-center gap-2"
      >
        <VIcon
          icon="mdi-file-document-outline"
          color="error"
        />
        <a
          :href="doc.url"
          target="_blank"
        >{{ doc.title }}</a>
      </li>
    </ul>
    <p
      v-else
      class="text-body-2 text-medium-emphasis"
    >
      No se adjuntó documentación adicional
    </p>
  </div>
</template>

<style scoped>
.product-docs {
  flex: 1 1 300px;
}

.product-docs ul {
  list-style: none;
  padding: 0;
}
.product-docs li + li {
  margin-top: 8px;
}
</style>
