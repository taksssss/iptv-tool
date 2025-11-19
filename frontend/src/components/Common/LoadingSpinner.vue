<template>
  <div class="loading-spinner">
    <el-icon class="is-loading" :size="size">
      <Loading />
    </el-icon>
    <p v-if="text">{{ text }}</p>
  </div>
</template>

<script setup>
import { Loading } from '@element-plus/icons-vue'

defineProps({
  size: {
    type: Number,
    default: 40
  },
  text: {
    type: String,
    default: ''
  }
})
</script>

<style scoped>
.loading-spinner {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 40px;
}

.loading-spinner p {
  margin-top: 10px;
  color: #909399;
}
</style>
