<template>
  <div class="movie-list">
    <div class="header">
      <FilterBar @search="handleSearch" @filter="handleFilter" />
      <div class="mode-switch">
        <button 
          :class="{ active: viewMode === 'folder' }" 
          @click="switchMode('folder')"
        >📁 文件夹</button>
        <button 
          :class="{ active: viewMode === 'list' }" 
          @click="switchMode('list')"
        >📋 列表</button>
      </div>
    </div>

    <!-- 文件夹模式 -->
    <div v-if="viewMode === 'folder'" class="folder-view">
      <!-- 面包屑导航 -->
      <div class="breadcrumb" v-if="currentPath">
        <span 
          class="crumb" 
          :class="{ disabled: !canGoBack }"
          @click="goBack"
        >← 返回</span>
        <span class="current">{{ currentPath }}</span>
      </div>

      <div v-if="loading" class="loading">加载中...</div>
      <div v-else>
        <!-- 文件夹列表 -->
        <div v-if="folders.length" class="folder-list">
          <div 
            v-for="folder in folders" 
            :key="folder.path"
            class="folder-item"
            @click="enterFolder(folder.path)"
          >
            <span class="icon">📁</span>
            <span class="name">{{ folder.name }}</span>
          </div>
        </div>

        <!-- 文件列表 -->
        <div v-if="files.length" class="grid">
          <MovieCard
            v-for="file in files"
            :key="file.video_path"
            :movie="file"
            @click="openDetail(file)"
          />
        </div>

        <div v-if="!folders.length && !files.length" class="empty">
          <p>该目录为空</p>
        </div>
      </div>
    </div>

    <!-- 视频列表模式 -->
    <div v-else class="list-view">
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
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import MovieCard from '../components/MovieCard.vue'
import FilterBar from '../components/FilterBar.vue'
import { movieApi } from '../api/movieApi'

const router = useRouter()
const route = useRoute()

const movies = ref([])
const loading = ref(true)
const page = ref(1)
const totalPages = ref(1)
const filters = ref({})

// 文件夹模式
const viewMode = ref(localStorage.getItem('viewMode') || 'folder')
const currentPath = ref('')
const canGoBack = ref(false)
const folders = ref([])
const files = ref([])

const switchMode = (mode) => {
  viewMode.value = mode
  localStorage.setItem('viewMode', mode)
  if (mode === 'folder') {
    fetchBrowse()
  } else {
    fetchMovies()
  }
}

const fetchBrowse = async (path = '') => {
  loading.value = true
  try {
    const res = await movieApi.browse(path)
    const data = res.data
    currentPath.value = data.current_path
    canGoBack.value = data.can_go_back
    folders.value = data.folders || []
    files.value = data.files || []
  } catch (e) {
    console.error('获取目录失败', e)
  } finally {
    loading.value = false
  }
}

const enterFolder = (path) => {
  fetchBrowse(path)
}

const goBack = () => {
  if (!canGoBack.value) return
  const parts = currentPath.value.split('/')
  parts.pop()
  const parentPath = parts.join('/')
  fetchBrowse(parentPath)
}

const openDetail = (file) => {
  if (file.id) {
    // 保存当前路径，用于返回
    sessionStorage.setItem('lastBrowsePath', currentPath.value)
    sessionStorage.setItem('lastViewMode', viewMode.value)
    router.push(`/movie/${file.id}`)
  } else {
    alert('该视频尚未入库，请先扫描')
  }
}

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

onMounted(() => {
  // 检查 url 参数
  if (route.query.mode === 'folder') {
    viewMode.value = 'folder'
    const path = route.query.path || ''
    fetchBrowse(path)
  } else if (viewMode.value === 'folder') {
    fetchBrowse()
  } else {
    fetchMovies()
  }
})
</script>

<style scoped>
.movie-list {
  padding: 20px 40px;
}

.header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.mode-switch {
  display: flex;
  gap: 10px;
}

.mode-switch button {
  padding: 8px 16px;
  background: #1a1a2e;
  color: #888;
  border: 1px solid #333;
  border-radius: 6px;
  cursor: pointer;
}

.mode-switch button.active {
  background: #e94560;
  color: white;
  border-color: #e94560;
}

.breadcrumb {
  margin-bottom: 20px;
  font-size: 14px;
}

.breadcrumb .crumb {
  cursor: pointer;
  color: #4a9;
}

.breadcrumb .crumb.disabled {
  color: #666;
  cursor: not-allowed;
}

.breadcrumb .current {
  color: #888;
  margin-left: 10px;
}

.folder-list {
  margin-bottom: 30px;
}

.folder-item {
  display: flex;
  align-items: center;
  padding: 15px;
  background: #1a1a2e;
  border-radius: 8px;
  margin-bottom: 10px;
  cursor: pointer;
  transition: background 0.2s;
}

.folder-item:hover {
  background: #2a2a4e;
}

.folder-item .icon {
  margin-right: 10px;
}

.folder-item .name {
  font-size: 16px;
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