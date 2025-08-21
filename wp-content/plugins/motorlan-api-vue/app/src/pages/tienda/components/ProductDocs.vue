<script setup lang="ts">
interface Doc { title: string; url: string }

defineProps<{ docs?: Doc[] }>()
</script>

<template>
  <div
    v-if="docs && docs.length"
    class="product-docs"
  >
    <h3 class="text-error mb-4">
      Documentación adicional
    </h3>
    <ul>
      <li
        v-for="doc in docs"
        :key="doc.url"
      >
        <a
          :href="doc.url"
          target="_blank"
        >{{ doc.title }}</a>
      </li>
    </ul>
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
