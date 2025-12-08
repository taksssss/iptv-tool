<template>
  <div class="speed-test">
    <el-card>
      <template #header>
        <span>直播源测速</span>
      </template>
      
      <el-alert
        title="测速说明"
        type="info"
        :closable="false"
        style="margin-bottom: 20px"
      >
        <p>测试直播源的连接速度和可用性。</p>
      </el-alert>

      <el-empty description="功能开发中" />
    </el-card>
  </div>
</template>

<script setup>
</script>

<style scoped>
.speed-test {
  padding: 20px;
}
</style>
