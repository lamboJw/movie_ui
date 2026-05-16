import { createRouter, createWebHistory } from 'vue-router'
import MovieList from '../views/MovieList.vue'
import MovieDetail from '../views/MovieDetail.vue'
import VideoPlayer from '../components/VideoPlayer.vue'
import ImageSetDetail from '../views/ImageSetDetail.vue'

const routes = [
  { path: '/', component: MovieList },
  { path: '/movie/:id', component: MovieDetail, props: true },
  { path: '/play/:id', component: VideoPlayer, props: true },
  { path: '/image_set/:id', component: ImageSetDetail, props: true }
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

export default router
