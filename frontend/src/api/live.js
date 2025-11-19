import apiClient from './index'

export const liveApi = {
  // 获取直播源数据
  getLiveData: () => {
    return apiClient.get('/live.php')
  },

  // 解析源信息
  parseSourceInfo: (url) => {
    return apiClient.get(`/live.php?action=parse_source_info&url=${encodeURIComponent(url)}`)
  },

  // 下载源数据
  downloadSourceData: (url) => {
    const formData = new FormData()
    formData.append('action', 'download_source_data')
    formData.append('url', url)
    return apiClient.post('/live.php', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
  },

  // 删除源配置
  deleteSourceConfig: (id) => {
    const formData = new FormData()
    formData.append('action', 'delete_source_config')
    formData.append('id', id)
    return apiClient.post('/live.php', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
  },

  // 删除未使用的直播源数据
  deleteUnusedLiveData: () => {
    const formData = new FormData()
    formData.append('action', 'delete_unused_live_data')
    return apiClient.post('/live.php', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
  }
}
