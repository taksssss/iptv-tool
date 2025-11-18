// 配置状态管理
import { defineStore } from 'pinia'
import { ref } from 'vue'
import { configApi, type Config } from '@/api/config'

export const useConfigStore = defineStore('config', () => {
  const config = ref<Config | null>(null)
  const loading = ref(false)
  const serverUrl = ref('')

  // 获取配置
  async function fetchConfig() {
    loading.value = true
    try {
      config.value = await configApi.getConfig()
      return config.value
    } finally {
      loading.value = false
    }
  }

  // 更新配置
  async function updateConfig(newConfig: Partial<Config>) {
    loading.value = true
    try {
      const response = await configApi.updateConfig(newConfig)
      // 更新成功后重新获取最新配置
      if (response.success !== false) {
        await fetchConfig()
      }
      return response
    } finally {
      loading.value = false
    }
  }

  // 获取环境信息
  async function fetchEnv() {
    try {
      const env = await configApi.getEnv()
      serverUrl.value = env.server_url
      return env
    } catch (error) {
      console.error('Failed to fetch env:', error)
    }
  }

  return {
    config,
    loading,
    serverUrl,
    fetchConfig,
    updateConfig,
    fetchEnv
  }
})
