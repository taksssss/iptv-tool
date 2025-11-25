import { defineStore } from 'pinia'
import { ref } from 'vue'
import { epgApi } from '@/api/epg'

export const useEpgStore = defineStore('epg', () => {
  const channels = ref([])
  const channelBindEpg = ref({})
  const loading = ref(false)

  async function fetchChannels() {
    loading.value = true
    try {
      channels.value = await epgApi.getChannels()
      return channels.value
    } finally {
      loading.value = false
    }
  }

  async function fetchChannelBindEpg() {
    loading.value = true
    try {
      channelBindEpg.value = await epgApi.getChannelBindEpg()
      return channelBindEpg.value
    } finally {
      loading.value = false
    }
  }

  async function saveChannelBindEpg(data) {
    loading.value = true
    try {
      const response = await epgApi.saveChannelBindEpg(data)
      await fetchChannelBindEpg()
      return response
    } finally {
      loading.value = false
    }
  }

  return {
    channels,
    channelBindEpg,
    loading,
    fetchChannels,
    fetchChannelBindEpg,
    saveChannelBindEpg
  }
})
