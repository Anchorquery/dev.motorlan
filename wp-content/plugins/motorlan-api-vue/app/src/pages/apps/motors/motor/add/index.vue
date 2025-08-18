<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()
const motorId = Number(route.params.id)

const motorData = ref({
  name: '',
  sku: '',
  barcode: '',
  description: '',
  price: null,
  discountedPrice: null,
  taxable: true,
  inStock: true,
  category: '',
  status: 'Published',
  tags: '',
})

if (motorId) {
  // Fetch motor data for editing
  useApi(`/wp-json/wp/v2/motors/${motorId}`).then(response => {
    motorData.value = response.data.value
  })
}

const publishMotor = async () => {
  const api = useApi()
  const url = motorId ? `/wp-json/wp/v2/motors/${motorId}` : '/wp-json/wp/v2/motors'
  const method = motorId ? 'PUT' : 'POST'

  try {
    await api(url, {
      method,
      body: motorData.value,
    })
    router.push('/apps/motors/motor/list')
  }
  catch (error) {
    console.error('Failed to publish motor:', error)
  }
}

const content = ref(
  `<p>
    Keep your account secure with authentication step.
    </p>`)
</script>

<template>
  <div>
    <div class="d-flex flex-wrap justify-start justify-sm-space-between gap-y-4 gap-x-6 mb-6">
      <div class="d-flex flex-column justify-center">
        <h4 class="text-h4 font-weight-medium">
          {{ motorId ? 'Edit' : 'Add' }} a new motor
        </h4>
        <div class="text-body-1">
          Orders placed across your store
        </div>
      </div>

      <div class="d-flex gap-4 align-center flex-wrap">
        <VBtn
          variant="tonal"
          color="secondary"
          @click="router.push('/apps/motors/motor/list')"
        >
          Discard
        </VBtn>
        <VBtn
          variant="tonal"
          color="primary"
        >
          Save Draft
        </VBtn>
        <VBtn @click="publishMotor">
          {{ motorId ? 'Update' : 'Publish' }} Motor
        </VBtn>
      </div>
    </div>

    <VRow>
      <VCol md="8">
        <!-- 👉 Motor Information -->
        <VCard
          class="mb-6"
          title="Motor Information"
        >
          <VCardText>
            <VRow>
              <VCol cols="12">
                <AppTextField
                  v-model="motorData.name"
                  label="Name"
                  placeholder="Motor Name"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="motorData.sku"
                  label="SKU"
                  placeholder="FXSK123U"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="motorData.barcode"
                  label="Barcode"
                  placeholder="0123-4567"
                />
              </VCol>
              <VCol>
                <span class="mb-1">Description (optional)</span>
                <MotorDescriptionEditor
                  v-model="motorData.description"
                  placeholder="Motor Description"
                  class="border rounded"
                />
              </VCol>
            </VRow>
          </VCardText>
        </VCard>

        <!-- 👉 Media -->
        <VCard class="mb-6">
          <VCardItem>
            <template #title>
              Motor Image
            </template>
            <template #append>
              <span class="text-primary font-weight-medium text-sm cursor-pointer">Add Media from URL</span>
            </template>
          </VCardItem>

          <VCardText>
            <DropZone />
          </VCardText>
        </VCard>
      </VCol>

      <VCol
        md="4"
        cols="12"
      >
        <!-- 👉 Pricing -->
        <VCard
          title="Pricing"
          class="mb-6"
        >
          <VCardText>
            <AppTextField
              v-model="motorData.price"
              label="Best Price"
              placeholder="Price"
              class="mb-6"
            />
            <AppTextField
              v-model="motorData.discountedPrice"
              label="Discounted Price"
              placeholder="$499"
              class="mb-6"
            />

            <VCheckbox
              v-model="motorData.taxable"
              label="Charge Tax on this motor"
            />

            <VDivider class="my-2" />

            <div class="d-flex flex-raw align-center justify-space-between ">
              <span>In stock</span>
              <VSwitch
                v-model="motorData.inStock"
                density="compact"
              />
            </div>
          </VCardText>
        </VCard>

        <!-- 👉 Organize -->
        <VCard title="Organize">
          <VCardText>
            <div class="d-flex flex-column gap-y-4">
              <AppSelect
                v-model="motorData.category"
                placeholder="Select Category"
                label="Category"
                :items="['Category 1', 'Category 2', 'Category 3']"
              />
              <AppSelect
                v-model="motorData.status"
                placeholder="Select Status"
                label="Status"
                :items="['Published', 'Inactive', 'Scheduled']"
              />
              <AppTextField
                v-model="motorData.tags"
                label="Tags"
                placeholder="Electric, Powerful, etc"
              />
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>

<style lang="scss" scoped>
  .drop-zone {
    border: 2px dashed rgba(var(--v-theme-on-surface), 0.12);
    border-radius: 6px;
  }
</style>

<style lang="scss">
.inventory-card {
  .v-tabs.v-tabs-pill {
    .v-slide-group-item--active.v-tab--selected.text-primary {
      h6 {
        color: #fff !important;
      }
    }
  }

  .v-radio-group,
  .v-checkbox {
    .v-selection-control {
      align-items: start !important;
    }

    .v-label.custom-input {
      border: none !important;
    }
  }
}

.ProseMirror {
  p {
    margin-block-end: 0;
  }

  padding: 0.5rem;
  outline: none;

  p.is-editor-empty:first-child::before {
    block-size: 0;
    color: #adb5bd;
    content: attr(data-placeholder);
    float: inline-start;
    pointer-events: none;
  }
}
</style>
