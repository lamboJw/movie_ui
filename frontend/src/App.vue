<template>
  <div id="app">
    <header class="header">
      <h1>🎬 电影库</h1>
      <div class="header-actions">
        <button @click="triggerScan" :disabled="scanning" class="scan-btn">
          {{ scanning ? '扫描中...' : '🔄 扫描' }}
        </button>
        <button @click="getRandomMovie" class="random-btn">🎲 随机一部</button>
      </div>
    </header>
    <router-view></router-view>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()
const scanning = ref(false)

const getRandomMovie = async () => {
  const res = await fetch('/api/random')
  const movie = await res.json()
  if (movie.id) {
    router.push(`/movie/${movie.id}`)
  }
}

const triggerScan = async () => {
  if (scanning.value) return
  scanning.value = true
  try {
    await fetch('/api/scan')
    window.location.reload()
  } catch (e) {
    console.error('扫描失败', e)
  } finally {
    scanning.value = false
  }
}
</script>

<style>
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  background: #0f0f1a;
  color: #e0e0e0;
}

.header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 40px;
  background: linear-gradient(135deg, #1a1a2e, #16213e);
  box-shadow: 0 2px 10px rgba(0,0,0,0.3);
}

.header h1 {
  font-size: 24px;
  color: #e94560;
}

.header-actions {
  display: flex;
  gap: 10px;
}

.scan-btn {
  padding: 10px 20px;
  background: #1a1a2e;
  color: #888;
  border: 1px solid #333;
  border-radius: 8px;
  cursor: pointer;
  font-size: 14px;
}

.scan-btn:hover:not(:disabled) {
  background: #2a2a3e;
  color: #fff;
}

.scan-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.random-btn {
  padding: 10px 20px;
  background: linear-gradient(135deg, #e94560, #c23152);
  color: white;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-size: 14px;
  transition: transform 0.2s;
}

.random-btn:hover {
  transform: scale(1.05);
}

#app {
  min-height: 100vh;
}
</style>
