<!-- 主布局组件 -->
<template>
  <el-container class="app-container">
    <el-header height="60px">
      <AppHeader />
    </el-header>
    
    <el-container>
      <!-- 桌面端侧边栏 -->
      <el-aside v-if="!isMobile" width="200px" class="app-aside">
        <AppSidebar />
      </el-aside>
      
      <!-- 移动端抽屉 -->
      <el-drawer
        v-model="drawerVisible"
        v-if="isMobile"
        direction="ltr"
        :size="250"
        :with-header="false"
      >
        <AppSidebar @navigate="drawerVisible = false" />
      </el-drawer>
      
      <el-main class="app-main">
        <router-view v-slot="{ Component }">
          <transition name="fade" mode="out-in">
            <component :is="Component" />
          </transition>
        </router-view>
      </el-main>
    </el-container>
    
    <el-footer height="40px" class="app-footer">
      <AppFooter />
    </el-footer>
  </el-container>
</template>

<script setup lang="ts">
import { ref, computed, provide } from 'vue'
import { useWindowSize } from '@vueuse/core'
import AppHeader from './AppHeader.vue'
import AppSidebar from './AppSidebar.vue'
import AppFooter from './AppFooter.vue'

const { width } = useWindowSize()
const isMobile = computed(() => width.value < 768)
const drawerVisible = ref(false)

// 提供给 Header 使用
provide('toggleDrawer', () => {
  drawerVisible.value = !drawerVisible.value
})
</script>

<style scoped>
.app-container {
  height: 100vh;
}

.app-aside {
  background-color: #304156;
  overflow-y: auto;
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

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
