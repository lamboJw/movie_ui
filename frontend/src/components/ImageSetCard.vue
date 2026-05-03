<template>
  <div class="image-set-card" @click="$router.push(`/image_set/${imageSet.id}`)">
    <div class="cover">
      <img
        :src="imageSet.cover_image || 'https://via.placeholder.com/300x450?text=No+Image'"
        :alt="imageSet.title"
        @error="handleImageError"
      >
      <div class="count">{{ imageSet.image_count }}</div>
    </div>
    <div class="info">
      <h3 class="title">{{ imageSet.title }}</h3>
      <p class="folder" v-if="imageSet.parent_path">{{ imageSet.parent_path }}</p>
      <p class="date">{{ formatDate(imageSet.date_added) }}</p>
    </div>
  </div>
</template>

<script setup>
defineProps({
  imageSet: {
    type: Object,
    required: true
  }
})

const handleImageError = (e) => {
  e.target.src = 'https://via.placeholder.com/300x450?text=No+Image'
}

const formatDate = (dateStr) => {
  if (!dateStr) return ''
  const date = new Date(dateStr)
  return date.toLocaleDateString('zh-CN')
}
</script>

<style scoped>
.image-set-card {
  background: #1a1a2e;
  border-radius: 12px;
  overflow: hidden;
  cursor: pointer;
  transition: transform 0.2s, box-shadow 0.2s;
}

.image-set-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 8px 25px rgba(233, 69, 96, 0.3);
}

.cover {
  aspect-ratio: 2/3;
  overflow: hidden;
  position: relative;
}

.cover img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.count {
  position: absolute;
  bottom: 8px;
  right: 8px;
  background: rgba(0, 0, 0, 0.7);
  color: #fff;
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 12px;
}

.info {
  padding: 12px;
}

.title {
  font-size: 14px;
  font-weight: bold;
  margin-bottom: 4px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.folder {
  font-size: 11px;
  color: #4a9;
  margin-bottom: 4px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.date {
  font-size: 11px;
  color: #666;
}

@media (max-width: 768px) {
  .info {
    padding: 8px;
  }

  .title {
    font-size: 12px;
  }

  .folder {
    font-size: 10px;
  }

  .date {
    font-size: 10px;
  }

  .count {
    font-size: 10px;
    padding: 2px 6px;
  }
}
</style>