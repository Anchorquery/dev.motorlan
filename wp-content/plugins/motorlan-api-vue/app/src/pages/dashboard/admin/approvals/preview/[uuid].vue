<script setup lang="ts">
import { computed, ref, onMounted } from "vue";
import { useRoute, useRouter } from "vue-router";
import ProductImage from "@/pages/store/components/ProductImage.vue";
import ProductDetails from "@/pages/store/components/ProductDetails.vue";
import PublicacionInfo from "@/pages/store/components/PublicacionInfo.vue";
import ProductDocs from "@/pages/store/components/ProductDocs.vue";
import { useApi } from "@/composables/useApi";
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

const title = computed(() => {
  if (!publicacion.value) return "";

  const tipo = publicacion.value.tipo && publicacion.value.tipo.length > 0 ? publicacion.value.tipo[0].name : '';
  const marca = (publicacion.value as any).marca_name || '';
  const modelo = publicacion.value.acf.tipo_o_referencia || '';

  let powerOrTorque = '';
  if (publicacion.value.acf.potencia) {
      powerOrTorque = `${publicacion.value.acf.potencia} kW`;
  } else if (publicacion.value.acf.par_nominal) {
      powerOrTorque = `${publicacion.value.acf.par_nominal} Nm`;
  }

  const velocidad = publicacion.value.acf.velocidad
      ? `${publicacion.value.acf.velocidad} rpm`
      : '';

  const parts = [tipo, marca, modelo, powerOrTorque, velocidad].filter(p => !!p && String(p).trim() !== '');
  return parts.join(' ').toUpperCase();
});

const getInitials = (value: string): string => {
  const parts = value.split(' ').filter(Boolean)
  return parts.slice(0, 2).map(part => part.charAt(0).toUpperCase()).join('') || 'U'
}

const approvePublication = async () => {
  if (!publicacion.value?.id) return;
  
  isActionLoading.value = true;
  try {
    const { error } = await useApi(`/wp-json/motorlan/v1/admin/approve-publication/${publicacion.value.id}`).post().json();
    if (error.value) throw error.value;
    
    showToast(t('admin_approvals.approve_success', 'Publicación aprobada con éxito'), 'success');
    router.push('/dashboard/admin/approvals');
  } catch (error) {
    console.error(error);
    showToast(t('admin_approvals.approve_error', 'Error al aprobar la publicación'), 'error');
  } finally {
    isActionLoading.value = false;
  }
};

const rejectPublication = async () => {
  if (!publicacion.value?.id) return;

  isActionLoading.value = true;
  try {
    const { error } = await useApi(`/wp-json/motorlan/v1/admin/reject-publication/${publicacion.value.id}`)
      .post({ reason: rejectReason.value })
      .json();
    
    if (error.value) throw error.value;
    
    showToast(t('admin_approvals.reject_success', 'Publicación rechazada'), 'info');
    isRejectDialogVisible.value = false;
    router.push('/dashboard/admin/approvals');
  } catch (error) {
    console.error(error);
    showToast(t('admin_approvals.reject_error', 'Error al rechazar la publicación'), 'error');
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
      <div class="d-flex align-center justify-space-between flex-wrap gap-4 w-100">
        <div>
          <span class="font-weight-bold">Modo Previsualización de Administrador</span>
          <p class="text-caption mb-0">Esta publicación aún no es pública. Así es como se verá una vez aprobada.</p>
        </div>
        <div class="d-flex align-center gap-2">
           <VBtn
            color="success"
            variant="elevated"
            prepend-icon="tabler-check"
            :loading="isActionLoading"
            @click="approvePublication"
          >
            Aprobar
          </VBtn>
          <VBtn
            color="error"
            variant="tonal"
            prepend-icon="tabler-x"
            :loading="isActionLoading"
            @click="isRejectDialogVisible = true"
          >
            Rechazar
          </VBtn>
          <VBtn
            color="primary"
            variant="tonal"
            prepend-icon="tabler-pencil"
            :to="`/dashboard/publications/publication/edit/${uuid}`"
          >
            Editar
          </VBtn>
          <VBtn
            color="default"
            variant="text"
            prepend-icon="tabler-arrow-left"
            @click="router.push('/dashboard/admin/approvals')"
          >
            Volver
          </VBtn>
        </div>
      </div>
    </VAlert>

    <div v-if="publicacion">
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
              <p class="text-caption mb-0 text-medium-emphasis">Solicitante de la publicación</p>
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
      <VCardText class="text-h5">Publicación no encontrada</VCardText>
      <p class="text-body-1 text-medium-emphasis">Es posible que el ID no sea válido o la publicación haya sido eliminada.</p>
      <VBtn color="primary" class="mt-4" @click="router.push('/dashboard/admin/approvals')">
        Regresar al listado
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
          <div class="d-flex align-center gap-2 pa-4 bg-error text-white">
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
        <VCardActions class="pa-6 pt-0">
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
</style>

<route lang="yaml">
meta:
  action: manage
  subject: all
</route>
