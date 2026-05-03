<template>
  <div id="app">
    <header class="header">
      <h1>🎬 电影库</h1>
      <button @click="getRandomMovie" class="random-btn">🎲 随机一部</button>
    </header>
    <router-view></router-view>
  </div>
</template>

<script setup>
import { useRouter } from 'vue-router'

const router = useRouter()

const getRandomMovie = async () => {
  const res = await fetch('/api/random')
  const movie = await res.json()
  if (movie.id) {
    router.push(`/movie/${movie.id}`)
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
