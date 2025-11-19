import apiClient from './index'

export const configApi = {
  getConfig: () => {
    return apiClient.get('/config.php')
  },

  updateConfig: (config) => {
    return apiClient.post('/config.php', config)
  },

  getEnv: () => {
    return apiClient.get('/config.php?action=get_env')
  }
}
