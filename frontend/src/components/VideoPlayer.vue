<template>
  <div
    class="player-wrapper"
    :class="{ inline }"
    ref="wrapperRef"
    @keydown="onKeydown"
    tabindex="0"
    @mousemove="onMouseMove"
    @mouseleave="startHideTimer"
    @touchstart="onTouchStart"
    @touchmove="onTouchMove"
    @touchend="onTouchEnd"
  >
    <video
      ref="videoRef"
      :src="streamUrl"
      @loadedmetadata="onLoadedMetadata"
      @timeupdate="onTimeUpdate"
      @play="onPlay"
      @pause="onPause"
      @waiting="onWaiting"
      @canplay="onCanPlay"
      @ended="onEnded"
      @progress="onProgress"
      playsinline
      webkit-playsinline
      preload="metadata"
    ></video>

    <!-- 中央状态图标 -->
    <div class="center-icon" v-if="showCenterIcon" :class="{ fadeout: centerIconFade }">
      <span v-if="centerIconType === 'play'" class="center-icon-btn">▶</span>
      <span v-else-if="centerIconType === 'pause'" class="center-icon-btn">⏸</span>
      <span v-else-if="centerIconType === 'loading'" class="spinner"></span>
      <span v-else-if="centerIconType === 'rewind'" class="center-icon-btn bg">⏪</span>
      <span v-else-if="centerIconType === 'forward'" class="center-icon-btn bg">⏩</span>
    </div>

    <!-- 遮挡层（用于捕获触控事件，不遮挡视频点击） -->
    <div class="overlay" :class="{ visible: controlsVisible }" @click="onOverlayClick">
      <!-- 顶部栏 -->
      <div class="top-bar">
        <button class="control-btn back-btn" @click="goBack">← 返回</button>
        <span class="title">{{ title }}</span>
      </div>

      <!-- 底部控制栏 -->
      <div class="bottom-bar" @click.stop>
        <!-- 进度条 -->
        <div class="progress-container" ref="progressRef" @click="seekByClick" @mousedown="startDragSeek" @touchstart.stop.prevent="onProgressTouchStart" @touchmove="onProgressTouchMove" @touchend="onProgressTouchEnd">
          <div class="progress-track">
            <div class="progress-buffered" :style="{ width: bufferedPercent + '%' }"></div>
            <div class="progress-current" :style="{ width: progressPercent + '%' }"></div>
            <div class="progress-thumb" :style="{ left: progressPercent + '%' }"></div>
          </div>
        </div>

        <div class="controls-row">
          <div class="controls-left">
            <button class="control-btn" @click="togglePlay">
              {{ isPlaying ? '⏸' : '▶' }}
            </button>
            <span class="time-display">{{ formatTime(currentTime) }} / {{ formatTime(duration) }}</span>
          </div>
          <div class="controls-right">
            <button class="control-btn speed-btn" @click="toggleSpeedPanel">
              {{ currentSpeed }}×
            </button>
            <button class="control-btn" @click="toggleFullscreen">⛶</button>
          </div>
        </div>
      </div>
    </div>

    <!-- 速度选择面板 -->
    <div class="speed-panel" v-if="showSpeedPanel" @click.stop>
      <div
        v-for="speed in speedOptions"
        :key="speed"
        class="speed-option"
        :class="{ active: currentSpeed === speed }"
        @click="setSpeed(speed)"
      >{{ speed }}×</div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRouter } from 'vue-router'

const props = defineProps({
  id: { type: [String, Number], required: true },
  title: { type: String, default: '' },
  inline: { type: Boolean, default: false }
})

const emit = defineEmits(['close'])
const router = useRouter()
const wrapperRef = ref(null)
const videoRef = ref(null)
const progressRef = ref(null)

const streamUrl = ref('')
const currentTime = ref(0)
const duration = ref(0)
const isPlaying = ref(false)
const isLoading = ref(true)
const isFullscreen = ref(false)
const controlsVisible = ref(true)
const showCenterIcon = ref(false)
const centerIconType = ref('')
const centerIconFade = ref(false)

const bufferedPercent = ref(0)
const currentSpeed = ref(1)
const showSpeedPanel = ref(false)
const speedOptions = [0.5, 0.75, 1, 1.25, 1.5, 1.75, 2, 3]

const progressPercent = computed(() => {
  if (!duration.value) return 0
  return (currentTime.value / duration.value) * 100
})

let hideTimer = null
let longPressTimer = null
let isLongPressing = false
let longPressDirection = null // 'rewind' | 'forward'
let longPressInterval = null
let touchStartX = 0
let touchStartY = 0
let touchStartTime = 0
let lastTapTime = 0
let isSwiping = false
let swipeStartTime = 0
let isDraggingSeek = false
let skipNextTap = false
let progressTouchId = null
let rewindRAF = null
let rewindStartVTime = 0
let rewindStartWallTime = 0
const REWIND_SPEED = 3

onMounted(() => {
  streamUrl.value = `/api/stream?id=${props.id}`
  wrapperRef.value?.focus()
  showControls()
})

onUnmounted(() => {
  clearTimers()
})

function clearTimers() {
  if (hideTimer) clearTimeout(hideTimer)
  if (longPressTimer) clearTimeout(longPressTimer)
  if (longPressInterval) clearInterval(longPressInterval)
  if (rewindRAF) cancelAnimationFrame(rewindRAF)
  rewindRAF = null
}

function showControls() {
  controlsVisible.value = true
  startHideTimer()
}

function startHideTimer() {
  if (hideTimer) clearTimeout(hideTimer)
  hideTimer = setTimeout(() => {
    if (isPlaying.value) {
      controlsVisible.value = false
      showSpeedPanel.value = false
    }
  }, 3000)
}

function onMouseMove() {
  showControls()
}

function onKeydown(e) {
  const step = 10
  if (e.key === 'ArrowLeft') {
    e.preventDefault()
    seekRelative(-step)
  } else if (e.key === 'ArrowRight') {
    e.preventDefault()
    seekRelative(step)
  } else if (e.key === ' ') {
    e.preventDefault()
    togglePlay()
  }
}

function seekRelative(seconds) {
  const video = videoRef.value
  if (!video) return
  video.currentTime = Math.max(0, Math.min(video.duration, video.currentTime + seconds))
}

function doRewind(timestamp) {
  if (!isLongPressing || longPressDirection !== 'rewind') return
  const video = videoRef.value
  if (!video) return
  const elapsed = (timestamp - rewindStartWallTime) / 1000
  const targetTime = Math.max(0, rewindStartVTime - elapsed * REWIND_SPEED)
  video.currentTime = targetTime
  rewindRAF = requestAnimationFrame(doRewind)
}

function togglePlay() {
  const video = videoRef.value
  if (!video) return
  if (video.paused) {
    video.play()
  } else {
    video.pause()
  }
}

async function toggleFullscreen() {
  const el = wrapperRef.value
  if (!document.fullscreenElement) {
    await el?.requestFullscreen?.()
    isFullscreen.value = true
    const video = videoRef.value
    if (video?.videoWidth && screen?.orientation?.lock) {
      try {
        await screen.orientation.lock(
          video.videoWidth > video.videoHeight ? 'landscape' : 'portrait'
        )
      } catch (_) {}
    }
  } else {
    await document.exitFullscreen?.()
    isFullscreen.value = false
    screen?.orientation?.unlock?.()
  }
}

function goBack() {
  if (props.inline) {
    stopPlayback()
    emit('close')
  } else {
    router.back()
  }
}

function stopPlayback() {
  const video = videoRef.value
  if (video) {
    video.pause()
    video.removeAttribute('src')
    video.load()
  }
}

function toggleSpeedPanel() {
  showSpeedPanel.value = !showSpeedPanel.value
}

function setSpeed(speed) {
  currentSpeed.value = speed
  if (videoRef.value) {
    videoRef.value.playbackRate = speed
  }
  showSpeedPanel.value = false
}

function onLoadedMetadata() {
  duration.value = videoRef.value?.duration || 0
}

function onTimeUpdate() {
  currentTime.value = videoRef.value?.currentTime || 0
}

function onPlay() {
  isPlaying.value = true
  isLoading.value = false
  showCenterIconWithFade('play')
  startHideTimer()
}

function onPause() {
  isPlaying.value = false
  showCenterIconWithFade('pause')
  controlsVisible.value = true
}

function onWaiting() {
  isLoading.value = true
  setCenterIcon('loading')
}

function onCanPlay() {
  isLoading.value = false
  hideCenterIcon()
}

function onEnded() {
  isPlaying.value = false
  controlsVisible.value = true
  showCenterIconWithFade('pause')
}

function onProgress() {
  const video = videoRef.value
  if (!video || !video.buffered.length) return
  const bufferedEnd = video.buffered.end(video.buffered.length - 1)
  bufferedPercent.value = duration.value ? (bufferedEnd / duration.value) * 100 : 0
}

function setCenterIcon(type) {
  centerIconType.value = type
  showCenterIcon.value = true
  centerIconFade.value = false
}

function hideCenterIcon() {
  showCenterIcon.value = false
}

function showCenterIconWithFade(type) {
  centerIconType.value = type
  showCenterIcon.value = true
  centerIconFade.value = false
  setTimeout(() => {
    centerIconFade.value = true
    setTimeout(() => {
      showCenterIcon.value = false
    }, 300)
  }, 500)
}

function formatTime(t) {
  if (!t || isNaN(t)) return '0:00'
  const m = Math.floor(t / 60)
  const s = Math.floor(t % 60)
  return m + ':' + (s < 10 ? '0' : '') + s
}

function seekByClick(e) {
  const rect = progressRef.value?.getBoundingClientRect()
  if (!rect || !videoRef.value || !duration.value) return
  const ratio = (e.clientX - rect.left) / rect.width
  videoRef.value.currentTime = ratio * duration.value
}

function startDragSeek(e) {
  isDraggingSeek = true
  document.addEventListener('mousemove', onDragSeekMove)
  document.addEventListener('mouseup', onDragSeekEnd)
  seekByClick(e)
}

function onDragSeekMove(e) {
  if (!isDraggingSeek) return
  const rect = progressRef.value?.getBoundingClientRect()
  if (!rect || !videoRef.value || !duration.value) return
  let ratio = (e.clientX - rect.left) / rect.width
  ratio = Math.max(0, Math.min(1, ratio))
  videoRef.value.currentTime = ratio * duration.value
}

function onDragSeekEnd() {
  isDraggingSeek = false
  document.removeEventListener('mousemove', onDragSeekMove)
  document.removeEventListener('mouseup', onDragSeekEnd)
}

// ---------- 进度条触摸 ----------
function onProgressTouchStart(e) {
  const touch = e.touches[0]
  progressTouchId = touch.identifier
  seekByTouch(touch)
}

function onProgressTouchMove(e) {
  const touch = Array.from(e.changedTouches).find(t => t.identifier === progressTouchId)
  if (!touch) return
  seekByTouch(touch)
}

function onProgressTouchEnd() {
  progressTouchId = null
}

function seekByTouch(touch) {
  const rect = progressRef.value?.getBoundingClientRect()
  if (!rect || !videoRef.value || !duration.value) return
  let ratio = (touch.clientX - rect.left) / rect.width
  ratio = Math.max(0, Math.min(1, ratio))
  videoRef.value.currentTime = ratio * duration.value
}

// ---------- 移动端手势 ----------
function onTouchStart(e) {
  const touch = e.touches[0]
  touchStartX = touch.clientX
  touchStartY = touch.clientY
  touchStartTime = Date.now()
  isSwiping = false
  isLongPressing = false
  clearTimers()

  // 双击检测
  const now = Date.now()
  if (now - lastTapTime < 300) {
    lastTapTime = 0
    skipNextTap = true
    togglePlay()
    return
  }
  lastTapTime = now

  // 长按检测
  const rect = wrapperRef.value?.getBoundingClientRect()
  if (!rect) return
  const isLeft = touch.clientX < rect.left + rect.width / 2

  longPressTimer = setTimeout(() => {
    isLongPressing = true
    longPressDirection = isLeft ? 'rewind' : 'forward'
    const video = videoRef.value
    if (!video) return

    if (isLeft) {
      setCenterIcon('rewind')
      rewindStartVTime = video.currentTime
      rewindStartWallTime = performance.now()
      rewindRAF = requestAnimationFrame(doRewind)
    } else {
      // 快进：3倍速播放
      setCenterIcon('forward')
      video.playbackRate = 3
    }
  }, 300)

  showControls()
}

function onTouchMove(e) {
  if (!touchStartX) return
  const touch = e.touches[0]
  const deltaX = touch.clientX - touchStartX
  const deltaY = touch.clientY - touchStartY

  // 水平滑动超过阈值 → 拖拽进度
  if (Math.abs(deltaX) > 10 && Math.abs(deltaX) > Math.abs(deltaY)) {
    if (!isSwiping) {
      isSwiping = true
      swipeStartTime = Date.now()
      // 取消长按
      if (longPressTimer) clearTimeout(longPressTimer)
      if (isLongPressing) {
        isLongPressing = false
        if (longPressInterval) clearInterval(longPressInterval)
        longPressInterval = null
        if (rewindRAF) {
          cancelAnimationFrame(rewindRAF)
          rewindRAF = null
        }
        const v = videoRef.value
        if (v) v.playbackRate = currentSpeed.value
      }
    }

    if (isSwiping && videoRef.value && duration.value) {
      const rect = wrapperRef.value?.getBoundingClientRect()
      if (rect) {
        const ratio = (touch.clientX - rect.left) / rect.width
        const clampedRatio = Math.max(0, Math.min(1, ratio))
        videoRef.value.currentTime = clampedRatio * duration.value
        // 显示进度反馈
        setCenterIcon(clampedRatio > 0.5 ? 'forward' : 'rewind')
      }
    }
  }
}

function onTouchEnd() {
  if (longPressTimer) clearTimeout(longPressTimer)

  if (isLongPressing) {
    isLongPressing = false
    if (longPressInterval) {
      clearInterval(longPressInterval)
      longPressInterval = null
    }
    if (rewindRAF) {
      cancelAnimationFrame(rewindRAF)
      rewindRAF = null
    }
    const video = videoRef.value
    if (video) {
      video.playbackRate = currentSpeed.value
    }
    hideCenterIcon()
    return
  }

  if (isSwiping) {
    isSwiping = false
    showControls()
    setTimeout(hideCenterIcon, 500)
    return
  }

  // 单击显示/隐藏控制栏
  if (skipNextTap) {
    skipNextTap = false
    return
  }
  if (controlsVisible.value && isPlaying.value) {
    controlsVisible.value = false
    showSpeedPanel.value = false
  } else {
    showControls()
  }

  hideCenterIcon()
}

function onOverlayClick() {
  showSpeedPanel.value = false
  if (!isPlaying.value && !controlsVisible.value) {
    showControls()
  }
}
</script>

<style scoped>
.player-wrapper {
  position: relative;
  width: 100%;
  height: 100vh;
  background: #000;
  overflow: hidden;
  outline: none;
  user-select: none;
  -webkit-user-select: none;
}

.player-wrapper.inline {
  height: 0;
  padding-bottom: 56.25%;
  border-radius: 12px;
}

.player-wrapper.inline video,
.player-wrapper.inline .overlay {
  position: absolute;
  inset: 0;
  border-radius: 12px;
}

.player-wrapper.inline .overlay {
  border-radius: 12px;
}

.player-wrapper video {
  width: 100%;
  height: 100%;
  object-fit: contain;
}

.overlay {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  background: linear-gradient(transparent 60%, rgba(0,0,0,.7));
  opacity: 0;
  transition: opacity .3s ease;
  pointer-events: none;
}

.overlay.visible {
  opacity: 1;
  pointer-events: auto;
}

.top-bar {
  display: flex;
  align-items: center;
  padding: 16px;
  gap: 12px;
  background: linear-gradient(rgba(0,0,0,.6), transparent);
}

.top-bar .title {
  flex: 1;
  color: #fff;
  font-size: 15px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.bottom-bar {
  padding: 0 16px 12px;
}

.progress-container {
  cursor: pointer;
  padding: 8px 0;
  margin-bottom: 4px;
}

.progress-track {
  position: relative;
  height: 4px;
  background: rgba(255,255,255,.2);
  border-radius: 2px;
  transition: height .15s;
}

.progress-container:hover .progress-track {
  height: 6px;
}

.progress-buffered {
  position: absolute;
  top: 0;
  left: 0;
  height: 100%;
  background: rgba(255,255,255,.3);
  border-radius: 2px;
}

.progress-current {
  position: absolute;
  top: 0;
  left: 0;
  height: 100%;
  background: #e94560;
  border-radius: 2px;
}

.progress-thumb {
  position: absolute;
  top: 50%;
  width: 14px;
  height: 14px;
  border-radius: 50%;
  background: #e94560;
  transform: translate(-50%, -50%);
  opacity: 0;
  transition: opacity .15s;
}

.progress-container:hover .progress-thumb {
  opacity: 1;
}

.controls-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.controls-left, .controls-right {
  display: flex;
  align-items: center;
  gap: 10px;
}

.control-btn {
  background: none;
  border: none;
  color: #fff;
  font-size: 20px;
  cursor: pointer;
  padding: 4px 8px;
  border-radius: 4px;
  transition: background .15s;
}

@media (hover: hover) {
  .control-btn:hover {
    background: rgba(255,255,255,.15);
  }
}

.speed-btn {
  font-size: 14px !important;
  font-weight: 600;
  min-width: 44px;
  text-align: center;
}

.time-display {
  color: #ccc;
  font-size: 13px;
  font-variant-numeric: tabular-nums;
}

.speed-panel {
  position: absolute;
  bottom: 80px;
  right: 16px;
  background: rgba(0,0,0,.85);
  border-radius: 8px;
  overflow: hidden;
  z-index: 10;
}

.speed-option {
  padding: 8px 20px;
  color: #aaa;
  font-size: 14px;
  cursor: pointer;
  text-align: center;
  transition: background .15s, color .15s;
}

.speed-option:hover {
  background: rgba(255,255,255,.1);
  color: #fff;
}

.speed-option.active {
  color: #e94560;
  font-weight: 700;
}

/* 中央图标 */
.center-icon {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  pointer-events: none;
  transition: opacity .3s ease;
  z-index: 5;
}

.center-icon.fadeout {
  opacity: 0;
}

.center-icon-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 96px;
  height: 96px;
  font-size: 48px;
  color: rgba(255,255,255,.9);
}

.center-icon-btn.bg {
  background: rgba(0,0,0,.55);
  border-radius: 50%;
}

.spinner {
  display: inline-block;
  width: 48px;
  height: 48px;
  border: 4px solid rgba(255,255,255,.2);
  border-top-color: #e94560;
  border-radius: 50%;
  animation: spin .8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* 全屏适配 */
:fullscreen .player-wrapper {
  width: 100vw;
  height: 100vh;
}
</style>
