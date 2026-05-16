<template>
  <div class="filter-bar">
    <div class="filter-header">
      <div class="search-box">
        <input
          v-model="searchText"
          @input="emitSearch"
          type="text"
          placeholder="搜索电影名称..."
          class="search-input"
        >
      </div>
      <button class="toggle-filters" @click="collapsed = !collapsed">
        {{ collapsed ? '▼ 筛选' : '▲ 筛选' }}
      </button>
    </div>

    <div class="filters" v-show="!collapsed">
      <Combobox
        :options="filterOptions.years"
        placeholder="年份"
        v-model="filters.year"
        @change="emitFilter"
      />
      <Combobox
        :options="filterOptions.genres"
        placeholder="类型"
        v-model="filters.genre"
        @change="emitFilter"
      />
      <Combobox
        :options="filterOptions.directors"
        placeholder="导演"
        v-model="filters.director"
        @change="emitFilter"
      />
      <Combobox
        :options="filterOptions.actors"
        placeholder="演员"
        v-model="filters.actor"
        @change="emitFilter"
      />
      <div class="rating-filter">
        <input v-model="filters.min_rating" type="number" step="0.1" min="0" max="10" placeholder="最低分" class="filter-input" @change="emitFilter">
        <span>-</span>
        <input v-model="filters.max_rating" type="number" step="0.1" min="0" max="10" placeholder="最高分" class="filter-input" @change="emitFilter">
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import Combobox from './Combobox.vue'

const props = defineProps({
  filterOptions: {
    type: Object,
    default: () => ({ years: [], genres: [], directors: [], actors: [] })
  }
})

const emit = defineEmits(['search', 'filter'])

const searchText = ref('')
const collapsed = ref(true)

function updateCollapsed() {
  collapsed.value = window.innerWidth <= 768
}

onMounted(() => {
  updateCollapsed()
  window.addEventListener('resize', updateCollapsed)
})

onUnmounted(() => {
  window.removeEventListener('resize', updateCollapsed)
})

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

.filter-header {
  display: flex;
  gap: 10px;
  align-items: center;
}

.filter-header .search-box {
  flex: 1;
  margin-bottom: 0;
}

.toggle-filters {
  display: none;
  padding: 8px 12px;
  background: #16213e;
  color: #888;
  border: 1px solid #333;
  border-radius: 6px;
  cursor: pointer;
  font-size: 12px;
  white-space: nowrap;
}

.toggle-filters:hover {
  color: #fff;
  border-color: #e94560;
}

@media (max-width: 768px) {
  .filter-header {
    flex-direction: row;
  }

  .toggle-filters {
    display: inline-block;
  }

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
