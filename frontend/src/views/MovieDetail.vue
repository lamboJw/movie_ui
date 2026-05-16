<template>
  <div class="movie-detail" v-if="movie">
    <button @click="$router.back()" class="back-btn">← 返回</button>

    <div class="player-area" v-if="playing">
      <VideoPlayer :id="route.params.id" :title="movie.title" inline @close="closePlayer" />
    </div>

    <div class="detail-content">
      <div class="poster" v-show="!playing">
        <img :src="movie.thumb || 'https://via.placeholder.com/300x450?text=No+Image'" :alt="movie.title">
        <button @click="playMovie" class="play-btn">▶ 播放</button>
      </div>

      <div class="info">
        <h1>{{ movie.title }}</h1>
        <p v-if="movie.original_title" class="original-title">{{ movie.original_title }}</p>
        <p v-if="movie.folder" class="folder">📁 {{ movie.folder }}</p>

        <div class="meta">
          <span v-if="movie.year">{{ movie.year }}</span>
          <span v-if="movie.runtime">{{ movie.runtime }}分钟</span>
          <span v-if="movie.rating">⭐ {{ movie.rating }}/10</span>
        </div>

        <div class="genres" v-if="movie.genres && movie.genres.length">
          <span v-for="genre in movie.genres" :key="genre" class="genre-tag">{{ genre }}</span>
        </div>

        <p v-if="movie.director" class="director">导演：{{ movie.director }}</p>

        <div class="plot" v-if="movie.plot">
          <h3>剧情简介</h3>
          <p>{{ movie.plot }}</p>
        </div>

        <div class="actors" v-if="movie.actors && movie.actors.length">
          <h3>演员表</h3>
          <div class="actor-list">
            <div v-for="actor in movie.actors" :key="actor.name" class="actor-item">
              <span class="actor-name">{{ actor.name }}</span>
              <span v-if="actor.role" class="actor-role">{{ actor.role }}</span>
            </div>
          </div>
        </div>

        <p class="date-added">添加日期：{{ movie.date_added }}</p>
      </div>
    </div>
  </div>
  <div v-else class="loading">加载中...</div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { movieApi } from '../api/movieApi'
import VideoPlayer from '../components/VideoPlayer.vue'

const route = useRoute()
const router = useRouter()
const movie = ref(null)
const playing = ref(false)

onMounted(async () => {
  try {
    const res = await movieApi.getMovie(route.params.id)
    movie.value = res.data
  } catch (e) {
    console.error('获取电影详情失败', e)
  }
})

const playMovie = () => {
  playing.value = true
}

const closePlayer = () => {
  playing.value = false
}
</script>

<style scoped>
.movie-detail {
  padding: 20px 40px;
  max-width: 1200px;
  margin: 0 auto;
}

.back-btn {
  padding: 8px 16px;
  background: #1a1a2e;
  color: white;
  border: 1px solid #333;
  border-radius: 6px;
  cursor: pointer;
  margin-bottom: 20px;
}

.detail-content {
  display: flex;
  gap: 30px;
  margin-top: 20px;
}

.poster {
  position: relative;
}

.poster img {
  width: 300px;
  border-radius: 12px;
  box-shadow: 0 4px 20px rgba(0,0,0,0.5);
  display: block;
}

.play-btn {
  position: absolute;
  bottom: 16px;
  left: 50%;
  transform: translateX(-50%);
  padding: 10px 28px;
  background: #e94560;
  color: #fff;
  border: none;
  border-radius: 8px;
  font-size: 16px;
  font-weight: 600;
  cursor: pointer;
  transition: background .2s, transform .2s;
  white-space: nowrap;
}

.play-btn:hover {
  background: #d63850;
  transform: translateX(-50%) scale(1.05);
}

.info {
  flex: 1;
}

.info h1 {
  font-size: 32px;
  margin-bottom: 5px;
}

.original-title {
  color: #888;
  margin-bottom: 10px;
}

.folder {
  color: #4a9;
  font-size: 14px;
  margin-bottom: 15px;
}

.meta {
  display: flex;
  gap: 15px;
  margin: 15px 0;
  color: #aaa;
}

.genres {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  margin: 15px 0;
}

.genre-tag {
  padding: 4px 12px;
  background: #e94560;
  border-radius: 20px;
  font-size: 12px;
}

.director {
  color: #aaa;
  margin: 10px 0;
}

.plot {
  margin: 20px 0;
}

.plot h3, .actors h3 {
  margin-bottom: 10px;
  color: #e94560;
}

.plot p {
  line-height: 1.8;
  color: #ccc;
}

.actor-list {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 10px;
  margin-top: 10px;
}

.actor-item {
  padding: 8px;
  background: #1a1a2e;
  border-radius: 6px;
}

.actor-name {
  display: block;
  font-weight: bold;
  font-size: 14px;
}

.actor-role {
  display: block;
  font-size: 12px;
  color: #888;
  margin-top: 4px;
}

.date-added {
  margin-top: 20px;
  color: #666;
  font-size: 14px;
}

.loading {
  text-align: center;
  padding: 60px;
  color: #888;
}

.player-area {
  margin-bottom: 24px;
}

@media (max-width: 768px) {
  .movie-detail {
    padding: 12px;
  }

  .back-btn {
    padding: 6px 12px;
    font-size: 12px;
  }

  .detail-content {
    flex-direction: column;
    gap: 20px;
  }

  .poster img {
    width: 100%;
    max-width: 200px;
    margin: 0 auto;
    display: block;
  }

  .play-btn {
    font-size: 14px;
    padding: 8px 20px;
  }

  .info h1 {
    font-size: 22px;
  }

  .meta {
    flex-wrap: wrap;
    gap: 10px;
  }

  .actor-list {
    grid-template-columns: 1fr;
  }
}
</style>
