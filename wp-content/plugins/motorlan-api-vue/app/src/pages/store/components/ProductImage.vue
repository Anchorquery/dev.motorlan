<script setup lang="ts">
import { computed, ref } from 'vue'
import { Swiper, SwiperSlide } from 'swiper/vue'
import { Navigation, Thumbs } from 'swiper/modules'
import type { Publicacion } from '@/interfaces/publicacion'

import 'swiper/css'
import 'swiper/css/navigation'
import 'swiper/css/thumbs'

const props = defineProps<{ publicacion: Publicacion }>()

const thumbsSwiper = ref<any>(null)

const images = computed(() => {
  type ImageItem = { url: string; [key: string]: any }

  const gallery = (props.publicacion.acf?.motor_gallery || []) as ImageItem[]
  const featured = props.publicacion.imagen_destacada && !Array.isArray(props.publicacion.imagen_destacada)
    ? [props.publicacion.imagen_destacada]
    : []
  const acfImage = (props.publicacion.acf?.motor_image && !Array.isArray(props.publicacion.acf?.motor_image))
    ? [props.publicacion.acf?.motor_image]
    : []

  const allImages = [...featured, ...gallery, ...acfImage] as ImageItem[]
  const uniqueImages = allImages.reduce((acc: ImageItem[], current: ImageItem) => {
    if (current && current.url && !acc.find((item: ImageItem) => item.url === current.url))
      acc.push(current)

    return acc
  }, [])

  return uniqueImages.filter((img: ImageItem) => img && img.url)
})
</script>

<template>
  <VCard class="product-image-card" flat>
    <div class="product-image">
      <Swiper
        v-if="images.length"
        :modules="[Navigation, Thumbs]"
        :navigation="true"
        :loop="images.length > 1"
        :space-between="10"
        :thumbs="{ swiper: thumbsSwiper }"
        class="main-swiper"
      >
        <SwiperSlide
          v-for="img in images"
          :key="img.url"
        >
          <VImg
            :src="img.url"
            cover
            class="main-image"
            alt="Imagen del motor"
          />
        </SwiperSlide>
      </Swiper>

      <Swiper
        v-if="images.length > 1"
        @swiper="thumbsSwiper = $event"
        :space-between="10"
        :slides-per-view="4"
        :watch-slides-progress="true"
        class="thumbs-swiper mt-3"
      >
        <SwiperSlide
          v-for="img in images"
          :key="`thumb-${img.url}`"
        >
          <VImg
            :src="img.url"
            aspect-ratio="1"
            cover
            alt="Miniatura"
            class="thumb-img"
          />
        </SwiperSlide>
      </Swiper>

      <div
        v-if="!images.length"
        class="image-placeholder"
      />
    </div>
  </VCard>
</template>

<style scoped lang="scss">
.product-image-card {
  width: 100%;
  border-radius: 20px;
  border: 1px solid rgba(218, 41, 28, 0.08);
  box-shadow: 0 12px 24px rgba(20, 20, 43, 0.06);
  overflow: hidden;
  background: #fff;
}

.product-image {
  width: 100%;
  padding: 0.75rem;
}

.main-swiper {
  background: white !important;
  border-radius: 16px;
  min-height: 320px;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;

  img, .v-img {
    max-width: 100%;
    max-height: 100%;
  }
}

.main-image {
  min-height: 320px;
}

.thumbs-swiper {
  .swiper-slide {
    opacity: 0.6;
    cursor: pointer;
    transition: opacity 0.3s, transform 0.2s;
    border-radius: 10px;
    overflow: hidden;

    &:hover {
      opacity: 1;
      transform: scale(1.03);
    }

    &.swiper-slide-thumb-active {
      opacity: 1;
      border: 2px solid rgb(var(--v-theme-primary));
    }
  }
}

.thumb-img {
  border-radius: 10px;
}

.image-placeholder {
  width: 100%;
  min-height: 320px;
  background: linear-gradient(135deg, #f7f7f9, #ffffff);
  border-radius: 16px;
}

:deep(.swiper-button-next),
:deep(.swiper-button-prev) {
  background-color: white;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  box-shadow: 0 4px 14px rgba(0, 0, 0, 0.15);
  color: #da291c;

  &:after {
    font-size: 16px;
    font-weight: bold;
  }
}

:deep(.swiper-button-disabled) {
  opacity: 0;
  pointer-events: none;
}

@media (max-width: 959px) {
  .product-image {
    padding: 0.5rem;
  }

  .main-swiper,
  .image-placeholder,
  .main-image {
    min-height: 240px;
  }
}
</style>
