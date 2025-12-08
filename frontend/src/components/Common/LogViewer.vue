<template>
  <div class="log-viewer">
    <el-card>
      <template #header>
        <div class="card-header">
          <span>{{ title }}</span>
          <el-button v-if="showRefresh" @click="handleRefresh" :loading="loading">
            <el-icon><Refresh /></el-icon>
            刷新
          </el-button>
        </div>
      </template>
      
      <div class="log-content">
        <pre v-if="logs">{{ logs }}</pre>
        <el-empty v-else description="暂无日志" />
      </div>
    </el-card>
  </div>
</template>

<script setup>
import { Refresh } from '@element-plus/icons-vue'

const props = defineProps({
  title: {
    type: String,
    default: '日志'
  },
  logs: {
    type: String,
    default: ''
  },
  loading: {
    type: Boolean,
    default: false
  },
  showRefresh: {
    type: Boolean,
    default: true
  }
})

const emit = defineEmits(['refresh'])

const handleRefresh = () => {
  emit('refresh')
}
</script>

<style scoped>
.log-viewer {
  width: 100%;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.log-content {
  max-height: 600px;
  overflow-y: auto;
}

.log-content pre {
  margin: 0;
  padding: 10px;
  background-color: #f5f5f5;
  border-radius: 4px;
  font-family: 'Courier New', Courier, monospace;
  font-size: 12px;
  line-height: 1.5;
  white-space: pre-wrap;
  word-wrap: break-word;
}
</style>
