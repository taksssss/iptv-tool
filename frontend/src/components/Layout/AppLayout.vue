<template>
  <el-container class="app-container">
    <el-header height="60px" class="app-header">
      <div class="header-left">
        <h1 class="title">IPTV 工具箱</h1>
      </div>
      
      <div class="header-right">
        <ThemeSwitcher />
        <el-dropdown @command="handleCommand">
          <span class="user-dropdown">
            <el-icon><User /></el-icon>
            <span>管理员</span>
            <el-icon><ArrowDown /></el-icon>
          </span>
          <template #dropdown>
            <el-dropdown-menu>
              <el-dropdown-item command="logout">
                <el-icon><SwitchButton /></el-icon>
                退出登录
              </el-dropdown-item>
            </el-dropdown-menu>
          </template>
        </el-dropdown>
      </div>
    </el-header>
    
    <el-container>
      <AppSidebar />
      
      <el-main class="app-main">
        <router-view v-slot="{ Component }">
          <transition name="fade" mode="out-in">
            <component :is="Component" />
          </transition>
        </router-view>
      </el-main>
    </el-container>
    
    <el-footer height="40px">
      <AppFooter />
    </el-footer>
  </el-container>
</template>

<script setup>
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { User, ArrowDown, SwitchButton } from '@element-plus/icons-vue'
import { useAuthStore } from '@/stores/auth'
import AppSidebar from './AppSidebar.vue'
import AppFooter from './AppFooter.vue'
import ThemeSwitcher from './ThemeSwitcher.vue'

const router = useRouter()
const authStore = useAuthStore()

const handleCommand = async (command) => {
  if (command === 'logout') {
    try {
      await ElMessageBox.confirm('确定要退出登录吗？', '提示', {
        type: 'warning'
      })
      
      await authStore.logout()
      ElMessage.success('已退出登录')
      router.push('/login')
    } catch (error) {
      // User cancelled
    }
  }
}
</script>

<style scoped>
.app-container {
  height: 100vh;
}

.app-header {
  background-color: #fff;
  box-shadow: 0 1px 4px rgba(0, 21, 41, 0.08);
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0 20px;
}

.header-left {
  display: flex;
  align-items: center;
  gap: 15px;
}

.title {
  margin: 0;
  font-size: 20px;
  font-weight: 500;
  color: #333;
}

.header-right {
  display: flex;
  align-items: center;
  gap: 20px;
}

.user-dropdown {
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  padding: 5px 10px;
  border-radius: 4px;
  transition: background-color 0.3s;
}

.user-dropdown:hover {
  background-color: #f5f5f5;
}

.app-aside {
  background-color: #304156;
  overflow-y: auto;
}

.app-menu {
  border: none;
  background-color: #304156;
}

.app-menu :deep(.el-menu-item) {
  color: #bfcbd9;
}

.app-menu :deep(.el-menu-item.is-active) {
  background-color: #263445 !important;
  color: #409eff;
}

.app-menu :deep(.el-menu-item:hover) {
  background-color: #263445;
  color: #fff;
}

.app-main {
  background-color: #f0f2f5;
  padding: 20px;
  overflow-y: auto;
}

.app-footer {
  background-color: #fff;
  border-top: 1px solid #e4e7ed;
  display: flex;
  align-items: center;
  justify-content: center;
}

.app-footer a {
  color: #666;
  text-decoration: none;
}

.app-footer a:hover {
  color: #409eff;
  text-decoration: underline;
}
</style>
