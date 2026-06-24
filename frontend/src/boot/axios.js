import { boot } from 'quasar/wrappers'
import axios from 'axios'
import { clearAuthSession, clearLegacyAuth, getAuthToken } from 'src/utils/auth'

clearLegacyAuth()

const api = axios.create({
  baseURL: process.env.API_URL || 'http://localhost:8000/api',
  headers: {
    Accept: 'application/json'
  }
})

api.interceptors.request.use((config) => {
  const token = getAuthToken()

  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }

  return config
})

api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response && error.response.status === 401) {
      clearAuthSession()
      if (window.location.pathname !== '/login') {
        window.location.href = '/login'
      }
    }

    return Promise.reject(error)
  }
)

export default boot(({ app }) => {
  app.config.globalProperties.$axios = axios
  app.config.globalProperties.$api = api
})

export { api }
