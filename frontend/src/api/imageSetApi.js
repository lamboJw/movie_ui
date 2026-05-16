import axios from 'axios'

const API_BASE = '/api'

export const imageSetApi = {
  get(id) {
    return axios.get(`${API_BASE}/image_set?id=${id}`)
  },

  getList(params = {}) {
    return axios.get(`${API_BASE}/image_sets`, { params })
  }
}
