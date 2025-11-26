<script setup lang="ts">
interface Doc { title: string; url: string }

defineProps<{ docs?: Doc[] }>()
</script>

<template>
  <div
    v-if="docs && docs.length"
    class="product-docs h-100"
  >
    <VDivider class="my-4" />
    <h3 class="mb-4">Documentación adicional</h3>
    
    <VCard border flat class="rounded-lg overflow-hidden bg-surface">
      <VList lines="one" density="comfortable" class="py-0">
        <template v-for="(doc, index) in docs" :key="doc.url">
          <VListItem
            :href="doc.url"
            target="_blank"
            rel="noopener noreferrer"
            link
            class="doc-item py-3"
          >
            <template #prepend>
              <VAvatar color="primary" variant="tonal" rounded class="mr-3">
                <VIcon icon="mdi-file-document-outline" size="20" />
              </VAvatar>
            </template>
            
            <VListItemTitle class="font-weight-medium text-body-2">
              {{ doc.title }}
            </VListItemTitle>
            
            <template #append>
              <VIcon 
                icon="mdi-open-in-new" 
                size="18" 
                color="medium-emphasis" 
                class="doc-action-icon"
              />
            </template>
          </VListItem>
          <VDivider v-if="index < docs.length - 1" />
        </template>
      </VList>
    </VCard>
  </div>
</template>

<style scoped>
.product-docs {
  width: 100%;
}

.doc-item {
  transition: background-color 0.2s ease;
}

.doc-item:hover {
  background-color: rgba(var(--v-theme-primary), 0.05);
}

.doc-item:hover .doc-action-icon {
  color: rgb(var(--v-theme-primary)) !important;
}
</style>
