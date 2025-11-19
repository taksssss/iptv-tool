import apiClient from './index'

export const iconApi = {
  // 获取台标列表
  getIcons: () => {
    return apiClient.get('/icon.php')
  },

  // 上传台标
  uploadIcon: (file, channelName) => {
    const formData = new FormData()
    formData.append('action', 'upload')
    formData.append('icon', file)
    formData.append('channel_name', channelName)
    return apiClient.post('/icon.php', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
  },

  // 删除台标
  deleteIcon: (filename) => {
    const formData = new FormData()
    formData.append('action', 'delete')
    formData.append('filename', filename)
    return apiClient.post('/icon.php', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
  },

  // 删除未使用的台标
  deleteUnusedIcons: () => {
    const formData = new FormData()
    formData.append('action', 'delete_unused')
    return apiClient.post('/icon.php', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
  }
}
