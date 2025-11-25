<template>
  <div class="epg-index">
    <el-card>
      <template #header>
        <span>EPG 管理</span>
      </template>
      
      <el-row :gutter="20">
        <el-col :xs="24" :sm="12" :md="6">
          <el-statistic title="总频道数" :value="channels.length">
            <template #suffix>个</template>
          </el-statistic>
        </el-col>
        <el-col :xs="24" :sm="12" :md="6">
          <el-button type="primary" @click="$router.push('/epg/channels')">
            <el-icon><List /></el-icon>
            查看频道列表
          </el-button>
        </el-col>
        <el-col :xs="24" :sm="12" :md="6">
          <el-button type="success" @click="$router.push('/epg/channel-bind')">
            <el-icon><Link /></el-icon>
            频道绑定
          </el-button>
        </el-col>
        <el-col :xs="24" :sm="12" :md="6">
          <el-button @click="$router.push('/epg/generate-list')">
            <el-icon><Document /></el-icon>
            生成列表
          </el-button>
        </el-col>
      </el-row>
    </el-card>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { List, Link, Document } from '@element-plus/icons-vue'
import { useEpgStore } from '@/stores/epg'

const epgStore = useEpgStore()
const channels = ref([])

onMounted(async () => {
  try {
    channels.value = await epgStore.fetchChannels()
  } catch (error) {
    console.error('Failed to fetch channels:', error)
  }
})
</script>

<style scoped>
.epg-index {
  padding: 20px;
}
</style>
