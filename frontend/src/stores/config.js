import { defineStore } from 'pinia'
import { ref } from 'vue'
import { configApi } from '@/api/config'

export const useConfigStore = defineStore('config', () => {
  const config = ref(null)
  const loading = ref(false)
  const serverUrl = ref('')

  async function fetchConfig() {
    loading.value = true
    try {
      config.value = await configApi.getConfig()
      return config.value
    } finally {
      loading.value = false
    }
  }

  async function updateConfig(newConfig) {
    loading.value = true
    try {
      const response = await configApi.updateConfig(newConfig)
      if (response.success !== false) {
        await fetchConfig()
      }
      return response
    } finally {
      loading.value = false
    }
  }

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
