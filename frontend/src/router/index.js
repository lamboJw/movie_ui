import { createRouter, createWebHistory } from 'vue-router'
import MovieList from '../views/MovieList.vue'
import MovieDetail from '../views/MovieDetail.vue'
import ImageSetDetail from '../views/ImageSetDetail.vue'

const routes = [
  { path: '/', name: 'home', component: MovieList },
  { path: '/movie/:id', name: 'movie', component: MovieDetail, props: true },
  { path: '/image_set/:id', name: 'image_set', component: ImageSetDetail, props: true }
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

export default router
