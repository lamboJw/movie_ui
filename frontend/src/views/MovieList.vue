<template>
  <div class="movie-list">
    <FilterBar @search="handleSearch" @filter="handleFilter" />

    <div v-if="loading" class="loading">加载中...</div>
    <div v-else-if="movies.length === 0" class="empty">
      <p>暂无电影数据</p>
      <button @click="triggerScan" class="scan-btn">扫描视频文件夹</button>
    </div>
    <div v-else>
      <div class="grid">
        <MovieCard
          v-for="movie in movies"
          :key="movie.id"
          :movie="movie"
        />
      </div>
      <div class="pagination">
        <button :disabled="page <= 1" @click="changePage(page - 1)">上一页</button>
        <span>{{ page }} / {{ totalPages }}</span>
        <button :disabled="page >= totalPages" @click="changePage(page + 1)">下一页</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import MovieCard from '../components/MovieCard.vue'
import FilterBar from '../components/FilterBar.vue'
import { movieApi } from '../api/movieApi'

const movies = ref([])
const loading = ref(true)
const page = ref(1)
const totalPages = ref(1)
const filters = ref({})

const fetchMovies = async () => {
  loading.value = true
  try {
    const params = { page: page.value, limit: 20, ...filters.value }
    const res = await movieApi.getMovies(params)
    movies.value = res.data.movies
    totalPages.value = res.data.total_pages
  } catch (e) {
    console.error('获取电影列表失败', e)
  } finally {
    loading.value = false
  }
}

const changePage = (newPage) => {
  page.value = newPage
  fetchMovies()
}

const handleSearch = (search) => {
  filters.value.search = search
  page.value = 1
  fetchMovies()
}

const handleFilter = (newFilters) => {
  filters.value = { ...filters.value, ...newFilters }
  page.value = 1
  fetchMovies()
}

const triggerScan = async () => {
  if (confirm('确定要扫描视频文件夹吗？这可能需要几分钟时间。')) {
    loading.value = true
    await movieApi.scan()
    alert('扫描完成！')
    fetchMovies()
  }
}

onMounted(fetchMovies)
</script>

<style scoped>
.movie-list {
  padding: 20px 40px;
}

.grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 20px;
  margin-top: 20px;
}

.loading, .empty {
  text-align: center;
  padding: 60px;
  color: #888;
}

.scan-btn {
  margin-top: 20px;
  padding: 10px 20px;
  background: #e94560;
  color: white;
  border: none;
  border-radius: 8px;
  cursor: pointer;
}

.pagination {
  display: flex;
  justify-content: center;
  gap: 20px;
  margin-top: 30px;
  align-items: center;
}

.pagination button {
  padding: 8px 16px;
  background: #1a1a2e;
  color: white;
  border: 1px solid #333;
  border-radius: 6px;
  cursor: pointer;
}

.pagination button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
</style>
