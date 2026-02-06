<script setup lang="ts">
import { computed, ref, onMounted } from "vue";
import { useRoute } from "vue-router";
import { useUserStore } from "@/@core/stores/user";
import ProductImage from "@/pages/store/components/ProductImage.vue";
import { createFetch } from '@vueuse/core'
import { createUrl } from "@/@core/composable/createUrl";
import ProductDetails from "@/pages/store/components/ProductDetails.vue";
import PublicacionInfo from "@/pages/store/components/PublicacionInfo.vue";
import ProductDocs from "@/pages/store/components/ProductDocs.vue";
import RelatedProducts from "@/pages/store/components/RelatedProducts.vue";
import ChatModal from "@/pages/store/components/ChatModal.vue";
import EmptyState from "@/pages/store/components/EmptyState.vue";
import type { Publicacion } from "@/interfaces/publicacion";

definePage({
  meta: {
    layout: 'blank',
    public: true,
  },
})

const route = useRoute();

const userStore = useUserStore();

// Define a public API client that bypasses the global useApi (which forces auth headers and redirects on 401)
// This ensures the product page is truly public even if the user has an expired token in their cookies.
const usePublicApi = createFetch({
  baseUrl: (import.meta.env.VITE_API_BASE_URL?.trim() ?? '') || ((window as any)?.wpData?.site_url ?? window.location.origin),
  options: {
      async onFetchError({ error }) {
          console.error('Public API Error:', error);
          return { error };
      }
  },
})

const { data, isFetching, execute } = usePublicApi<any>(
  createUrl(() => `/wp-json/motorlan/v1/publicaciones/${route.params.slug}`),
  { immediate: false, refetch: true }
)
  .get()
  .json();

onMounted(execute);

const isChatModalVisible = ref(false);
const chatRoomKeyFromQuery = computed(() => {
  const raw = (route.query.room_key as string) || ''
  return raw && raw.trim().length ? raw : null
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

  // Nomenclature: Tipo de producto_Marca_Tipo/modelo_Potencia o Par_Velocidad
  
  // 1. Tipo
  const tipo = publicacion.value.tipo && publicacion.value.tipo.length > 0 ? publicacion.value.tipo[0].name : '';
  
  // 2. Marca
  const marca = (publicacion.value as any).marca_name || '';

  // 3. Modelo
  const modelo = publicacion.value.acf.tipo_o_referencia || '';

  // 4. Potencia o Par
  let powerOrTorque = '';
  if (publicacion.value.acf.potencia) {
      powerOrTorque = `${publicacion.value.acf.potencia} kW`;
  } else if (publicacion.value.acf.par_nominal) {
      powerOrTorque = `${publicacion.value.acf.par_nominal} Nm`;
  }

  // 5. Velocidad
  const velocidad = publicacion.value.acf.velocidad
      ? `${publicacion.value.acf.velocidad} rpm`
      : '';

  const parts = [
    tipo,
    marca,
    modelo,
    powerOrTorque,
    velocidad,
  ].filter(p => !!p && String(p).trim() !== '');

  return parts.join(' ').toUpperCase();
});

const getInitials = (value: string): string => {
  const parts = value.split(' ').filter(Boolean)
  return parts.slice(0, 2).map(part => part.charAt(0).toUpperCase()).join('') || 'U'
}

onMounted(() => {
  if (route.query.open_chat === '1')
    isChatModalVisible.value = true
})
</script>

<template>
  <VContainer v-if="publicacion" fluid>
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
    title="Publicación no encontrada"
    message="Lo sentimos, no hemos podido encontrar la publicación que buscas. Es posible que el enlace sea incorrecto o que la publicación haya sido eliminada."
    action-label="Ir a la tienda"
    action-link="/store"
  />
</template>

<style scoped>
</style>
