<template>
  <div class="icon-index">
    <el-card>
      <template #header>
        <span>台标管理</span>
      </template>
      
      <el-row :gutter="20">
        <el-col :xs="24" :sm="12" :md="8">
          <el-card shadow="hover" class="feature-card">
            <div class="card-content">
              <el-icon :size="48" color="#409eff"><Upload /></el-icon>
              <h3>上传台标</h3>
              <p>上传频道台标图片</p>
              <el-button type="primary" @click="$router.push('/icon/upload')">
                进入上传
              </el-button>
            </div>
          </el-card>
        </el-col>
        
        <el-col :xs="24" :sm="12" :md="8">
          <el-card shadow="hover" class="feature-card">
            <div class="card-content">
              <el-icon :size="48" color="#67c23a"><Link /></el-icon>
              <h3>台标映射</h3>
              <p>管理频道与台标的映射关系</p>
              <el-button type="success" @click="$router.push('/icon/mapping')">
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
import { Upload, Link } from '@element-plus/icons-vue'
</script>

<style scoped>
.icon-index {
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
