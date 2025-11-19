import apiClient from './index'

export const systemApi = {
  // 获取更新日志
  getUpdateLogs: () => {
    return apiClient.get('/system.php?action=update_logs')
  },

  // 获取定时日志
  getCronLogs: () => {
    return apiClient.get('/system.php?action=cron_logs')
  },

  // 获取访问日志
  getAccessLogs: () => {
    return apiClient.get('/system.php?action=access_logs')
  },

  // 获取访问统计
  getAccessStats: () => {
    return apiClient.get('/system.php?action=access_stats')
  },

  // 清除访问日志
  clearAccessLog: () => {
    const formData = new FormData()
    formData.append('action', 'clear_access_log')
    return apiClient.post('/system.php', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
  },

  // 触发数据更新
  triggerUpdate: () => {
    const formData = new FormData()
    formData.append('action', 'update')
    return apiClient.post('/system.php', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
  },

  // 获取版本日志
  getVersionLog: () => {
    return apiClient.get('/system.php?action=version_log')
  },

  // 获取使用说明
  getReadme: () => {
    return apiClient.get('/system.php?action=readme')
  }
}
