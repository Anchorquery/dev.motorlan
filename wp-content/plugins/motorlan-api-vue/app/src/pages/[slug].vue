<script setup lang="ts">
import { computed, ref, onMounted } from "vue";
import { useRoute } from "vue-router";
import { useI18n } from "vue-i18n";

const { t } = useI18n();
import { useUserStore } from "@/@core/stores/user";
import ProductImage from "@/pages/store/components/ProductImage.vue";
import { createUrl } from "@/@core/composable/createUrl";
import ProductDetails from "@/pages/store/components/ProductDetails.vue";
import PublicacionInfo from "@/pages/store/components/PublicacionInfo.vue";
import ProductDocs from "@/pages/store/components/ProductDocs.vue";
import RelatedProducts from "@/pages/store/components/RelatedProducts.vue";
import ChatModal from "@/pages/store/components/ChatModal.vue";
import EmptyState from "@/pages/store/components/EmptyState.vue";
import type { Publicacion } from "@/interfaces/publicacion";
import { usePublicApi } from "@/composables/usePublicApi";
import { useApi } from "@/composables/useApi";
import { setStoredGuestEmail, setStoredGuestName } from "@/utils/guest";

definePage({
  meta: {
    layout: 'blank',
    public: true,
  },
})

const route = useRoute();

const userStore = useUserStore();

const { data, isFetching, execute } = usePublicApi<any>(
  createUrl(() => `/wp-json/motorlan/v1/publicaciones/${route.params.slug}`),
  { immediate: false, refetch: true }
)
  .get()
  .json();

onMounted(execute);

const isChatModalVisible = ref(false);
const exchangedRoomKey = ref<string | null>(null);
const tokenExpired = ref(false);
const chatRoomKeyFromQuery = computed(() => {
  const raw = (route.query.room_key as string) || ''
  if (raw && raw.trim().length) return raw
  return exchangedRoomKey.value
})

const isOwner = computed(() => {
  if (!userStore.user || !publicacion.value) return false;

  return Number(userStore.user.id) === Number(publicacion.value.author.id);
});

const publicacion = computed(() => {
  if (!data.value?.data) return undefined;

  return {
    ...data.value.data,
    imagen_destacada: data.value.imagen_destacada,
  } as Publicacion;
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
  return publicacion.value.title;
});

const getInitials = (value: string): string => {
  const parts = value.split(' ').filter(Boolean)
  return parts.slice(0, 2).map(part => part.charAt(0).toUpperCase()).join('') || 'U'
}

onMounted(async () => {
  const token = (route.query.ct as string) || ''
  if (token) {
    const req = useApi<any>(createUrl(`/wp-json/motorlan/v1/chat-tokens/${encodeURIComponent(token)}`), { immediate: false }).get().json()
    await req.execute()
    const status = req.statusCode.value
    const payload = req.data.value
    if (status === 410 || status === 404 || (payload && payload.code === 'invalid_token')) {
      tokenExpired.value = true
      return
    }
    if (payload && payload.room_key) {
      exchangedRoomKey.value = String(payload.room_key)
      if (payload.guest_email) setStoredGuestEmail(String(payload.guest_email))
      const m = String(payload.room_key).match(/^pub-\d+-viewer-(.+)$/)
      if (m && m[1]) {
        try { localStorage.setItem('ml_guest_id', m[1]) } catch {}
      }
      if (payload.guest_email && !localStorage.getItem('ml_guest_name')) {
        setStoredGuestName(String(payload.guest_email).split('@')[0])
      }
      isChatModalVisible.value = true
      return
    }
  }
  if (route.query.open_chat === '1')
    isChatModalVisible.value = true
})
</script>

<template>
  <VContainer v-if="publicacion" fluid>
    <VAlert
      v-if="tokenExpired"
      type="warning"
      variant="tonal"
      class="mb-4"
      closable
      @click:close="tokenExpired = false"
    >
      <div class="font-weight-bold mb-1">Enlace caducado</div>
      <div class="text-body-2">El enlace del email ha caducado (válido 7 días). Para retomar la conversación, escribe un nuevo mensaje desde el chat del producto usando el mismo correo.</div>
    </VAlert>
    <VRow>
      <VCol cols="12">
        <h1 class="text-h4 mb-4">
          {{ title }}
        </h1>
      </VCol>
    </VRow>
    <VRow>
      <VCol cols="12" md="7">
        <ProductImage :publicacion="publicacion" />
      </VCol>
      <VCol cols="12" md="5">
        <!-- Seller row with chat button above the buy button -->


        <ProductDetails :publicacion="publicacion" :disable-actions="isOwner" @open-chat="isChatModalVisible = true" />
                <div class="d-flex align-center mb-4" v-if="publicacion.author">
          <VAvatar size="48" class="mr-4" :color="publicacion.author.avatar ? undefined : 'primary'">
            <VImg v-if="publicacion.author.avatar" :src="publicacion.author.avatar" :alt="publicacion.author.name || 'Vendedor'" />
            <span v-else class="text-h6 font-weight-bold text-white">
              {{ getInitials((publicacion.author.first_name || publicacion.author.last_name) ? `${publicacion.author.first_name || ''} ${publicacion.author.last_name || ''}`.trim() : 'Vendedor') }}
            </span>
          </VAvatar>
          <div>
            <p class="font-weight-bold mb-0">
              {{ (publicacion.author.first_name || publicacion.author.last_name) 
                  ? `${publicacion.author.first_name || ''} ${publicacion.author.last_name || ''}`.trim()
                  : 'Vendedor'
              }}
            </p>
            <p class="text-caption mb-0">
              {{ Number(publicacion.author.acf?.ventas || 0) }} ventas |
              {{ Number(publicacion.author.acf?.calificacion || 0) }} valoraciones
            </p>
          </div>

        </div>
      </VCol>
    </VRow>

    <VRow class="my-6" align="stretch">
      <VCol cols="12" md="7" class="d-flex">
        <PublicacionInfo :publicacion="publicacion" />
      </VCol>
      <VCol cols="12" md="5" class="d-flex">
        <ProductDocs :docs="docs" />
      </VCol>
    </VRow>

    <RelatedProducts :current-id="publicacion.id" />
    <ChatModal
      v-if="isChatModalVisible && !isOwner"
      :publicacion="publicacion"
      :room-key="chatRoomKeyFromQuery"
      @close="isChatModalVisible = false"
    />
  </VContainer>

  <div v-else-if="isFetching" class="text-center pa-12">
    <VProgressCircular indeterminate size="64" />
  </div>

  <EmptyState
    v-else
    :title="t('publication_slug_page.not_found_title')"
    message="Lo sentimos, no hemos podido encontrar la publicación que buscas. Es posible que el enlace sea incorrecto o que la publicación haya sido eliminada."
    :action-label="t('publication_slug_page.not_found_action')"
    action-link="/store"
  />
</template>

<style scoped>
</style>
