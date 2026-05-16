<template>
  <div class="filter-bar">
    <div class="search-box">
      <input
        v-model="searchText"
        @input="emitSearch"
        type="text"
        placeholder="搜索电影名称..."
        class="search-input"
      >
    </div>

    <div class="filters">
      <input v-model="filters.year" type="text" placeholder="年份" class="filter-input" @change="emitFilter">
      <input v-model="filters.genre" type="text" placeholder="类型" class="filter-input" @change="emitFilter">
      <input v-model="filters.director" type="text" placeholder="导演" class="filter-input" @change="emitFilter">
      <input v-model="filters.actor" type="text" placeholder="演员" class="filter-input" @change="emitFilter">
      <div class="rating-filter">
        <input v-model="filters.min_rating" type="number" step="0.1" min="0" max="10" placeholder="最低分" class="filter-input" @change="emitFilter">
        <span>-</span>
        <input v-model="filters.max_rating" type="number" step="0.1" min="0" max="10" placeholder="最高分" class="filter-input" @change="emitFilter">
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'

const emit = defineEmits(['search', 'filter'])

const searchText = ref('')
const filters = ref({
  year: '',
  genre: '',
  director: '',
  actor: '',
  min_rating: '',
  max_rating: ''
})

let searchTimeout
const emitSearch = () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    emit('search', searchText.value)
  }, 300)
}

const emitFilter = () => {
  emit('filter', { ...filters.value })
}
</script>

<style scoped>
.filter-bar {
  background: #1a1a2e;
  padding: 20px;
  border-radius: 12px;
  margin-bottom: 20px;
}

.search-box {
  margin-bottom: 15px;
}

.search-input {
  width: 100%;
  padding: 12px 16px;
  background: #16213e;
  border: 1px solid #333;
  border-radius: 8px;
  color: white;
  font-size: 14px;
}

.search-input:focus {
  outline: none;
  border-color: #e94560;
}

.filters {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.filter-input {
  padding: 8px 12px;
  background: #16213e;
  border: 1px solid #333;
  border-radius: 6px;
  color: white;
  font-size: 13px;
  width: 120px;
}

.filter-input:focus {
  outline: none;
  border-color: #e94560;
}

.rating-filter {
  display: flex;
  align-items: center;
  gap: 5px;
}

.rating-filter span {
  color: #888;
}

@media (max-width: 768px) {
  .filter-bar {
    padding: 12px;
  }

  .filters {
    gap: 6px;
  }

  .filter-input {
    width: 100%;
    font-size: 12px;
    padding: 6px 10px;
  }

  .search-input {
    padding: 10px 12px;
    font-size: 13px;
  }

  .rating-filter {
    width: 100%;
  }

  .rating-filter .filter-input {
    width: 50%;
  }
}
</style>
