<script setup lang="ts">
import { computed, ref, onMounted, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
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
import { setGuestRoomToken, setStoredGuestEmail, setStoredGuestName } from "@/utils/guest";

definePage({
  meta: {
    layout: 'blank',
    public: true,
  },
})

const route = useRoute();
const router = useRouter();

const userStore = useUserStore();

const data = ref<any>(null);
const isFetching = ref(true);

// Con sesión la petición va autenticada: el dueño/admin ve su publicación pendiente o en
// borrador y su precio de referencia (usePublicApi va sin cookies y siempre daba "no
// encontrada"). Si la autenticada falla (p. ej. nonce caducado) se repite como pública.
const loadPublicacion = async () => {
  const slug = String(route.params.slug || "");
  if (!slug) {
    data.value = null;
    isFetching.value = false;
    return;
  }

  isFetching.value = true;
  const url = `/wp-json/motorlan/v1/publicaciones/${encodeURIComponent(slug)}`;
  try {
    const hasSession = userStore.getIsLoggedIn || Boolean((window as any)?.wpData?.user_data?.is_logged_in);
    if (hasSession) {
      const authReq = useApi<any>(createUrl(url), { immediate: false }).get().json();
      await authReq.execute();
      if (!authReq.error.value && authReq.data.value?.data) {
        data.value = authReq.data.value;
        return;
      }
      if (authReq.statusCode.value === 404) {
        data.value = null;
        return;
      }
    }

    const publicReq = usePublicApi<any>(createUrl(url), { immediate: false }).get().json();
    await publicReq.execute();
    data.value = publicReq.error.value ? null : publicReq.data.value;
  }
  catch {
    data.value = null;
  }
  finally {
    isFetching.value = false;
    // URL antigua (resuelta vía _wp_old_slug en el backend): pasar a la canónica
    const canonical = data.value?.data?.slug;
    if (canonical && canonical !== String(route.params.slug || ""))
      router.replace({ params: { ...route.params, slug: canonical }, query: route.query, hash: route.hash });
  }
};

onMounted(loadPublicacion);
// Navegación entre fichas (productos relacionados): mismo componente, otro slug
watch(() => route.params.slug, (next, prev) => {
  if (next && next !== prev)
    loadPublicacion();
});

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

  return Number(userStore.user.id) === Number(publicacion.value.author?.id);
});

// Antes se sobrescribía imagen_destacada con data.value.imagen_destacada (undefined:
// la respuesta es { data: {...} })
const publicacion = computed(() => {
  if (!data.value?.data) return undefined;

  return data.value.data as Publicacion;
});

// Vendida, pausada o sin stock: la ficha sigue visible pero sin "Consultar precio"
const isUnavailable = computed(() => {
  const pub = publicacion.value;
  if (!pub) return false;
  if (["paused", "sold"].includes(String(pub.status || ""))) return true;
  const stock = (pub.acf as any)?.stock;

  return stock !== null && stock !== undefined && stock !== "" && Number(stock) <= 0;
});

const sellerDisplayName = computed(() => {
  const author = publicacion.value?.author;
  if (!author) return t("product_details.seller_default");
  const fullName = `${author.first_name || ""} ${author.last_name || ""}`.trim();

  return fullName || author.name || t("product_details.seller_default");
});

const sellerSalesCount = computed(() => Number((publicacion.value?.author?.acf as any)?.ventas) || 0);
const sellerReviewsCount = computed(() => Number((publicacion.value?.author?.acf as any)?.valoraciones) || 0);

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
      if (payload.room_token)
        setGuestRoomToken(String(payload.room_key), String(payload.room_token))
      if (payload.guest_email) setStoredGuestEmail(String(payload.guest_email))
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
      <div class="font-weight-bold mb-1">{{ t('publication_slug_page.link_expired_title', 'Enlace caducado') }}</div>
      <div class="text-body-2">{{ t('publication_slug_page.link_expired_text', 'El enlace del email ha caducado (válido 7 días). Para retomar la conversación, escribe un nuevo mensaje desde el chat del producto usando el mismo correo.') }}</div>
    </VAlert>
    <VAlert
      v-if="isUnavailable && !isOwner"
      type="info"
      variant="tonal"
      class="mb-4"
    >
      {{ t('publication_slug_page.unavailable', 'Este artículo ya no está disponible: se ha vendido o el vendedor lo ha retirado temporalmente.') }}
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


        <ProductDetails :publicacion="publicacion" :disable-actions="isOwner || isUnavailable" @open-chat="isChatModalVisible = true" />
                <div class="d-flex align-center mb-4" v-if="publicacion.author">
          <VAvatar size="48" class="mr-4" :color="publicacion.author.avatar ? undefined : 'primary'">
            <VImg v-if="publicacion.author.avatar" :src="publicacion.author.avatar" :alt="sellerDisplayName" />
            <span v-else class="text-h6 font-weight-bold text-white">
              {{ getInitials(sellerDisplayName) }}
            </span>
          </VAvatar>
          <div>
            <p class="font-weight-bold mb-0">
              {{ sellerDisplayName }}
            </p>
            <!-- Antes mostraba la media (calificacion) como si fuera el número de valoraciones -->
            <p class="text-caption mb-0">
              {{ t('product_details.seller_sales_count', { n: sellerSalesCount }, 'Ventas: {n}') }} |
              {{ t('product_details.seller_reviews_count', { n: sellerReviewsCount }, 'Valoraciones: {n}') }}
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

    <RelatedProducts :current-id="publicacion.id" :tipo="publicacion.tipo?.[0]?.slug" />
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
    :message="t('store.empty.message')"
    :action-label="t('publication_slug_page.not_found_action')"
    action-link="/"
  />
</template>

<style scoped>
</style>
