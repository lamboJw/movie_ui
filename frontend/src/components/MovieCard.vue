<template>
  <div class="movie-card" @click="$router.push(`/movie/${movie.id}`)">
    <div class="poster">
      <img
        :src="movie.thumb || 'https://via.placeholder.com/300x450?text=No+Image'"
        :alt="movie.title"
        @error="handleImageError"
      >
    </div>
    <div class="info">
      <h3 class="title">{{ movie.title }}</h3>
      <p class="meta">
        <span v-if="movie.year">{{ movie.year }}</span>
        <span v-if="movie.rating">⭐ {{ movie.rating }}</span>
      </p>
      <p class="date">{{ formatDate(movie.date_added) }}</p>
    </div>
  </div>
</template>

<script setup>
defineProps({
  movie: {
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
.movie-card {
  background: #1a1a2e;
  border-radius: 12px;
  overflow: hidden;
  cursor: pointer;
  transition: transform 0.2s, box-shadow 0.2s;
}

.movie-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 8px 25px rgba(233, 69, 96, 0.3);
}

.poster {
  aspect-ratio: 2/3;
  overflow: hidden;
}

.poster img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.info {
  padding: 12px;
}

.title {
  font-size: 14px;
  font-weight: bold;
  margin-bottom: 6px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.meta {
  font-size: 12px;
  color: #aaa;
  display: flex;
  gap: 10px;
  margin-bottom: 4px;
}

.date {
  font-size: 11px;
  color: #666;
}
</style>
