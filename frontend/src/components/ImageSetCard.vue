<template>
  <div class="image-set-card" @click="$emit('click')">
    <div class="cover">
      <img :src="imageSet.cover_image || 'https://via.placeholder.com/300x200?text=No+Image'" :alt="imageSet.title">
      <div class="count-badge">
        <span>{{ imageSet.image_count || 0 }}P</span>
      </div>
    </div>
    <div class="info">
      <p class="title">{{ imageSet.title }}</p>
    </div>
  </div>
</template>

<script setup>
defineProps({
  imageSet: { type: Object, required: true }
})

defineEmits(['click'])
</script>

<style scoped>
.image-set-card {
  cursor: pointer;
  border-radius: 10px;
  overflow: hidden;
  background: #1a1a2e;
  transition: transform .2s;
}

.image-set-card:hover {
  transform: translateY(-4px);
}

.cover {
  position: relative;
  aspect-ratio: 3 / 2;
  overflow: hidden;
}

.cover img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.count-badge {
  position: absolute;
  bottom: 8px;
  right: 8px;
  padding: 3px 8px;
  background: rgba(0,0,0,.7);
  color: #fff;
  border-radius: 4px;
  font-size: 12px;
}

.info {
  padding: 10px 12px;
}

.info .title {
  font-size: 14px;
  color: #ddd;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
</style>
