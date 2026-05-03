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
      <select v-model="filters.year" @change="emitFilter" class="filter-select">
        <option value="">年份</option>
        <option v-for="year in filterOptions.years" :key="year" :value="year">{{ year }}</option>
      </select>

      <select v-model="filters.genre" @change="emitFilter" class="filter-select">
        <option value="">类型</option>
        <option v-for="genre in filterOptions.genres" :key="genre" :value="genre">{{ genre }}</option>
      </select>

      <select v-model="filters.director" @change="emitFilter" class="filter-select">
        <option value="">导演</option>
        <option v-for="director in filterOptions.directors" :key="director" :value="director">{{ director }}</option>
      </select>

      <select v-model="filters.actor" @change="emitFilter" class="filter-select">
        <option value="">演员</option>
        <option v-for="actor in filterOptions.actors" :key="actor" :value="actor">{{ actor }}</option>
      </select>

      <div class="rating-filter">
        <input v-model="filters.min_rating" type="number" step="0.1" min="0" max="10" placeholder="最低分" class="filter-input" @change="emitFilter">
        <span>-</span>
        <input v-model="filters.max_rating" type="number" step="0.1" min="0" max="10" placeholder="最高分" class="filter-input" @change="emitFilter">
      </div>

      <button v-if="hasFilters" @click="clearFilters" class="clear-btn">清除筛选</button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
  filterOptions: {
    type: Object,
    default: () => ({
      years: [],
      genres: [],
      directors: [],
      actors: []
    })
  }
})

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

const hasFilters = computed(() => {
  return filters.value.year || filters.value.genre || filters.value.director || 
         filters.value.actor || filters.value.min_rating || filters.value.max_rating
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

const clearFilters = () => {
  filters.value = {
    year: '',
    genre: '',
    director: '',
    actor: '',
    min_rating: '',
    max_rating: ''
  }
  emitFilter()
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
  align-items: center;
}

.filter-select {
  padding: 8px 12px;
  background: #16213e;
  border: 1px solid #333;
  border-radius: 6px;
  color: white;
  font-size: 13px;
  min-width: 100px;
  cursor: pointer;
}

.filter-select:focus {
  outline: none;
  border-color: #e94560;
}

.filter-input {
  padding: 8px 12px;
  background: #16213e;
  border: 1px solid #333;
  border-radius: 6px;
  color: white;
  font-size: 13px;
  width: 80px;
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

.clear-btn {
  padding: 8px 16px;
  background: #e94560;
  color: white;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-size: 13px;
}

.clear-btn:hover {
  background: #d63850;
}
</style>