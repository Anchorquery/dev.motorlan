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
    if (current && current.url && !acc.find((item: ImageItem) => item.url === current.url)) {
      acc.push(current)
    }
    return acc
  }, [])

  return uniqueImages.filter((img: ImageItem) => img && img.url)
})
</script>

<template>
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
      <SwiperSlide v-for="img in images" :key="img.url">
        <VImg
          :src="img.url"
          cover
          max-height="500"
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
      class="thumbs-swiper mt-2"
    >
      <SwiperSlide v-for="img in images" :key="`thumb-${img.url}`">
        <VImg
          :src="img.url"
          aspect-ratio="1"
          cover
          alt="Miniatura"
          class="thumb-img"
        />
      </SwiperSlide>
    </Swiper>

    <div v-if="!images.length" class="image-placeholder" />
  </div>
</template>

<style scoped lang="scss">
.product-image {
  width: 100%;
}

.main-swiper {
  background: #EEF1F4;
  border-radius: 8px;
  min-height: 300px;
  display: flex;
  align-items: center;
  justify-content: center;

  img, .v-img {
    max-width: 100%;
    max-height: 100%;
  }
}

.thumbs-swiper {
  margin-top: 10px;

  .swiper-slide {
    opacity: 0.6;
    cursor: pointer;
    transition: opacity 0.3s, transform 0.2s;
    border-radius: 6px;
    overflow: hidden;

    &:hover {
      opacity: 1;
      transform: scale(1.05);
    }

    &.swiper-slide-thumb-active {
      opacity: 1;
      border: 2px solid rgb(var(--v-theme-primary));
    }
  }
}

.image-placeholder {
  width: 100%;
  height: 100%;
  min-height: 300px;
  background: #EEF1F4;
  border-radius: 8px;
}
</style>
