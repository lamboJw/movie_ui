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
      <div class="filter-select-wrapper">
        <div class="input-with-clear">
          <input
            v-model="filterTexts.year"
            @input="onFilterInput('year')"
            @focus="showDropdown('year')"
            @keydown.enter="selectFirst('year')"
            type="text"
            placeholder="年份"
            class="filter-input-text"
          >
          <button v-if="filterTexts.year" class="clear-input" @click="clearFilter('year')">×</button>
        </div>
        <div v-if="openDropdown === 'year'" class="filter-dropdown">
          <div
            v-for="year in filteredYears"
            :key="year"
            class="filter-option"
            @click="selectOption('year', year)"
          >{{ year }}</div>
          <div v-if="!filteredYears.length" class="filter-empty">无匹配</div>
        </div>
      </div>

      <div class="filter-select-wrapper">
        <div class="input-with-clear">
          <input
            v-model="filterTexts.genre"
            @input="onFilterInput('genre')"
            @focus="showDropdown('genre')"
            @keydown.enter="selectFirst('genre')"
            type="text"
            placeholder="类型"
            class="filter-input-text"
          >
          <button v-if="filterTexts.genre" class="clear-input" @click="clearFilter('genre')">×</button>
        </div>
        <div v-if="openDropdown === 'genre'" class="filter-dropdown">
          <div
            v-for="genre in filteredGenres"
            :key="genre"
            class="filter-option"
            @click="selectOption('genre', genre)"
          >{{ genre }}</div>
          <div v-if="!filteredGenres.length" class="filter-empty">无匹配</div>
        </div>
      </div>

      <div class="filter-select-wrapper">
        <div class="input-with-clear">
          <input
            v-model="filterTexts.director"
            @input="onFilterInput('director')"
            @focus="showDropdown('director')"
            @keydown.enter="selectFirst('director')"
            type="text"
            placeholder="导演"
            class="filter-input-text"
          >
          <button v-if="filterTexts.director" class="clear-input" @click="clearFilter('director')">×</button>
        </div>
        <div v-if="openDropdown === 'director'" class="filter-dropdown">
          <div
            v-for="director in filteredDirectors"
            :key="director"
            class="filter-option"
            @click="selectOption('director', director)"
          >{{ director }}</div>
          <div v-if="!filteredDirectors.length" class="filter-empty">无匹配</div>
        </div>
      </div>

      <div class="filter-select-wrapper">
        <div class="input-with-clear">
          <input
            v-model="filterTexts.actor"
            @input="onFilterInput('actor')"
            @focus="showDropdown('actor')"
            @keydown.enter="selectFirst('actor')"
            type="text"
            placeholder="演员"
            class="filter-input-text"
          >
          <button v-if="filterTexts.actor" class="clear-input" @click="clearFilter('actor')">×</button>
        </div>
        <div v-if="openDropdown === 'actor'" class="filter-dropdown">
          <div
            v-for="actor in filteredActors"
            :key="actor"
            class="filter-option"
            @click="selectOption('actor', actor)"
          >{{ actor }}</div>
          <div v-if="!filteredActors.length" class="filter-empty">无匹配</div>
        </div>
      </div>

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
import { ref, computed, onMounted, onUnmounted } from 'vue'

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

const filterTexts = ref({
  year: '',
  genre: '',
  director: '',
  actor: ''
})

const openDropdown = ref('')

const filteredYears = computed(() => {
  const text = filterTexts.value.year
  if (!text) return props.filterOptions.years || []
  return (props.filterOptions.years || []).filter(y => String(y).includes(text))
})

const filteredGenres = computed(() => {
  const text = filterTexts.value.genre
  if (!text) return props.filterOptions.genres || []
  return (props.filterOptions.genres || []).filter(g => g.toLowerCase().includes(text.toLowerCase()))
})

const filteredDirectors = computed(() => {
  const text = filterTexts.value.director
  if (!text) return props.filterOptions.directors || []
  return (props.filterOptions.directors || []).filter(d => d.toLowerCase().includes(text.toLowerCase()))
})

const filteredActors = computed(() => {
  const text = filterTexts.value.actor
  if (!text) return props.filterOptions.actors || []
  return (props.filterOptions.actors || []).filter(a => a.toLowerCase().includes(text.toLowerCase()))
})

const onFilterInput = (field) => {
  openDropdown.value = field
}

const showDropdown = (field) => {
  openDropdown.value = field
}

const selectOption = (field, value) => {
  filters.value[field] = value
  filterTexts.value[field] = value
  openDropdown.value = ''
  emitFilter()
}

const selectFirst = (field) => {
  const map = {
    year: filteredYears.value,
    genre: filteredGenres.value,
    director: filteredDirectors.value,
    actor: filteredActors.value
  }
  const list = map[field]
  if (list && list.length > 0) {
    selectOption(field, list[0])
  }
}

const clearFilter = (field) => {
  filters.value[field] = ''
  filterTexts.value[field] = ''
  openDropdown.value = ''
  emitFilter()
}

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
  filterTexts.value = {
    year: '',
    genre: '',
    director: '',
    actor: ''
  }
  emitFilter()
}

const closeDropdown = (e) => {
  if (!e.target.closest('.filter-select-wrapper')) {
    openDropdown.value = ''
  }
}

onMounted(() => {
  document.addEventListener('click', closeDropdown)
})

onUnmounted(() => {
  document.removeEventListener('click', closeDropdown)
})
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

.filter-select-wrapper {
  position: relative;
}

.input-with-clear {
  display: flex;
  align-items: center;
  position: relative;
}

.filter-input-text {
  padding: 8px 30px 8px 12px;
  background: #16213e;
  border: 1px solid #333;
  border-radius: 6px;
  color: white;
  font-size: 13px;
  min-width: 100px;
}

.clear-input {
  position: absolute;
  right: 8px;
  background: none;
  border: none;
  color: #666;
  cursor: pointer;
  font-size: 16px;
  padding: 0 4px;
  line-height: 1;
}

.clear-input:hover {
  color: #e94560;
}

.filter-input-text:focus {
  outline: none;
  border-color: #e94560;
}

.filter-dropdown {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  max-height: 200px;
  overflow-y: auto;
  background: #1a1a2e;
  border: 1px solid #333;
  border-top: none;
  border-radius: 0 0 6px 6px;
  z-index: 100;
}

.filter-option {
  padding: 8px 12px;
  cursor: pointer;
  color: #ccc;
}

.filter-option:hover {
  background: #e94560;
  color: white;
}

.filter-empty {
  padding: 8px 12px;
  color: #666;
  font-size: 12px;
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

@media (max-width: 768px) {
  .filter-bar {
    padding: 12px;
  }

  .search-input {
    padding: 10px 12px;
    font-size: 13px;
  }

  .filters {
    gap: 8px;
  }

  .filter-input-text {
    min-width: 80px;
    font-size: 12px;
    padding: 6px 24px 6px 8px;
  }

  .filter-input {
    width: 60px;
    padding: 6px 8px;
    font-size: 12px;
  }

  .rating-filter span {
    font-size: 12px;
  }

  .clear-btn {
    padding: 6px 12px;
    font-size: 11px;
  }
}
</style>