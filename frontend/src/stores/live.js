import { defineStore } from 'pinia'
import { ref } from 'vue'
import { liveApi } from '@/api/live'

export const useLiveStore = defineStore('live', () => {
  const liveData = ref([])
  const loading = ref(false)

  async function fetchLiveData() {
    loading.value = true
    try {
      liveData.value = await liveApi.getLiveData()
      return liveData.value
    } finally {
      loading.value = false
    }
  }

  async function deleteSourceConfig(id) {
    loading.value = true
    try {
      const response = await liveApi.deleteSourceConfig(id)
      await fetchLiveData()
      return response
    } finally {
      loading.value = false
    }
  }

  return {
    liveData,
    loading,
    fetchLiveData,
    deleteSourceConfig
  }
})
