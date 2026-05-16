<template>
  <div class="image-set-detail">
    <div class="header">
      <div class="header-row">
        <button class="back-btn" @click="$router.back()">← 返回</button>
        <h2 class="title">{{ imageSet.title }}</h2>
      </div>
      <div class="header-row controls-row">
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
    </div>

    <div v-if="loading" class="loading">加载中...</div>
    <div v-else-if="!imageSet.images?.length" class="empty">
      <p>暂无图片</p>
    </div>

      <!-- 轮播模式 -->
    <div
      v-else-if="viewMode === 'slider'"
      class="slider-view"
      @touchstart="onSliderTouchStart"
      @touchmove="onSliderTouchMove"
      @touchend="onSliderTouchEnd"
      @mousemove="showNav"
      @mouseleave="startNavHide"
    >
      <div class="slider-image-area" :style="{ maxWidth: imageWidth + '%' }">
        <div class="slider-stage" ref="stageRef" @transitionend="onTrackEnd">
          <div class="slider-track" :style="trackStyle">
            <div class="slider-item" v-for="item in slideItems" :key="item.key">
              <img :src="item.url" @error="handleImageError" />
            </div>
          </div>
        </div>
      </div>

      <button
        class="nav-btn prev"
        :class="{ hidden: !navVisible }"
        @click="prevImage"
        :disabled="currentIndex === 0"
      >‹</button>
      <button
        class="nav-btn next"
        :class="{ hidden: !navVisible }"
        @click="nextImage"
        :disabled="currentIndex >= imageSet.images.length - 1"
      >›</button>

      <div class="slider-footer" :class="{ hidden: !navVisible }">
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
const imageWidth = ref(50)

const waterfallRef = ref(null)
const displayedImages = ref([])
const loadingMore = ref(false)
const noMore = ref(false)
const pageSize = 20

const navVisible = ref(true)
let navHideTimer = null
let touchStartX = 0
let touchStartY = 0
let swiping = false

const stageRef = ref(null)
const dragOffset = ref(0)
const isSnapping = ref(false)
const snapTarget = ref(0)

const slideItems = computed(() => {
  const images = imageSet.value.images || []
  const idx = currentIndex.value
  const items = []
  if (idx > 0) items.push({ key: 'p' + idx, url: images[idx - 1] })
  items.push({ key: idx, url: images[idx] })
  if (idx < images.length - 1) items.push({ key: 'n' + idx, url: images[idx + 1] })
  return items
})

const trackStyle = computed(() => {
  const ci = slideItems.value.findIndex(i => i.key === currentIndex.value)
  const base = -(ci >= 0 ? ci : 0) * 100
  return {
    transform: `translateX(${base + dragOffset.value}%)`,
    transition: isSnapping.value ? 'transform .35s cubic-bezier(0.25, 0.46, 0.45, 0.94)' : 'none'
  }
})

function showNav() {
  navVisible.value = true
  startNavHide()
}

function startNavHide() {
  if (navHideTimer) clearTimeout(navHideTimer)
  navHideTimer = setTimeout(() => {
    navVisible.value = false
  }, 2000)
}

function onSliderTouchStart(e) {
  touchStartX = e.touches[0].clientX
  touchStartY = e.touches[0].clientY
  swiping = false
  isSnapping.value = false
  showNav()
}

function onSliderTouchMove(e) {
  if (!touchStartX) return
  const dx = e.touches[0].clientX - touchStartX
  const dy = e.touches[0].clientY - touchStartY
  if (Math.abs(dx) > 10 && Math.abs(dx) > Math.abs(dy)) {
    swiping = true
    e.preventDefault()
    const rect = stageRef.value?.getBoundingClientRect()
    if (rect) {
      dragOffset.value = (dx / rect.width) * 100
    }
  }
}

function onSliderTouchEnd() {
  if (!swiping || !touchStartX) {
    touchStartX = 0
    return
  }
  if (navHideTimer) clearTimeout(navHideTimer)

  const absDrag = Math.abs(dragOffset.value)
  if (absDrag > 30) {
    if (dragOffset.value > 0 && currentIndex.value > 0) {
      isSnapping.value = true
      dragOffset.value = 100
      snapTarget.value = -1
    } else if (dragOffset.value < 0 && currentIndex.value < imageSet.value.images.length - 1) {
      isSnapping.value = true
      dragOffset.value = -100
      snapTarget.value = 1
    } else {
      isSnapping.value = true
      dragOffset.value = 0
      snapTarget.value = 0
    }
  } else {
    isSnapping.value = true
    dragOffset.value = 0
    snapTarget.value = 0
  }
  touchStartX = 0
  touchStartY = 0
  swiping = false
  showNav()
}

function onTrackEnd() {
  if (!isSnapping.value) return
  isSnapping.value = false
  dragOffset.value = 0
  if (snapTarget.value === -1) {
    currentIndex.value--
  } else if (snapTarget.value === 1) {
    currentIndex.value++
  }
  snapTarget.value = 0
}

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
    isSnapping.value = true
    dragOffset.value = 100
    snapTarget.value = -1
  }
}

const nextImage = () => {
  if (currentIndex.value < imageSet.value.images.length - 1) {
    isSnapping.value = true
    dragOffset.value = -100
    snapTarget.value = 1
  }
}

const goToImage = (idx) => {
  const diff = idx - currentIndex.value
  if (diff === 1) {
    nextImage()
  } else if (diff === -1) {
    prevImage()
  } else if (diff !== 0) {
    currentIndex.value = idx
    dragOffset.value = 0
    isSnapping.value = false
  }
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
  min-height: 100vh;
  background: #0f0f1a;
}

.header {
  padding: 20px 40px;
  margin-bottom: 20px;
}

.header-row {
  display: flex;
  align-items: center;
  gap: 12px;
}

.header-row + .header-row {
  margin-top: 8px;
}

.controls-row {
  gap: 16px;
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
  padding: 0 20px;
  position: relative;
  touch-action: pan-y pinch-zoom;
}

.slider-image-area {
  width: 100%;
  padding: 0;
  overflow: hidden;
  display: flex;
  justify-content: center;
}

.slider-stage {
  width: 100%;
  overflow: hidden;
}

.slider-track {
  display: flex;
}

.slider-item {
  min-width: 100%;
}

.slider-item img {
  display: block;
  width: 100%;
  height: auto;
  object-fit: contain;
}

.nav-btn {
  position: fixed;
  top: 50%;
  transform: translateY(-50%);
  z-index: 10;
  width: 60px;
  height: 120px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(0, 0, 0, 0.45);
  color: #fff;
  border: none;
  font-size: 36px;
  cursor: pointer;
  transition: opacity .3s, background .2s;
  border-radius: 4px;
}

.nav-btn:hover:not(:disabled) {
  background: rgba(233, 69, 96, 0.7);
}

.nav-btn.prev {
  left: 0;
}

.nav-btn.next {
  right: 0;
}

.nav-btn.hidden {
  opacity: 0;
  pointer-events: none;
}

.nav-btn:disabled {
  opacity: 0.15;
  cursor: not-allowed;
}

.slider-footer {
  width: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 12px 20px 16px;
  background: linear-gradient(transparent, rgba(0,0,0,.5));
  transition: opacity .3s;
}

.slider-footer.hidden {
  opacity: 0;
  pointer-events: none;
}

.thumbnails {
  display: flex;
  gap: 8px;
  overflow-x: auto;
  max-width: 100%;
  padding: 4px 10px;
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
  cursor: pointer;
}

.waterfall-item img {
  width: v-bind(imageWidth + '%');
  max-width: 100%;
  margin: 0 auto;
  display: block;
  transition: transform 0.2s;
}

.waterfall-item:hover img {
  transform: scale(1.05);
}

.loading-more, .no-more {
  text-align: center;
  padding: 20px;
  color: #888;
}

@media (max-width: 768px) {
  .header {
    padding: 10px 12px;
    margin-bottom: 10px;
  }

  .header-row {
    gap: 8px;
  }

  .header-row + .header-row {
    margin-top: 6px;
  }

  .controls-row {
    gap: 10px;
  }

  .back-btn {
    padding: 4px 8px;
    font-size: 11px;
    white-space: nowrap;
    flex-shrink: 0;
  }

  .title {
    font-size: 13px;
    flex: 1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .mode-toggle {
    gap: 4px;
  }

  .width-toggle {
    gap: 3px;
    margin-left: 0;
  }

  .mode-toggle button, .width-toggle button {
    padding: 4px 7px;
    font-size: 10px;
  }

  .slider-view {
    padding: 0 10px;
  }

  .nav-btn {
    width: 40px;
    height: 80px;
    font-size: 24px;
  }

  .thumbnails img {
    width: 60px;
    height: 60px;
  }

  .counter {
    font-size: 12px;
  }

  .waterfall-view {
    padding: 0 10px;
  }
}
</style>