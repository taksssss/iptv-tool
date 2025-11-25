<template>
  <div class="icon-mapping">
    <el-card>
      <template #header>
        <span>台标映射</span>
      </template>
      
      <el-alert
        title="台标映射说明"
        type="info"
        :closable="false"
        style="margin-bottom: 20px"
      >
        <p>管理频道名称与台标文件的映射关系。</p>
      </el-alert>

      <el-empty description="功能开发中" />
    </el-card>
  </div>
</template>

<script setup>
</script>

<style scoped>
.icon-mapping {
  padding: 20px;
}
</style>
