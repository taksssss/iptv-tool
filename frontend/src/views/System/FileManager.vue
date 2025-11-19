<template>
  <div class="file-manager">
    <el-card>
      <template #header>
        <span>文件管理</span>
      </template>
      
      <el-alert
        title="文件管理说明"
        type="info"
        :closable="false"
        style="margin-bottom: 20px"
      >
        <p>管理系统文件。</p>
      </el-alert>

      <el-empty description="功能开发中" />
    </el-card>
  </div>
</template>

<script setup>
</script>

<style scoped>
.file-manager {
  padding: 20px;
}
</style>
