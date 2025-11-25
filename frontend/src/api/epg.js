import apiClient from './index'

export const epgApi = {
  // 获取频道列表
  getChannels: () => {
    return apiClient.get('/epg.php?action=get_channel')
  },

  // 获取频道EPG数据
  getEpgByChannel: (channel) => {
    return apiClient.get(`/epg.php?action=get_epg&channel=${encodeURIComponent(channel)}`)
  },

  // 获取频道绑定EPG源
  getChannelBindEpg: () => {
    return apiClient.get('/epg.php?action=get_channel_bind_epg')
  },

  // 保存频道绑定EPG源
  saveChannelBindEpg: (data) => {
    return apiClient.post('/epg.php?action=save_channel_bind_epg', data)
  },

  // 获取频道匹配
  getChannelMatch: () => {
    return apiClient.get('/epg.php?action=get_channel_match')
  },

  // 获取生成列表
  getGenList: () => {
    return apiClient.get('/epg.php?action=get_gen_list')
  }
}
