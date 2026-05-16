<template>
  <div class="movie-list">
    <div class="header">
      <FilterBar :filterOptions="filterOptions" @search="handleSearch" @filter="handleFilter" />
      <div class="header-actions">
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
        <span class="separator">/</span>
        <span
          class="crumb root"
          @click="navigateToSegment('')"
        >首页</span>
        <template v-for="(seg, i) in breadcrumbSegments" :key="i">
          <span class="separator">/</span>
          <span
            class="crumb segment"
            :class="{ current: i === breadcrumbSegments.length - 1 }"
            @click="i < breadcrumbSegments.length - 1 ? navigateToSegment(seg.path) : undefined"
          >{{ seg.name }}</span>
        </template>
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

        <!-- 图片套图列表 -->
        <div v-if="imageSets.length" class="grid">
          <ImageSetCard
            v-for="set in imageSets"
            :key="set.id"
            :imageSet="set"
            @click="openImageSet(set)"
          />
        </div>

        <div v-if="!folders.length && !files.length && !imageSets.length" class="empty">
          <p>该目录为空</p>
        </div>
      </div>
    </div>

    <!-- 视频列表模式 -->
    <div v-else class="list-view">
      <!-- 面包屑导航 -->
      <div class="breadcrumb" v-if="currentPath">
        <span
          class="crumb"
          :class="{ disabled: !canGoBack }"
          @click="goBack"
        >← 返回</span>
        <span class="separator">/</span>
        <span
          class="crumb root"
          @click="navigateToSegment('')"
        >首页</span>
        <template v-for="(seg, i) in breadcrumbSegments" :key="i">
          <span class="separator">/</span>
          <span
            class="crumb segment"
            :class="{ current: i === breadcrumbSegments.length - 1 }"
            @click="i < breadcrumbSegments.length - 1 ? navigateToSegment(seg.path) : undefined"
          >{{ seg.name }}</span>
        </template>
      </div>

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
          <div class="page-input">
            <input type="number" v-model="jumpPage" min="1" :max="totalPages" @keyup.enter="jumpToPage" />
            <span> / {{ totalPages }}</span>
            <button @click="jumpToPage">跳转</button>
          </div>
          <button :disabled="page >= totalPages" @click="changePage(page + 1)">下一页</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import MovieCard from '../components/MovieCard.vue'
import ImageSetCard from '../components/ImageSetCard.vue'
import FilterBar from '../components/FilterBar.vue'
import { movieApi } from '../api/movieApi'

const router = useRouter()
const route = useRoute()

const movies = ref([])
const loading = ref(true)
const scanning = ref(false)
const page = ref(1)
const totalPages = ref(1)
const filters = ref({})
const jumpPage = ref(1)

const viewMode = ref(localStorage.getItem('viewMode') || 'folder')
const currentPath = ref('')
const canGoBack = ref(false)
const folders = ref([])
const files = ref([])
const imageSets = ref([])

const breadcrumbSegments = computed(() => {
  if (!currentPath.value) return []
  const parts = currentPath.value.split('/').filter(Boolean)
  let acc = ''
  return parts.map(name => {
    acc = acc ? acc + '/' + name : name
    return { name, path: acc }
  })
})

const navigateToSegment = (path) => {
  router.push({ query: { mode: viewMode.value, path } })
}

const filterOptions = ref({
  years: [],
  genres: [],
  directors: [],
  actors: []
})

const loadByRoute = () => {
  const mode = route.query.mode
  const path = route.query.path || ''

  if (mode === 'folder') {
    viewMode.value = 'folder'
    fetchBrowse(path)
  } else if (mode === 'list') {
    viewMode.value = 'list'
    page.value = parseInt(route.query.page) || 1
    fetchMovies(path)
  } else {
    if (viewMode.value === 'folder') {
      fetchBrowse(path)
    } else {
      fetchMovies(path)
    }
  }
}

watch(() => route.query, loadByRoute)

onMounted(() => {
  loadByRoute()
  fetchFilterOptions('')
})

const fetchFilterOptions = async (folder = '') => {
  try {
    const res = await movieApi.getFilters(folder)
    filterOptions.value = res.data
  } catch (e) {
    console.error('获取筛选选项失败', e)
  }
}

const switchMode = (mode) => {
  viewMode.value = mode
  localStorage.setItem('viewMode', mode)
  sessionStorage.setItem('lastViewMode', mode)
  if (mode === 'folder') {
    router.replace({ query: { mode: 'folder', path: currentPath.value } })
    fetchBrowse(currentPath.value)
  } else {
    router.replace({ query: { mode: 'list', path: currentPath.value } })
    fetchMovies(currentPath.value)
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
    imageSets.value = data.image_sets || []
    sessionStorage.setItem('lastBrowsePath', data.current_path)
    fetchFilterOptions(data.current_path || '')
  } catch (e) {
    console.error('获取目录失败', e)
  } finally {
    loading.value = false
  }
}

const enterFolder = (path) => {
  router.push({ query: { mode: 'folder', path } })
}

const goBack = () => {
  if (!canGoBack.value) return
  const parts = currentPath.value.split('/')
  parts.pop()
  const parentPath = parts.join('/')
  router.push({ query: { mode: viewMode.value, path: parentPath } })
}

const openDetail = (file) => {
  if (file.id) {
    sessionStorage.setItem('lastBrowsePath', currentPath.value)
    sessionStorage.setItem('lastViewMode', viewMode.value)
    router.push(`/movie/${file.id}`)
  } else {
    alert('该视频尚未入库，请先扫描')
  }
}

const openImageSet = (imageSet) => {
  sessionStorage.setItem('lastBrowsePath', currentPath.value)
  sessionStorage.setItem('lastViewMode', viewMode.value)
  router.push(`/image_set/${imageSet.id}`)
}

const fetchMovies = async (folder = '') => {
  loading.value = true
  try {
    const params = { page: page.value, limit: 20, ...filters.value }
    if (folder) {
      params.folder = folder
    }
    const res = await movieApi.getMovies(params)
    movies.value = res.data.movies
    totalPages.value = res.data.total_pages
    currentPath.value = folder
    canGoBack.value = folder !== ''
    sessionStorage.setItem('lastBrowsePath', folder)
    fetchFilterOptions(folder)
  } catch (e) {
    console.error('获取电影列表失败', e)
  } finally {
    loading.value = false
  }
}

const changePage = (newPage) => {
  page.value = newPage
  router.push({ query: { mode: 'list', path: currentPath.value, page: newPage } })
}

const jumpToPage = () => {
  const p = parseInt(jumpPage.value)
  if (p >= 1 && p <= totalPages.value) {
    changePage(p)
  } else {
    alert(`请输入1-${totalPages.value}之间的页码`)
  }
}

const handleSearch = (search) => {
  filters.value.search = search
  page.value = 1
  router.replace({ query: { mode: 'list', path: currentPath.value, page: 1, search } })
}

const handleFilter = (newFilters) => {
  filters.value = { ...filters.value, ...newFilters }
  page.value = 1
  router.replace({ query: { mode: 'list', path: currentPath.value, page: 1, ...filters.value } })
}

const triggerScan = async () => {
  if (scanning.value) return
  scanning.value = true
  try {
    await movieApi.scan()
  } catch (e) {
    console.error('扫描失败', e)
  } finally {
    scanning.value = false
    setTimeout(() => {
      loadByRoute()
    }, 2000)
  }
}


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

.header-actions {
  display: flex;
  align-items: center;
  gap: 15px;
}

.header-actions .scan-btn {
  padding: 8px 16px;
  background: #e94560;
  color: white;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-size: 14px;
}

.header-actions .scan-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
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

.breadcrumb .crumb.segment,
.breadcrumb .crumb.root {
  color: #4a9;
  margin-left: 0;
}

.breadcrumb .crumb.segment:hover,
.breadcrumb .crumb.root:hover {
  text-decoration: underline;
}

.breadcrumb .crumb.current {
  color: #aaa;
  cursor: default;
  pointer-events: none;
}

.breadcrumb .separator {
  color: #666;
  margin: 0 6px;
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

.page-input {
  display: flex;
  align-items: center;
  gap: 8px;
}

.page-input input {
  width: 50px;
  padding: 8px;
  background: #1a1a2e;
  color: white;
  border: 1px solid #333;
  border-radius: 6px;
  text-align: center;
}

.page-input input:focus {
  outline: none;
  border-color: #e94560;
}

.page-input button {
  padding: 8px 12px;
}

.pagination button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

@media (max-width: 768px) {
  .movie-list {
    padding: 12px;
  }

  .header {
    flex-direction: column;
    gap: 12px;
    align-items: stretch;
  }

  .header-actions {
    flex-shrink: 0;
  }

  .mode-switch {
    flex-shrink: 0;
  }

  .mode-switch button {
    flex: 0 1 auto;
    text-align: center;
    padding: 8px 12px;
    font-size: 12px;
    white-space: nowrap;
  }

  .breadcrumb {
    font-size: 12px;
    overflow-x: auto;
    white-space: nowrap;
  }

  .folder-item {
    padding: 12px;
  }

  .folder-item .name {
    font-size: 14px;
  }

  .grid {
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
  }

  .pagination {
    flex-wrap: wrap;
    gap: 10px;
  }

  .pagination button {
    padding: 6px 10px;
    font-size: 12px;
  }

  .page-input {
    order: -1;
    width: 100%;
    justify-content: center;
  }

  .page-input input {
    width: 40px;
    padding: 6px;
  }
}
</style>