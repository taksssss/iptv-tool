<template>
  <div class="database">
    <el-card>
      <template #header>
        <span>数据库管理</span>
      </template>
      
      <el-alert
        title="数据库管理说明"
        type="info"
        :closable="false"
        style="margin-bottom: 20px"
      >
        <p>管理 SQLite/MySQL 数据库。</p>
      </el-alert>

      <el-empty description="功能开发中" />
    </el-card>
  </div>
</template>

<script setup>
</script>

<style scoped>
.database {
  padding: 20px;
}
</style>
