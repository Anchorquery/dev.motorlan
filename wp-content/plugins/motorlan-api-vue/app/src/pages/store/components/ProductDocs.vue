<script setup lang="ts">
interface Doc { title: string; url: string }

defineProps<{ docs?: Doc[] }>()
</script>

<template>
  <VCard
    v-if="docs && docs.length"
    class="product-docs"
    flat
    border
  >
    <VCardItem>
      <VCardTitle>Documentación adicional</VCardTitle>
    </VCardItem>
    <VDivider />
    <VList class="py-0">
      <template
        v-for="(doc, index) in docs"
        :key="doc.url"
      >
        <VListItem
          :href="doc.url"
          target="_blank"
          link
        >
          <template #prepend>
            <VIcon
              icon="mdi-file-document-outline"
              color="secondary"
            />
          </template>
          <VListItemTitle>{{ doc.title }}</VListItemTitle>
        </VListItem>
        <VDivider v-if="index < docs.length - 1" />
      </template>
    </VList>
  </VCard>
</template>

<style scoped>
.product-docs {
  flex: 1 1 300px;
}
</style>
