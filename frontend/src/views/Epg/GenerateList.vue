<template>
  <div class="generate-list">
    <el-card>
      <template #header>
        <span>生成列表管理</span>
      </template>
      
      <el-alert
        title="生成列表说明"
        type="info"
        :closable="false"
        style="margin-bottom: 20px"
      >
        <p>管理 EPG XML 文件的生成列表。</p>
      </el-alert>

      <el-empty description="功能开发中" />
    </el-card>
  </div>
</template>

<script setup>
</script>

<style scoped>
.generate-list {
  padding: 20px;
}
</style>
