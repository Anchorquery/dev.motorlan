<script setup lang="ts">
interface Doc { title: string; url: string }

defineProps<{ docs?: Doc[] }>()
</script>

<template>
  <VCard class="product-docs" flat>
    <VCardTitle class="docs-title d-flex align-center">
      <VIcon icon="tabler-file-text" color="error" class="mr-2" />
      Documentación adicional
    </VCardTitle>
    <VCardText class="pt-0">
      <VCard
        v-if="docs && docs.length"
        border
        flat
        class="rounded-lg overflow-hidden bg-surface"
      >
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
      <div v-else class="empty-docs">
        <VIcon icon="mdi-file-document-outline" size="28" class="mb-2" />
        <p class="text-body-2 mb-0">No hay documentación adjunta.</p>
      </div>
    </VCardText>
  </VCard>
</template>

<style scoped>
.product-docs {
  width: 100%;
  border: 1px solid #E6E6E6;
  border-radius: 12px;
  height: 100%;
  flex: 1 1 auto;
  background: #ffffff;
  box-shadow: 0 12px 24px rgba(20, 20, 43, 0.06);
}
.docs-title {
  font-weight: 600;
  padding: 24px 24px 16px;
}
.docs-title .v-icon {
  background: rgba(var(--v-theme-error), 0.1);
  border-radius: 8px;
  padding: 8px;
  width: 32px;
  height: 32px;
}
.empty-docs {
  min-height: 120px;
  border-radius: 12px;
  background: #f7f7f9;
  color: #5f6368;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-direction: column;
  text-align: center;
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
