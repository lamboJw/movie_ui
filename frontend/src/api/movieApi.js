import axios from 'axios'

const API_BASE = '/api'

export const movieApi = {
  // 获取电影列表
  getMovies(params = {}) {
    return axios.get(`${API_BASE}/movies`, { params })
  },

  // 获取电影详情
  getMovie(id) {
    return axios.get(`${API_BASE}/movie?id=${id}`)
  },

  // 随机获取一部电影
  getRandom() {
    return axios.get(`${API_BASE}/random`)
  },

  // 触发扫描
  scan() {
    return axios.get(`${API_BASE}/scan`)
  },

  // 浏览文件夹
  browse(path = '') {
    return axios.get(`${API_BASE}/browse`, { params: { path } })
  },

  // 获取筛选选项
  getFilters(folder = '') {
    return axios.get(`${API_BASE}/filters`, { params: { folder } })
  },

  // 获取流媒体地址
  getStreamUrl(id) {
    return `${API_BASE}/stream?id=${id}`
  }
}
