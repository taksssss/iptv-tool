<template>
  <div class="dashboard">
    <h1>欢迎使用 IPTV 工具箱</h1>
    
    <el-row :gutter="20">
      <el-col :xs="24" :sm="12" :md="8">
        <el-card shadow="hover" class="dashboard-card">
          <div class="card-content">
            <el-icon :size="48" color="#409eff"><Setting /></el-icon>
            <div class="card-info">
              <h3>配置管理</h3>
              <p>EPG 源配置、频道别名、定时任务</p>
              <el-button type="primary" @click="$router.push('/config')">
                进入管理
              </el-button>
            </div>
          </div>
        </el-card>
      </el-col>
      
      <el-col :xs="24" :sm="12" :md="8">
        <el-card shadow="hover" class="dashboard-card">
          <div class="card-content">
            <el-icon :size="48" color="#67c23a"><Document /></el-icon>
            <div class="card-info">
              <h3>系统信息</h3>
              <p>版本: v2024.11.25</p>
              <p>状态: 运行中</p>
            </div>
          </div>
        </el-card>
      </el-col>
      
      <el-col :xs="24" :sm="12" :md="8">
        <el-card shadow="hover" class="dashboard-card">
          <div class="card-content">
            <el-icon :size="48" color="#e6a23c"><Link /></el-icon>
            <div class="card-info">
              <h3>快捷链接</h3>
              <p>
                <a href="https://github.com/taksssss/iptv-tool" target="_blank">
                  GitHub 仓库
                </a>
              </p>
            </div>
          </div>
        </el-card>
      </el-col>
    </el-row>
  </div>
</template>

<script setup>
import { Setting, Document, Link } from '@element-plus/icons-vue'
</script>

<style scoped>
.dashboard {
  padding: 20px;
}

.dashboard h1 {
  margin-bottom: 30px;
  color: #303133;
}

.dashboard-card {
  margin-bottom: 20px;
  min-height: 200px;
}

.card-content {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 20px;
  text-align: center;
}

.card-info {
  margin-top: 20px;
}

.card-info h3 {
  margin-bottom: 10px;
  color: #303133;
}

.card-info p {
  margin: 5px 0;
  color: #606266;
}

.card-info a {
  color: #409eff;
  text-decoration: none;
}

.card-info a:hover {
  text-decoration: underline;
}
</style>
