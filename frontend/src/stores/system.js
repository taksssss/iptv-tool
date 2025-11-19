import { defineStore } from 'pinia'
import { ref } from 'vue'
import { systemApi } from '@/api/system'

export const useSystemStore = defineStore('system', () => {
  const updateLogs = ref([])
  const cronLogs = ref([])
  const accessLogs = ref([])
  const loading = ref(false)

  async function fetchUpdateLogs() {
    loading.value = true
    try {
      updateLogs.value = await systemApi.getUpdateLogs()
      return updateLogs.value
    } finally {
      loading.value = false
    }
  }

  async function fetchCronLogs() {
    loading.value = true
    try {
      cronLogs.value = await systemApi.getCronLogs()
      return cronLogs.value
    } finally {
      loading.value = false
    }
  }

  async function fetchAccessLogs() {
    loading.value = true
    try {
      accessLogs.value = await systemApi.getAccessLogs()
      return accessLogs.value
    } finally {
      loading.value = false
    }
  }

  return {
    updateLogs,
    cronLogs,
    accessLogs,
    loading,
    fetchUpdateLogs,
    fetchCronLogs,
    fetchAccessLogs
  }
})
