<template>
  <div class="channel-list">
    <el-card>
      <template #header>
        <div class="card-header">
          <span>频道列表</span>
          <el-input
            v-model="searchText"
            placeholder="搜索频道"
            style="width: 200px"
            clearable
          >
            <template #prefix>
              <el-icon><Search /></el-icon>
            </template>
          </el-input>
        </div>
      </template>
      
      <el-table
        :data="filteredChannels"
        v-loading="epgStore.loading"
        stripe
        style="width: 100%"
      >
        <el-table-column type="index" label="#" width="60" />
        <el-table-column prop="channel" label="频道名称" />
        <el-table-column prop="epg_count" label="节目数量" width="120" />
        <el-table-column label="操作" width="150">
          <template #default="scope">
            <el-button size="small" @click="handleView(scope.row)">
              查看EPG
            </el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-dialog
      v-model="dialogVisible"
      :title="`${currentChannel} - EPG数据`"
      width="80%"
    >
      <div v-loading="loadingEpg">
        <el-table :data="epgData" stripe max-height="400">
          <el-table-column prop="start" label="开始时间" width="180" />
          <el-table-column prop="title" label="节目名称" />
          <el-table-column prop="desc" label="描述" show-overflow-tooltip />
        </el-table>
      </div>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { Search } from '@element-plus/icons-vue'
import { useEpgStore } from '@/stores/epg'
import { epgApi } from '@/api/epg'

const epgStore = useEpgStore()
const searchText = ref('')
const dialogVisible = ref(false)
const currentChannel = ref('')
const epgData = ref([])
const loadingEpg = ref(false)

const filteredChannels = computed(() => {
  if (!searchText.value) return epgStore.channels
  return epgStore.channels.filter(channel =>
    channel.channel.toLowerCase().includes(searchText.value.toLowerCase())
  )
})

onMounted(async () => {
  await epgStore.fetchChannels()
})

const handleView = async (row) => {
  currentChannel.value = row.channel
  dialogVisible.value = true
  loadingEpg.value = true
  
  try {
    const data = await epgApi.getEpgByChannel(row.channel)
    epgData.value = data || []
  } catch (error) {
    console.error('Failed to fetch EPG:', error)
    epgData.value = []
  } finally {
    loadingEpg.value = false
  }
}
</script>

<style scoped>
.channel-list {
  padding: 20px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
</style>
