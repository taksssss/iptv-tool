<template>
  <div class="version">
    <el-card>
      <template #header>
        <span>版本信息</span>
      </template>
      
      <el-descriptions :column="1" border>
        <el-descriptions-item label="当前版本">v2024.11.25</el-descriptions-item>
        <el-descriptions-item label="前端框架">Vue 3 + Vite</el-descriptions-item>
        <el-descriptions-item label="UI 组件库">Element Plus</el-descriptions-item>
        <el-descriptions-item label="状态管理">Pinia</el-descriptions-item>
        <el-descriptions-item label="路由">Vue Router 4</el-descriptions-item>
        <el-descriptions-item label="HTTP 客户端">Axios</el-descriptions-item>
        <el-descriptions-item label="后端">PHP</el-descriptions-item>
        <el-descriptions-item label="数据库">SQLite / MySQL</el-descriptions-item>
      </el-descriptions>
      
      <div style="margin-top: 20px;">
        <h3>更新日志</h3>
        <el-timeline>
          <el-timeline-item timestamp="2024-11-25" placement="top">
            <el-card>
              <h4>v2024.11.25</h4>
              <p>Vue 3 + Vite 前端重构完成</p>
            </el-card>
          </el-timeline-item>
        </el-timeline>
      </div>
    </el-card>
  </div>
</template>

<script setup>
</script>

<style scoped>
.version {
  padding: 20px;
}
</style>
