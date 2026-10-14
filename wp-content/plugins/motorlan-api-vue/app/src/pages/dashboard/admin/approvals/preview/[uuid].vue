<script setup lang="ts">
import { computed, ref, watch, onMounted } from "vue";
import { useRoute, useRouter } from "vue-router";
import ProductImage from "@/pages/store/components/ProductImage.vue";
import ProductDetails from "@/pages/store/components/ProductDetails.vue";
import PublicacionInfo from "@/pages/store/components/PublicacionInfo.vue";
import ProductDocs from "@/pages/store/components/ProductDocs.vue";
import { assertApiOk, useApi } from "@/composables/useApi";
import type { Publicacion } from "@/interfaces/publicacion";

import { useToast } from "@/composables/useToast";
import { useI18n } from "vue-i18n";

const route = useRoute();
const router = useRouter();
const { t } = useI18n();
const { showToast } = useToast();
const uuid = route.params.uuid as string;

const isActionLoading = ref(false);
const isRejectDialogVisible = ref(false);
const rejectReason = ref("");

const { data, isFetching, execute } = useApi<any>(
  `/wp-json/motorlan/v1/publicaciones/uuid/${uuid}`
).get().json();

onMounted(execute);

const publicacion = computed(() => {
  if (!data.value) return undefined;
  return data.value as Publicacion;
});

const docs = computed(() => {
  const raw = publicacion.value?.acf?.documentacion_adicional;
  if (!raw || !Array.isArray(raw)) return [];

  return raw
    .filter((d: any) => d && d.archivo && d.archivo.url)
    .map((d: any) => ({
      title: d.nombre || d.archivo.title || "Documento",
      url: d.archivo.url,
    }));
});

const pendingBrand = computed(() => (publicacion.value as any)?.pending_brand || null)
const editedBrandName = ref('')
const isEditingBrand = ref(false)
const isBrandSaving = ref(false)

watch(pendingBrand, (val) => {
  if (val) editedBrandName.value = val
}, { immediate: true })

const startEditingBrand = () => {
  editedBrandName.value = pendingBrand.value || ''
  isEditingBrand.value = true
}

const saveBrandName = async () => {
  if (!publicacion.value?.id || !editedBrandName.value.trim()) return

  isBrandSaving.value = true
  try {
    assertApiOk(await useApi(`/wp-json/motorlan/v1/admin/update-pending-brand/${publicacion.value.id}`)
      .post({ brand_name: editedBrandName.value.trim() })
      .json())

    showToast(t('admin_approvals.brand_updated', 'Nombre de marca actualizado'), 'success')
    isEditingBrand.value = false
    await execute()
  } catch (error: any) {
    console.error(error)
    showToast(error?.message || t('admin_approvals.brand_update_error', 'Error al actualizar la marca'), 'error')
  } finally {
    isBrandSaving.value = false
  }
}

const title = computed(() => {
  if (!publicacion.value) return "";
  return publicacion.value.title || "";
});

const getInitials = (value: string): string => {
  const parts = value.split(' ').filter(Boolean)
  return parts.slice(0, 2).map(part => part.charAt(0).toUpperCase()).join('') || 'U'
}

const approvePublication = async () => {
  if (!publicacion.value?.id) return;
  
  isActionLoading.value = true;
  try {
    assertApiOk(await useApi(`/wp-json/motorlan/v1/admin/approve-publication/${publicacion.value.id}`).post().json())
    
    showToast(t('admin_approvals.approve_success', 'Publicación aprobada con éxito'), 'success');
    router.push('/dashboard/admin/approvals');
  } catch (error: any) {
    console.error(error);
    showToast(error?.message ? `${t('admin_approvals.approve_error', 'Error al aprobar la publicación')}: ${error.message}` : t('admin_approvals.approve_error', 'Error al aprobar la publicación'), 'error');
  } finally {
    isActionLoading.value = false;
  }
};

const rejectPublication = async () => {
  if (!publicacion.value?.id) return;

  isActionLoading.value = true;
  try {
    assertApiOk(await useApi(`/wp-json/motorlan/v1/admin/reject-publication/${publicacion.value.id}`)
      .post({ reason: rejectReason.value })
      .json())
    
    showToast(t('admin_approvals.reject_success', 'Publicación rechazada'), 'info');
    isRejectDialogVisible.value = false;
    router.push('/dashboard/admin/approvals');
  } catch (error: any) {
    console.error(error);
    showToast(error?.message ? `${t('admin_approvals.reject_error', 'Error al rechazar la publicación')}: ${error.message}` : t('admin_approvals.reject_error', 'Error al rechazar la publicación'), 'error');
  } finally {
    isActionLoading.value = false;
  }
};
</script>

<template>
  <div class="admin-preview-wrapper">
    <!-- Banner de modo preview -->
    <VAlert
      type="info"
      variant="tonal"
      class="mb-6 rounded-xl border-dashed"
      closable
    >
      <template #prepend>
        <VIcon icon="tabler-eye" size="24" class="mr-2" />
      </template>
      <div class="admin-preview-banner d-flex align-center justify-space-between flex-wrap gap-4 w-100">
        <div>
          <span class="font-weight-bold">{{ t('admin_preview.admin_preview_mode') }}</span>
          <p class="text-caption mb-0">{{ t('admin_preview.preview_subtitle') }}</p>
        </div>
        <div class="admin-preview-banner__actions d-flex align-center gap-2">
           <VBtn
            color="success"
            variant="elevated"
            prepend-icon="tabler-check"
            :loading="isActionLoading"
            @click="approvePublication"
          >
            {{ t('admin_preview.approve') }}
          </VBtn>
          <VBtn
            color="error"
            variant="tonal"
            prepend-icon="tabler-x"
            :loading="isActionLoading"
            @click="isRejectDialogVisible = true"
          >
            {{ t('admin_preview.reject') }}
          </VBtn>
          <VBtn
            color="primary"
            variant="tonal"
            prepend-icon="tabler-pencil"
            :to="`/dashboard/publications/publication/edit/${uuid}`"
          >
            {{ t('admin_approvals.edit') }}
          </VBtn>
          <VBtn
            color="default"
            variant="text"
            prepend-icon="tabler-arrow-left"
            @click="router.push('/dashboard/admin/approvals')"
          >
            {{ t('admin_preview.back') }}
          </VBtn>
        </div>
      </div>
    </VAlert>

    <div v-if="publicacion">
      <!-- Aviso de marca nueva pendiente -->
      <VAlert
        v-if="pendingBrand"
        type="warning"
        variant="tonal"
        class="mb-6 rounded-xl"
        prominent
      >
        <template #prepend>
          <VIcon icon="tabler-tag-starred" size="28" />
        </template>
        <div class="w-100">
          <span class="font-weight-bold">{{ t('admin_preview.new_brand_pending') }}</span>

          <div v-if="!isEditingBrand" class="admin-preview-brand__row d-flex align-center gap-2 mt-2">
            <p class="text-body-1 mb-0">
              {{ t('admin_preview.user_requested_brand_pre') }}
              <VChip color="warning" variant="elevated" class="mx-1 font-weight-bold">{{ pendingBrand }}</VChip>.
            </p>
            <VBtn
              variant="text"
              color="warning"
              size="small"
              prepend-icon="tabler-pencil"
              @click="startEditingBrand"
            >
              {{ t('admin_preview.edit_name') }}
            </VBtn>
          </div>

          <div v-else class="admin-preview-brand__editor d-flex align-center gap-3 mt-3">
            <VTextField
              v-model="editedBrandName"
              :label="t('admin_preview.brand_name')"
              variant="outlined"
              density="compact"
              hide-details
              style="max-width: 300px; text-transform: uppercase;"
              @input="editedBrandName = editedBrandName.toUpperCase()"
            />
            <VBtn
              color="success"
              variant="elevated"
              size="small"
              prepend-icon="tabler-check"
              :loading="isBrandSaving"
              :disabled="!editedBrandName.trim()"
              @click="saveBrandName"
            >
              {{ t('admin_preview.save') }}
            </VBtn>
            <VBtn
              variant="text"
              color="default"
              size="small"
              @click="isEditingBrand = false"
            >
              {{ t('admin_preview.cancel') }}
            </VBtn>
          </div>

          <p class="text-caption text-medium-emphasis mt-2 mb-0">
            {{ t('admin_preview.brand_creation_note') }}
          </p>
        </div>
      </VAlert>

      <VRow>
        <VCol cols="12">
          <h1 class="text-h4 mb-4 font-weight-bold">
            {{ title }}
          </h1>
        </VCol>
      </VRow>
      <VRow>
        <VCol cols="12" md="7">
          <ProductImage :publicacion="publicacion" />
        </VCol>
        <VCol cols="12" md="5">
          <ProductDetails :publicacion="publicacion" :is-preview="true" />
          
          <div class="d-flex align-center mt-6 mb-4" v-if="publicacion.author">
            <VAvatar size="48" class="mr-4" :color="publicacion.author.avatar ? undefined : 'primary'">
              <VImg v-if="publicacion.author.avatar" :src="publicacion.author.avatar" alt="Autor" />
              <span v-else class="text-h6 font-weight-bold text-white">
                {{ getInitials(publicacion.author.name || 'V') }}
              </span>
            </VAvatar>
            <div>
              <p class="font-weight-bold mb-0">{{ publicacion.author.name }}</p>
              <p class="text-caption mb-0 text-medium-emphasis">{{ t('admin_preview.publication_requester') }}</p>
            </div>
          </div>
        </VCol>
      </VRow>

      <VRow class="mt-6">
        <VCol cols="12" md="7">
          <PublicacionInfo :publicacion="publicacion" />
        </VCol>
        <VCol cols="12" md="5">
          <ProductDocs :docs="docs" />
        </VCol>
      </VRow>
    </div>

    <div v-else-if="isFetching" class="text-center pa-12">
      <VProgressCircular indeterminate size="64" color="primary" />
    </div>

    <VCard v-else class="pa-12 text-center rounded-xl elevation-2">
      <VIcon icon="tabler-alert-circle" size="64" color="error" class="mb-4" />
      <VCardText class="text-h5">{{ t('admin_preview.publication_not_found') }}</VCardText>
      <p class="text-body-1 text-medium-emphasis">{{ t('admin_preview.not_found_hint') }}</p>
      <VBtn color="primary" class="mt-4" @click="router.push('/dashboard/admin/approvals')">
        {{ t('admin_preview.back_to_list') }}
      </VBtn>
    </VCard>
  </div>

    <!-- 👉 Reject Dialog -->
    <VDialog
      v-model="isRejectDialogVisible"
      max-width="500"
    >
      <VCard class="rounded-xl overflow-hidden elevation-24">
        <VCardTitle class="pa-0">
          <div class="admin-preview-reject__header d-flex align-center gap-2 pa-4 bg-error text-white">
            <VIcon icon="tabler-circle-x" />
            <span class="text-h6 font-weight-bold">{{ t('admin_approvals.reject_dialog.title', 'Rechazar Publicación') }}</span>
          </div>
        </VCardTitle>
        <VCardText class="pa-6 pt-8">
          <p class="text-body-1 mb-4">
            {{ t('admin_approvals.reject_dialog.text', 'Indica el motivo por el cual no se aprueba esta publicación. El usuario recibirá esta información.') }}
          </p>
          <VTextarea
            v-model="rejectReason"
            :label="t('admin_approvals.reject_dialog.reason_label', 'Motivo del rechazo')"
            :placeholder="t('admin_approvals.reject_dialog.reason_placeholder', 'Ej: Faltan especificaciones técnicas en la descripción.')"
            variant="outlined"
            rows="3"
            auto-grow
          />
        </VCardText>
        <VCardActions class="admin-preview-reject__actions pa-6 pt-0">
          <VSpacer />
          <VBtn
            variant="text"
            color="secondary"
            @click="isRejectDialogVisible = false"
          >
            {{ t('admin_approvals.buttons.cancel', 'Cancelar') }}
          </VBtn>
          <VBtn
            color="error"
            variant="elevated"
            :disabled="!rejectReason"
            @click="rejectPublication"
          >
            {{ t('admin_approvals.buttons.confirm_reject', 'Confirmar Rechazo') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

</template>

<style scoped>
.admin-preview-wrapper {
  max-width: 1400px;
  margin: 0 auto;
}

@media (max-width: 959px) {
  .admin-preview-banner,
  .admin-preview-brand__row,
  .admin-preview-brand__editor {
    flex-direction: column;
    align-items: stretch;
  }

  .admin-preview-banner__actions {
    width: 100%;
    flex-direction: column;
    align-items: stretch;
  }

  .admin-preview-banner__actions :deep(.v-btn),
  .admin-preview-brand__row :deep(.v-btn),
  .admin-preview-brand__editor :deep(.v-btn) {
    width: 100%;
  }

  .admin-preview-brand__editor :deep(.v-text-field) {
    max-width: 100% !important;
    width: 100%;
  }

  .admin-preview-reject__header {
    align-items: flex-start;
  }

  .admin-preview-reject__actions {
    flex-direction: column;
    gap: 0.75rem;
  }

  .admin-preview-reject__actions :deep(.v-btn) {
    width: 100%;
  }

  .admin-preview-wrapper :deep(.v-row) {
    row-gap: 1rem;
  }
}
</style>

<route lang="yaml">
meta:
  action: manage
  subject: all
</route>
