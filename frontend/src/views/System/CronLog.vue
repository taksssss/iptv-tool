<template>
  <div class="cron-log">
    <el-card>
      <template #header>
        <div class="card-header">
          <span>定时日志</span>
          <el-button @click="refresh" :loading="systemStore.loading">
            <el-icon><Refresh /></el-icon>
            刷新
          </el-button>
        </div>
      </template>
      
      <LogViewer
        title="定时任务日志"
        :logs="systemStore.cronLogs"
        :loading="systemStore.loading"
        @refresh="refresh"
      />
    </el-card>
  </div>
</template>

<script setup>
import { onMounted } from 'vue'
import { Refresh } from '@element-plus/icons-vue'
import { useSystemStore } from '@/stores/system'
import LogViewer from '@/components/Common/LogViewer.vue'

const systemStore = useSystemStore()

onMounted(async () => {
  await refresh()
})

const refresh = async () => {
  await systemStore.fetchCronLogs()
}
</script>

<style scoped>
.cron-log {
  padding: 20px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
</style>
