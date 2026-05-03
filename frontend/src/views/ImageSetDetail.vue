<template>
  <div class="image-set-detail">
    <div class="header">
      <button class="back-btn" @click="$router.back()">← 返回</button>
      <h2 class="title">{{ imageSet.title }}</h2>
      <div class="mode-toggle">
        <button :class="{ active: viewMode === 'slider' }" @click="viewMode = 'slider'">轮播</button>
        <button :class="{ active: viewMode === 'waterfall' }" @click="viewMode = 'waterfall'">流式</button>
      </div>
      <div class="width-toggle">
        <button :class="{ active: imageWidth === 50 }" @click="imageWidth = 50">50%</button>
        <button :class="{ active: imageWidth === 75 }" @click="imageWidth = 75">75%</button>
        <button :class="{ active: imageWidth === 100 }" @click="imageWidth = 100">100%</button>
      </div>
    </div>

    <div v-if="loading" class="loading">加载中...</div>
    <div v-else-if="!imageSet.images?.length" class="empty">
      <p>暂无图片</p>
    </div>

    <!-- 轮播模式 -->
    <div v-else-if="viewMode === 'slider'" class="slider-view">
      <div class="main-image">
        <button class="nav-btn prev" @click="prevImage" :disabled="currentIndex === 0">←</button>
        <img :src="currentImage" :style="{ maxWidth: imageWidth + '%' }" @error="handleImageError" @load="onImageLoad" />
        <button class="nav-btn next" @click="nextImage" :disabled="currentIndex >= imageSet.images.length - 1">→</button>
      </div>
      <div class="thumbnails">
        <img
          v-for="(img, idx) in visibleThumbs"
          :key="idx"
          :src="img"
          :class="{ active: currentIndex === startIndex + idx }"
          @click="goToImage(startIndex + idx)"
        />
      </div>
      <div class="counter">{{ currentIndex + 1 }} / {{ imageSet.images.length }}</div>
    </div>

    <!-- 流式模式 - 懒加载 -->
    <div v-else ref="waterfallRef" class="waterfall-view" @scroll="onScroll">
      <div class="waterfall-grid">
        <div
          v-for="(img, idx) in displayedImages"
          :key="idx"
          class="waterfall-item"
        >
          <img :src="img" @error="handleImageError" loading="lazy" />
        </div>
      </div>
      <div v-if="loadingMore" class="loading-more">加载中...</div>
      <div v-if="noMore" class="no-more">没有更多了</div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { imageSetApi } from '../api/imageSetApi'

const route = useRoute()

const imageSet = ref({})
const loading = ref(true)
const currentIndex = ref(0)
const viewMode = ref('slider')
const imageWidth = ref(100)

const waterfallRef = ref(null)
const displayedImages = ref([])
const loadingMore = ref(false)
const noMore = ref(false)
const pageSize = 20

const currentImage = computed(() => imageSet.value.images?.[currentIndex.value] || '')

const visibleThumbs = computed(() => {
  const thumbs = []
  const start = Math.max(0, currentIndex.value - 3)
  const end = Math.min(imageSet.value.images?.length || 0, currentIndex.value + 4)
  for (let i = start; i < end; i++) {
    thumbs.push(imageSet.value.images[i])
  }
  return thumbs
})

const startIndex = computed(() => Math.max(0, currentIndex.value - 3))

const prevImage = () => {
  if (currentIndex.value > 0) {
    currentIndex.value--
  }
}

const nextImage = () => {
  if (currentIndex.value < imageSet.value.images.length - 1) {
    currentIndex.value++
  }
}

const goToImage = (idx) => {
  currentIndex.value = idx
}

const handleImageError = (e) => {
  e.target.src = 'https://via.placeholder.com/800x600?text=Image+Error'
}

const onImageLoad = (e) => {
}

const loadMoreImages = () => {
  const allImages = imageSet.value.images || []
  const currentCount = displayedImages.value.length

  if (currentCount >= allImages.length) {
    noMore.value = true
    return
  }

  const nextBatch = allImages.slice(currentCount, currentCount + pageSize)
  displayedImages.value = [...displayedImages.value, ...nextBatch]

  if (displayedImages.value.length >= allImages.length) {
    noMore.value = true
  }
}

const onScroll = (e) => {
  const el = waterfallRef.value
  if (!el) return

  const scrollTop = el.scrollTop
  const scrollHeight = el.scrollHeight
  const clientHeight = el.clientHeight

  if (scrollTop + clientHeight >= scrollHeight - 200) {
    if (!loadingMore.value && !noMore.value) {
      loadingMore.value = true
      setTimeout(() => {
        loadMoreImages()
        loadingMore.value = false
      }, 100)
    }
  }
}

const fetchImageSet = async () => {
  loading.value = true
  try {
    const res = await imageSetApi.get(route.params.id)
    imageSet.value = res.data
    displayedImages.value = imageSet.value.images?.slice(0, pageSize) || []
    if (displayedImages.value.length >= (imageSet.value.images?.length || 0)) {
      noMore.value = true
    }
  } catch (e) {
    console.error('获取套图失败', e)
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchImageSet()
})
</script>

<style scoped>
.image-set-detail {
  padding: 20px 40px;
  min-height: 100vh;
  background: #0f0f1a;
}

.header {
  display: flex;
  align-items: center;
  gap: 20px;
  margin-bottom: 20px;
}

.back-btn {
  padding: 8px 16px;
  background: #1a1a2e;
  color: #fff;
  border: 1px solid #333;
  border-radius: 6px;
  cursor: pointer;
}

.title {
  flex: 1;
  font-size: 18px;
  color: #fff;
}

.mode-toggle {
  display: flex;
  gap: 10px;
}

.mode-toggle button {
  padding: 8px 16px;
  background: #1a1a2e;
  color: #888;
  border: 1px solid #333;
  border-radius: 6px;
  cursor: pointer;
}

.mode-toggle button.active {
  background: #e94560;
  color: #fff;
  border-color: #e94560;
}

.width-toggle {
  display: flex;
  gap: 5px;
  margin-left: 10px;
}

.width-toggle button {
  padding: 4px 8px;
  background: #1a1a2e;
  color: #888;
  border: 1px solid #333;
  border-radius: 4px;
  font-size: 12px;
  cursor: pointer;
}

.width-toggle button.active {
  background: #4a9;
  color: #fff;
  border-color: #4a9;
}

.loading, .empty {
  text-align: center;
  padding: 60px;
  color: #888;
}

.slider-view {
  display: flex;
  flex-direction: column;
  align-items: center;
}

.main-image {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  max-width: 90vw;
  max-height: 70vh;
}

.main-image img {
  max-width: 100%;
  max-height: 70vh;
  object-fit: contain;
}

.nav-btn {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  padding: 20px 15px;
  background: rgba(0, 0, 0, 0.5);
  color: #fff;
  border: none;
  font-size: 24px;
  cursor: pointer;
  transition: background 0.2s;
}

.nav-btn:hover:not(:disabled) {
  background: rgba(233, 69, 96, 0.8);
}

.nav-btn.prev {
  left: -60px;
}

.nav-btn.next {
  right: -60px;
}

.nav-btn:disabled {
  opacity: 0.3;
  cursor: not-allowed;
}

.thumbnails {
  display: flex;
  gap: 10px;
  margin-top: 20px;
  overflow-x: auto;
  max-width: 100%;
  padding: 10px;
}

.thumbnails img {
  width: 80px;
  height: 80px;
  object-fit: cover;
  border-radius: 8px;
  cursor: pointer;
  border: 2px solid transparent;
  transition: border-color 0.2s;
}

.thumbnails img.active {
  border-color: #e94560;
}

.counter {
  margin-top: 15px;
  color: #888;
  font-size: 14px;
}

.waterfall-view {
  padding: 0 20px;
  max-height: calc(100vh - 150px);
  overflow-y: auto;
}

.waterfall-grid {
  column-count: 1;
  column-gap: 0;
}

.waterfall-item {
  break-inside: avoid;
  margin-bottom: 15px;
  border-radius: 8px;
  overflow: hidden;
}

.waterfall-item img {
  width: v-bind(imageWidth + '%');
  max-width: 100%;
  margin: 0 auto;
  display: block;
}

.loading-more, .no-more {
  text-align: center;
  padding: 20px;
  color: #888;
}

.nav-btn.prev {
  left: 0;
}

.nav-btn.next {
  right: 0;
}
</style>