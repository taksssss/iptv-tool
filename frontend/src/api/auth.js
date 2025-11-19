import apiClient from './index'

export const authApi = {
  login: (password) => {
    const formData = new FormData()
    formData.append('action', 'login')
    formData.append('password', password)
    return apiClient.post('/auth.php', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
  },

  checkLoginStatus: () => {
    return apiClient.get('/auth.php')
  },

  changePassword: (params) => {
    const formData = new FormData()
    formData.append('action', 'change_password')
    if (params.old_password) {
      formData.append('old_password', params.old_password)
    }
    formData.append('new_password', params.new_password)
    return apiClient.post('/auth.php', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
  },

  logout: () => {
    const formData = new FormData()
    formData.append('action', 'logout')
    return apiClient.post('/auth.php', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
  }
}
