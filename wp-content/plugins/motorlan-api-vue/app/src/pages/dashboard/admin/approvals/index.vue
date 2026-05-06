<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useApi } from '@/composables/useApi'
import { useToast } from '@/composables/useToast'

const { t } = useI18n()
const router = useRouter()
const { showToast } = useToast()

const headers = [
  { title: t('publication_list.publication'), value: 'publicacion' },
  { title: t('register.user'), value: 'user' },
  { title: t('publication_list.reference'), value: 'referencia' },
  { title: t('publication_list.price'), value: 'precio' },
  { title: t('publication_list.actions'), value: 'actions', sortable: false },
]

const { data: pendingData, execute: fetchPending, isFetching: isTableLoading } = useApi<any>('/wp-json/motorlan/v1/admin/pending-publications').get().json()

const publications = computed(() => pendingData.value || [])

const isActionLoading = ref(false)
const isRejectDialogVisible = ref(false)
const publicationToReject = ref<number | null>(null)
const rejectReason = ref('')

const openRejectDialog = (id: number) => {
  publicationToReject.value = id
  rejectReason.value = ''
  isRejectDialogVisible.value = true
}

const approvePublication = async (id: number) => {
  isActionLoading.value = true
  try {
    const { error } = await useApi(`/wp-json/motorlan/v1/admin/approve-publication/${id}`).post().json()
    if (error.value) throw error.value
    
    showToast(t('admin_approvals.approve_success', 'Publicación aprobada con éxito'), 'success')
    await fetchPending()
  } catch (error) {
    console.error(error)
    showToast(t('admin_approvals.approve_error', 'Error al aprobar la publicación'), 'error')
  } finally {
    isActionLoading.value = false
  }
}

const rejectPublication = async () => {
  if (!publicationToReject.value) return

  isActionLoading.value = true
  try {
    const { error } = await useApi(`/wp-json/motorlan/v1/admin/reject-publication/${publicationToReject.value}`)
      .post({ reason: rejectReason.value })
      .json()
    
    if (error.value) throw error.value
    
    showToast(t('admin_approvals.reject_success', 'Publicación rechazada'), 'info')
    isRejectDialogVisible.value = false
    await fetchPending()
  } catch (error) {
    console.error(error)
    showToast(t('admin_approvals.reject_error', 'Error al rechazar la publicación'), 'error')
  } finally {
    isActionLoading.value = false
  }
}

const getImageBySize = (image: any, size = 'thumbnail'): string => {
  if (!image) return ''
  if (Array.isArray(image) && image.length > 0) image = image[0]
  
  if (image.sizes && image.sizes[size]) return image.sizes[size]
  return image.url || ''
}

onMounted(() => {
  fetchPending()
})
</script>

<template>
  <div>
    <VCard class="motor-card-enhanced overflow-visible">
      <VCardTitle class="pa-6 pb-0">
        <div class="d-flex align-center gap-3">
          <VAvatar size="40" color="primary" variant="tonal">
            <VIcon icon="tabler-shield-check" />
          </VAvatar>
          <span class="text-h5 text-premium-title">{{ t('admin_approvals.title', 'Aprobaciones Pendientes') }}</span>
        </div>
        <p class="text-body-2 text-medium-emphasis mt-1 ms-13">
          {{ t('admin_approvals.subtitle', 'Revisa y gestiona las solicitudes de publicación de los usuarios.') }}
        </p>
      </VCardTitle>

      <VDivider class="mt-4" />

      <VDataTable
        :headers="headers"
        :items="publications"
        :loading="isTableLoading"
        class="text-no-wrap px-6 pb-6"
        item-value="id"
      >
        <!-- publicacion  -->
        <template #item.publicacion="{ item }">
          <div class="d-flex align-center gap-3 py-3">
            <VAvatar
              size="48"
              variant="tonal"
              rounded
              class="border"
              :image="getImageBySize((item as any).imagen_destacada)"
            />
            <div class="d-flex flex-column">
              <span class="text-body-1 font-weight-bold text-high-emphasis">{{ (item as any).title }}</span>
              <span class="text-caption text-medium-emphasis">{{ (item as any).acf?.marca?.name }}</span>
            </div>
          </div>
        </template>

        <!-- user -->
        <template #item.user="{ item }">
          <div class="d-flex flex-column">
            <span class="text-body-2 font-weight-medium text-high-emphasis">{{ (item as any).author_info?.name }}</span>
            <span class="text-caption text-medium-emphasis">{{ (item as any).author_info?.email }}</span>
          </div>
        </template>

        <!-- referencia -->
        <template #item.referencia="{ item }">
          <span class="text-body-2 text-medium-emphasis">{{ (item as any).acf?.tipo_o_referencia }}</span>
        </template>

        <!-- precio -->
        <template #item.precio="{ item }">
          <span class="text-body-1 text-primary font-weight-bold">{{ (item as any).acf?.precio_de_venta }}€</span>
        </template>

        <!-- Actions -->
        <template #item.actions="{ item }">
            <div class="d-flex gap-2">
            <VBtn
              color="success"
              variant="tonal"
              size="small"
              prepend-icon="tabler-check"
              :loading="isActionLoading"
              @click="approvePublication((item as any).id)"
            >
              {{ t('admin_approvals.buttons.approve', 'Aprobar') }}
            </VBtn>
            <VBtn
              color="error"
              variant="tonal"
              size="small"
              prepend-icon="tabler-x"
              :loading="isActionLoading"
              @click="openRejectDialog((item as any).id)"
            >
              {{ t('admin_approvals.buttons.reject', 'Rechazar') }}
            </VBtn>

            <IconBtn
              color="primary"
              variant="text"
              size="small"
              @click="router.push(`/dashboard/publications/publication/edit/${(item as any).uuid}`)"
            >
              <VIcon icon="tabler-pencil" size="18" />
              <VTooltip activator="parent" location="top">Editar</VTooltip>
            </IconBtn>
            
            <IconBtn 
              color="secondary" 
              variant="text" 
              size="small"
              @click="router.push(`/dashboard/admin/approvals/preview/${(item as any).uuid}`)"
            >
              <VIcon icon="tabler-eye" size="18" />
              <VTooltip activator="parent" location="top">Previsualizar</VTooltip>
            </IconBtn>
          </div>
        </template>

        <!-- No data -->
        <template #no-data>
          <div class="py-10 text-center">
            <VAvatar size="80" color="secondary" variant="tonal" class="mb-4">
              <VIcon icon="tabler-clipboard-check" size="40" />
            </VAvatar>
            <p class="text-h6 text-medium-emphasis">{{ t('admin_approvals.no_data_title', 'No hay publicaciones pendientes') }}</p>
            <p class="text-body-2 text-disabled">{{ t('admin_approvals.no_data_subtitle', 'Todo está al día por ahora.') }}</p>
          </div>
        </template>
      </VDataTable>
    </VCard>

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
  </div>
</template>

<style scoped>
.text-premium-title {
  background: linear-gradient(135deg, rgb(var(--v-theme-primary)), #3b82f6);
  -webkit-background-clip: text;
  background-clip: text;
  -webkit-text-fill-color: transparent;
  font-weight: 800;
}

.motor-card-enhanced {
  border-radius: 20px !important;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05) !important;
  border: 1px solid rgba(var(--v-theme-primary), 0.05) !important;
}
</style>
<route lang="yaml">
meta:
  action: manage
  subject: all
</route>
