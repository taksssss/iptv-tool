<template>
  <div class="access-log">
    <el-card>
      <template #header>
        <div class="card-header">
          <span>访问日志</span>
          <div>
            <el-button @click="refresh" :loading="systemStore.loading">
              <el-icon><Refresh /></el-icon>
              刷新
            </el-button>
            <el-button type="danger" @click="handleClear">
              清除日志
            </el-button>
          </div>
        </div>
      </template>
      
      <LogViewer
        title="访问日志"
        :logs="systemStore.accessLogs"
        :loading="systemStore.loading"
        @refresh="refresh"
      />
    </el-card>
  </div>
</template>

<script setup>
import { onMounted } from 'vue'
import { Refresh } from '@element-plus/icons-vue'
import { ElMessageBox, ElMessage } from 'element-plus'
import { useSystemStore } from '@/stores/system'
import { systemApi } from '@/api/system'
import LogViewer from '@/components/Common/LogViewer.vue'

const systemStore = useSystemStore()

onMounted(async () => {
  await refresh()
})

const refresh = async () => {
  await systemStore.fetchAccessLogs()
}

const handleClear = async () => {
  try {
    await ElMessageBox.confirm('确定要清除访问日志吗？', '提示', {
      type: 'warning'
    })
    await systemApi.clearAccessLog()
    ElMessage.success('清除成功')
    await refresh()
  } catch (error) {
    // User cancelled
  }
}
</script>

<style scoped>
.access-log {
  padding: 20px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
</style>
