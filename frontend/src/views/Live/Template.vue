<template>
  <div class="live-template">
    <el-card>
      <template #header>
        <span>模板管理</span>
      </template>
      
      <el-alert
        title="模板说明"
        type="info"
        :closable="false"
        style="margin-bottom: 20px"
      >
        <p>管理直播源的模板配置。</p>
      </el-alert>

      <el-empty description="功能开发中" />
    </el-card>
  </div>
</template>

<script setup>
</script>

<style scoped>
.live-template {
  padding: 20px;
}
</style>
