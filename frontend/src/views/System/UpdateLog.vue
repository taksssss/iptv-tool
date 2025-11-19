<template>
  <div class="update-log">
    <el-card>
      <template #header>
        <div class="card-header">
          <span>更新日志</span>
          <el-button @click="refresh" :loading="systemStore.loading">
            <el-icon><Refresh /></el-icon>
            刷新
          </el-button>
        </div>
      </template>
      
      <LogViewer
        title="EPG 更新日志"
        :logs="systemStore.updateLogs"
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
  await systemStore.fetchUpdateLogs()
}
</script>

<style scoped>
.update-log {
  padding: 20px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
</style>
