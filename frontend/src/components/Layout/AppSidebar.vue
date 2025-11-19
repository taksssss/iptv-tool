<template>
  <el-aside width="200px" class="app-sidebar">
    <el-menu
      :default-active="activeMenu"
      router
      class="sidebar-menu"
    >
      <el-menu-item index="/">
        <el-icon><HomeFilled /></el-icon>
        <span>仪表盘</span>
      </el-menu-item>
      
      <el-sub-menu index="config">
        <template #title>
          <el-icon><Setting /></el-icon>
          <span>配置管理</span>
        </template>
        <el-menu-item index="/config">配置主页</el-menu-item>
        <el-menu-item index="/config/epg-source">EPG源配置</el-menu-item>
        <el-menu-item index="/config/channel-mapping">频道别名</el-menu-item>
        <el-menu-item index="/config/scheduler">定时任务</el-menu-item>
        <el-menu-item index="/config/advanced">高级设置</el-menu-item>
      </el-sub-menu>
      
      <el-sub-menu index="epg">
        <template #title>
          <el-icon><Document /></el-icon>
          <span>EPG管理</span>
        </template>
        <el-menu-item index="/epg">EPG列表</el-menu-item>
        <el-menu-item index="/epg/channels">频道列表</el-menu-item>
        <el-menu-item index="/epg/channel-bind">频道绑定</el-menu-item>
        <el-menu-item index="/epg/generate-list">生成列表</el-menu-item>
      </el-sub-menu>
      
      <el-sub-menu index="live">
        <template #title>
          <el-icon><VideoPlay /></el-icon>
          <span>直播源管理</span>
        </template>
        <el-menu-item index="/live">直播源列表</el-menu-item>
        <el-menu-item index="/live/source-config">源配置</el-menu-item>
        <el-menu-item index="/live/speed-test">测速管理</el-menu-item>
        <el-menu-item index="/live/template">模板管理</el-menu-item>
      </el-sub-menu>
      
      <el-sub-menu index="icon">
        <template #title>
          <el-icon><Picture /></el-icon>
          <span>台标管理</span>
        </template>
        <el-menu-item index="/icon">台标列表</el-menu-item>
        <el-menu-item index="/icon/upload">上传台标</el-menu-item>
        <el-menu-item index="/icon/mapping">台标映射</el-menu-item>
      </el-sub-menu>
      
      <el-sub-menu index="system">
        <template #title>
          <el-icon><Tools /></el-icon>
          <span>系统管理</span>
        </template>
        <el-menu-item index="/system/update-log">更新日志</el-menu-item>
        <el-menu-item index="/system/cron-log">定时日志</el-menu-item>
        <el-menu-item index="/system/access-log">访问日志</el-menu-item>
        <el-menu-item index="/system/database">数据库管理</el-menu-item>
        <el-menu-item index="/system/file-manager">文件管理</el-menu-item>
      </el-sub-menu>
      
      <el-sub-menu index="about">
        <template #title>
          <el-icon><InfoFilled /></el-icon>
          <span>关于</span>
        </template>
        <el-menu-item index="/about/help">使用说明</el-menu-item>
        <el-menu-item index="/about/version">版本信息</el-menu-item>
        <el-menu-item index="/about/donation">打赏支持</el-menu-item>
      </el-sub-menu>
    </el-menu>
  </el-aside>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { HomeFilled, Setting, Document, VideoPlay, Picture, Tools, InfoFilled } from '@element-plus/icons-vue'

const route = useRoute()
const activeMenu = computed(() => route.path)
</script>

<style scoped>
.app-sidebar {
  background-color: #304156;
  overflow-y: auto;
}

.sidebar-menu {
  border: none;
  background-color: #304156;
}

.sidebar-menu :deep(.el-menu-item),
.sidebar-menu :deep(.el-sub-menu__title) {
  color: #bfcbd9;
}

.sidebar-menu :deep(.el-menu-item.is-active) {
  background-color: #263445 !important;
  color: #409eff;
}

.sidebar-menu :deep(.el-menu-item:hover),
.sidebar-menu :deep(.el-sub-menu__title:hover) {
  background-color: #263445;
  color: #fff;
}

.sidebar-menu :deep(.el-sub-menu .el-menu-item) {
  background-color: #1f2d3d !important;
}

.sidebar-menu :deep(.el-sub-menu .el-menu-item:hover) {
  background-color: #001528 !important;
}
</style>
