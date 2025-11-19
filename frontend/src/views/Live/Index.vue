<template>
  <div class="live-index">
    <el-card>
      <template #header>
        <span>直播源管理</span>
      </template>
      
      <el-row :gutter="20">
        <el-col :xs="24" :sm="12" :md="8">
          <el-card shadow="hover" class="feature-card">
            <div class="card-content">
              <el-icon :size="48" color="#409eff"><Setting /></el-icon>
              <h3>源配置</h3>
              <p>管理直播源配置</p>
              <el-button type="primary" @click="$router.push('/live/source-config')">
                进入管理
              </el-button>
            </div>
          </el-card>
        </el-col>
        
        <el-col :xs="24" :sm="12" :md="8">
          <el-card shadow="hover" class="feature-card">
            <div class="card-content">
              <el-icon :size="48" color="#67c23a"><Timer /></el-icon>
              <h3>测速管理</h3>
              <p>测试直播源速度</p>
              <el-button type="success" @click="$router.push('/live/speed-test')">
                进入管理
              </el-button>
            </div>
          </el-card>
        </el-col>
        
        <el-col :xs="24" :sm="12" :md="8">
          <el-card shadow="hover" class="feature-card">
            <div class="card-content">
              <el-icon :size="48" color="#e6a23c"><Document /></el-icon>
              <h3>模板管理</h3>
              <p>管理直播源模板</p>
              <el-button @click="$router.push('/live/template')">
                进入管理
              </el-button>
            </div>
          </el-card>
        </el-col>
      </el-row>
    </el-card>
  </div>
</template>

<script setup>
import { Setting, Timer, Document } from '@element-plus/icons-vue'
</script>

<style scoped>
.live-index {
  padding: 20px;
}

.feature-card {
  margin-bottom: 20px;
  min-height: 250px;
}

.card-content {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 20px;
  text-align: center;
}

.card-content h3 {
  margin: 15px 0 10px;
  color: #303133;
}

.card-content p {
  margin-bottom: 15px;
  color: #606266;
}
</style>
