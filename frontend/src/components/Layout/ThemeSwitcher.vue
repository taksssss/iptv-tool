<template>
  <el-dropdown @command="handleCommand">
    <span class="theme-switcher">
      <el-icon :size="20">
        <component :is="currentIcon" />
      </el-icon>
    </span>
    <template #dropdown>
      <el-dropdown-menu>
        <el-dropdown-item command="light">
          <el-icon><Sunny /></el-icon>
          浅色
        </el-dropdown-item>
        <el-dropdown-item command="dark">
          <el-icon><Moon /></el-icon>
          深色
        </el-dropdown-item>
        <el-dropdown-item command="auto">
          <el-icon><Monitor /></el-icon>
          自动
        </el-dropdown-item>
      </el-dropdown-menu>
    </template>
  </el-dropdown>
</template>

<script setup>
import { computed } from 'vue'
import { Sunny, Moon, Monitor } from '@element-plus/icons-vue'
import { useTheme } from '@/composables/useTheme'

const { theme, setTheme } = useTheme()

const currentIcon = computed(() => {
  if (theme.value === 'dark') return Moon
  if (theme.value === 'light') return Sunny
  return Monitor
})

const handleCommand = (command) => {
  setTheme(command)
}
</script>

<style scoped>
.theme-switcher {
  display: flex;
  align-items: center;
  cursor: pointer;
  padding: 5px;
  border-radius: 4px;
  transition: background-color 0.3s;
}

.theme-switcher:hover {
  background-color: #f5f5f5;
}
</style>
