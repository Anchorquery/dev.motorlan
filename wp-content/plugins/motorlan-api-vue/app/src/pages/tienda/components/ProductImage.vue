<script setup lang="ts">
import { computed } from 'vue'
import { register } from 'swiper/element/bundle'
import type { Motor } from '@/interfaces/motor'

const props = defineProps<{ motor: Motor }>()

register()

const images = computed(() => {
  const gallery = (props.motor.acf?.motor_gallery || []) as any[]

  const featured = props.motor.imagen_destacada && !Array.isArray(props.motor.imagen_destacada)
    ? [props.motor.imagen_destacada]
    : []

  return [...featured, ...gallery].filter(img => img && img.url)
})
</script>

<template>
  <div class="product-image">
    <swiper-container
      v-if="images.length"
      class="mainSwiper"
      thumbs-swiper=".thumbsSwiper"
      loop="true"
      navigation="true"
      space-between="10"
    >
      <swiper-slide
        v-for="img in images"
        :key="img.url"
      >
        <img
          :src="img.url"
          alt=""
        >
      </swiper-slide>
    </swiper-container>
    <swiper-container
      v-if="images.length > 1"
      class="thumbsSwiper mt-2"
      loop="true"
      free-mode="true"
      slides-per-view="4"
      space-between="10"
    >
      <swiper-slide
        v-for="img in images"
        :key="`thumb-${img.url}`"
      >
        <img
          :src="img.url"
          alt=""
        >
      </swiper-slide>
    </swiper-container>
  </div>
</template>

<style scoped lang="scss">
@use "swiper/css/bundle";

.product-image {
  flex: 1 1 300px;
  max-width: 600px;
}

.product-image swiper-container {
  background: #EEF1F4;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 300px;
}

.product-image img {
  max-width: 100%;
  max-height: 100%;
}

.thumbsSwiper {
  swiper-slide {
    opacity: 0.5;
    cursor: pointer;
  }

  .swiper-slide-thumb-active {
    opacity: 1;
  }
}
</style>
