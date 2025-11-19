<template>
  <div class="donation">
    <el-card>
      <template #header>
        <span>打赏支持</span>
      </template>
      
      <div class="donation-content">
        <p>如果您觉得这个项目对您有帮助，欢迎打赏支持！</p>
        <p>您的支持是我们持续更新的动力。</p>
        
        <el-link type="primary" href="https://github.com/taksssss/iptv-tool" target="_blank">
          访问 GitHub 仓库
        </el-link>
      </div>
    </el-card>
  </div>
</template>

<script setup>
</script>

<style scoped>
.donation {
  padding: 20px;
}

.donation-content {
  padding: 20px;
  text-align: center;
}

.donation-content p {
  margin: 10px 0;
  color: #606266;
}
</style>
