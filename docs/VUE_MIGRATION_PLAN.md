# 📋 IPTV工具箱 - Vue 3 + Vite 重构方案

## 📊 一、现有系统分析

### 1.1 项目概述
IPTV工具箱是一个基于 PHP 的 EPG（电子节目单）管理系统，支持直播源管理、台标管理等功能。

**技术栈（当前）：**
- 后端：PHP + PDO (SQLite/MySQL)
- 前端：原生 HTML + JavaScript (2300+ 行)
- 部署：Docker + Alpine Linux
- 数据库：SQLite / MySQL
- 缓存：Memcached / Redis

### 1.2 核心 PHP 文件分析

| 文件 | 功能描述 | 行数估计 |
|------|---------|---------|
| **manage.php** | 管理页面主控制器，处理配置、登录、密码管理 | ~600 行 |
| **index.php** | 公共接口，处理 EPG 查询、直播源代理 | ~500 行 |
| **public.php** | 公共函数库，数据库初始化、配置加载 | ~400 行 |
| **update.php** | 数据更新逻辑，抓取 EPG 数据 | ~800 行 |
| **cron.php** | 定时任务处理器 | ~200 行 |
| **scraper.php** | 数据抓取器（EPG 源抓取） | ~300 行 |
| **proxy.php** | 直播源代理服务 | ~200 行 |

### 1.3 前端文件分析

| 文件 | 功能 | 行数 |
|------|------|------|
| **assets/html/manage.html** | 管理界面 UI | 844 行 |
| **assets/html/login.html** | 登录页面 | 101 行 |
| **assets/js/manage.js** | 管理页面所有交互逻辑 | 2331 行 |
| **assets/css/manage.css** | 管理页面样式 | ~400 行 |
| **assets/css/login.css** | 登录页面样式 | ~100 行 |

### 1.4 主要功能模块

#### 📡 核心功能
1. **EPG 管理**
   - EPG 源配置（支持多个数据源）
   - 频道别名映射
   - 频道绑定指定 EPG 源
   - 数据定时更新

2. **直播源管理**
   - TXT/M3U 直播源聚合
   - 直播源测速校验
   - 直播源代理
   - 直播源模板管理

3. **台标管理**
   - 台标模糊匹配
   - tvbox 接口支持
   - 自定义台标上传

4. **系统配置**
   - 密码管理（MD5 加密）
   - Token 权限控制
   - User-Agent 验证
   - IP 黑白名单
   - 数据库切换（SQLite/MySQL）
   - 缓存配置（Memcached/Redis）

5. **数据接口**
   - DIYP/百川格式
   - 超级直播格式
   - xmltv 格式
   - tvbox 接口

#### 🛠️ 辅助功能
- 数据库管理（phpLiteAdmin）
- 文件管理（TinyFileManager）
- 定时任务日志
- 更新日志查看
- 访问日志统计

### 1.5 业务流程

```
用户访问 → 登录验证 → 管理界面
              ↓
    配置 EPG 源 → 保存配置 → 更新数据
              ↓
    设置定时任务 → cron.php 定期执行
              ↓
    外部访问 index.php → 根据参数返回 EPG 数据
```

## 🎯 二、Vue 3 重构方案

### 2.1 技术栈选型

**前端框架：**
- ⚡ **Vue 3** (Composition API)
- 🚀 **Vite** (构建工具)
- 🎨 **Element Plus** / **Ant Design Vue** (UI 组件库)
- 🔗 **Vue Router** (路由管理)
- 📦 **Pinia** (状态管理)
- 🌐 **Axios** (HTTP 请求)
- 🎭 **VueUse** (组合式工具集)

**开发工具：**
- 📝 **TypeScript** (可选，建议使用)
- 🎨 **TailwindCSS** / **UnoCSS** (原子化 CSS)
- 🔍 **ESLint** + **Prettier** (代码规范)

**后端保持：**
- PHP API 服务（最小改动）
- RESTful API 设计

### 2.2 目录结构设计

```
iptv-tool/
├── epg/                          # 现有 PHP 后端（保留）
│   ├── api/                      # 新增：API 专用目录
│   │   ├── auth.php             # 认证相关 API
│   │   ├── config.php           # 配置管理 API
│   │   ├── epg.php              # EPG 数据 API
│   │   ├── live.php             # 直播源 API
│   │   ├── icon.php             # 台标 API
│   │   └── system.php           # 系统信息 API
│   ├── manage.php               # 保留（兼容旧版）
│   ├── index.php                # 保留（公共接口）
│   ├── public.php               # 保留（公共函数）
│   └── ...
│
├── frontend/                     # 新增：Vue 3 前端项目
│   ├── public/                   # 静态资源
│   │   ├── favicon.ico
│   │   └── logo.png
│   │
│   ├── src/
│   │   ├── api/                  # API 接口封装
│   │   │   ├── index.ts         # Axios 实例配置
│   │   │   ├── auth.ts          # 认证 API
│   │   │   ├── config.ts        # 配置 API
│   │   │   ├── epg.ts           # EPG API
│   │   │   ├── live.ts          # 直播源 API
│   │   │   ├── icon.ts          # 台标 API
│   │   │   └── system.ts        # 系统 API
│   │   │
│   │   ├── assets/               # 静态资源
│   │   │   ├── styles/
│   │   │   │   ├── main.css     # 全局样式
│   │   │   │   ├── variables.css # CSS 变量
│   │   │   │   └── themes/       # 主题文件
│   │   │   │       ├── dark.css
│   │   │   │       └── light.css
│   │   │   └── images/
│   │   │
│   │   ├── components/           # 公共组件
│   │   │   ├── Layout/
│   │   │   │   ├── AppLayout.vue        # 主布局
│   │   │   │   ├── AppHeader.vue        # 顶部导航
│   │   │   │   ├── AppSidebar.vue       # 侧边栏
│   │   │   │   ├── AppFooter.vue        # 底部信息
│   │   │   │   └── ThemeSwitcher.vue    # 主题切换
│   │   │   │
│   │   │   ├── Common/
│   │   │   │   ├── DataTable.vue        # 数据表格
│   │   │   │   ├── Modal.vue            # 模态框
│   │   │   │   ├── CodeEditor.vue       # 代码编辑器
│   │   │   │   ├── LogViewer.vue        # 日志查看器
│   │   │   │   └── LoadingSpinner.vue   # 加载动画
│   │   │   │
│   │   │   └── Form/
│   │   │       ├── FormInput.vue
│   │   │       ├── FormTextarea.vue
│   │   │       ├── FormSelect.vue
│   │   │       └── FormCheckbox.vue
│   │   │
│   │   ├── composables/          # 组合式函数
│   │   │   ├── useAuth.ts        # 认证逻辑
│   │   │   ├── useConfig.ts      # 配置管理
│   │   │   ├── useTheme.ts       # 主题切换
│   │   │   ├── useModal.ts       # 模态框控制
│   │   │   ├── useNotification.ts # 通知提示
│   │   │   └── useTable.ts       # 表格数据处理
│   │   │
│   │   ├── router/               # 路由配置
│   │   │   └── index.ts
│   │   │
│   │   ├── stores/               # Pinia 状态管理
│   │   │   ├── auth.ts           # 认证状态
│   │   │   ├── config.ts         # 配置状态
│   │   │   ├── epg.ts            # EPG 数据状态
│   │   │   ├── live.ts           # 直播源状态
│   │   │   └── system.ts         # 系统状态
│   │   │
│   │   ├── types/                # TypeScript 类型定义
│   │   │   ├── api.ts
│   │   │   ├── config.ts
│   │   │   ├── epg.ts
│   │   │   └── live.ts
│   │   │
│   │   ├── utils/                # 工具函数
│   │   │   ├── format.ts         # 格式化函数
│   │   │   ├── validation.ts     # 验证函数
│   │   │   ├── storage.ts        # 本地存储
│   │   │   └── constants.ts      # 常量定义
│   │   │
│   │   ├── views/                # 页面组件
│   │   │   ├── Login.vue         # 登录页
│   │   │   ├── Dashboard.vue     # 仪表盘
│   │   │   │
│   │   │   ├── Config/           # 配置管理
│   │   │   │   ├── Index.vue     # 配置主页
│   │   │   │   ├── EpgSource.vue # EPG 源配置
│   │   │   │   ├── ChannelMapping.vue # 频道别名
│   │   │   │   ├── Scheduler.vue # 定时任务
│   │   │   │   └── Advanced.vue  # 高级设置
│   │   │   │
│   │   │   ├── Epg/              # EPG 管理
│   │   │   │   ├── Index.vue     # EPG 列表
│   │   │   │   ├── ChannelList.vue # 频道列表
│   │   │   │   ├── ChannelBind.vue # 频道绑定
│   │   │   │   └── GenerateList.vue # 生成列表
│   │   │   │
│   │   │   ├── Live/             # 直播源管理
│   │   │   │   ├── Index.vue     # 直播源列表
│   │   │   │   ├── SourceConfig.vue # 源配置
│   │   │   │   ├── SpeedTest.vue # 测速管理
│   │   │   │   └── Template.vue  # 模板管理
│   │   │   │
│   │   │   ├── Icon/             # 台标管理
│   │   │   │   ├── Index.vue     # 台标列表
│   │   │   │   ├── Upload.vue    # 上传台标
│   │   │   │   └── Mapping.vue   # 台标映射
│   │   │   │
│   │   │   ├── System/           # 系统管理
│   │   │   │   ├── UpdateLog.vue # 更新日志
│   │   │   │   ├── CronLog.vue   # 定时日志
│   │   │   │   ├── AccessLog.vue # 访问日志
│   │   │   │   ├── Database.vue  # 数据库管理
│   │   │   │   └── FileManager.vue # 文件管理
│   │   │   │
│   │   │   └── About/            # 关于页面
│   │   │       ├── Help.vue      # 使用说明
│   │   │       ├── Version.vue   # 版本信息
│   │   │       └── Donation.vue  # 打赏页面
│   │   │
│   │   ├── App.vue               # 根组件
│   │   └── main.ts               # 入口文件
│   │
│   ├── index.html                # HTML 模板
│   ├── vite.config.ts            # Vite 配置
│   ├── tsconfig.json             # TypeScript 配置
│   ├── package.json              # 依赖配置
│   └── .env.development          # 开发环境配置
│
└── docker-compose.yml            # 更新：添加 nginx 服务
```

### 2.3 路由结构设计

```typescript
// router/index.ts
const routes = [
  {
    path: '/login',
    name: 'Login',
    component: () => import('@/views/Login.vue'),
    meta: { requiresAuth: false }
  },
  {
    path: '/',
    component: () => import('@/components/Layout/AppLayout.vue'),
    meta: { requiresAuth: true },
    children: [
      {
        path: '',
        name: 'Dashboard',
        component: () => import('@/views/Dashboard.vue')
      },
      {
        path: 'config',
        name: 'Config',
        children: [
          { path: '', component: () => import('@/views/Config/Index.vue') },
          { path: 'epg-source', component: () => import('@/views/Config/EpgSource.vue') },
          { path: 'channel-mapping', component: () => import('@/views/Config/ChannelMapping.vue') },
          { path: 'scheduler', component: () => import('@/views/Config/Scheduler.vue') },
          { path: 'advanced', component: () => import('@/views/Config/Advanced.vue') }
        ]
      },
      {
        path: 'epg',
        name: 'Epg',
        children: [
          { path: '', component: () => import('@/views/Epg/Index.vue') },
          { path: 'channels', component: () => import('@/views/Epg/ChannelList.vue') },
          { path: 'channel-bind', component: () => import('@/views/Epg/ChannelBind.vue') },
          { path: 'generate-list', component: () => import('@/views/Epg/GenerateList.vue') }
        ]
      },
      {
        path: 'live',
        name: 'Live',
        children: [
          { path: '', component: () => import('@/views/Live/Index.vue') },
          { path: 'source-config', component: () => import('@/views/Live/SourceConfig.vue') },
          { path: 'speed-test', component: () => import('@/views/Live/SpeedTest.vue') },
          { path: 'template', component: () => import('@/views/Live/Template.vue') }
        ]
      },
      {
        path: 'icon',
        name: 'Icon',
        children: [
          { path: '', component: () => import('@/views/Icon/Index.vue') },
          { path: 'upload', component: () => import('@/views/Icon/Upload.vue') },
          { path: 'mapping', component: () => import('@/views/Icon/Mapping.vue') }
        ]
      },
      {
        path: 'system',
        name: 'System',
        children: [
          { path: 'update-log', component: () => import('@/views/System/UpdateLog.vue') },
          { path: 'cron-log', component: () => import('@/views/System/CronLog.vue') },
          { path: 'access-log', component: () => import('@/views/System/AccessLog.vue') },
          { path: 'database', component: () => import('@/views/System/Database.vue') },
          { path: 'file-manager', component: () => import('@/views/System/FileManager.vue') }
        ]
      },
      {
        path: 'about',
        name: 'About',
        children: [
          { path: 'help', component: () => import('@/views/About/Help.vue') },
          { path: 'version', component: () => import('@/views/About/Version.vue') },
          { path: 'donation', component: () => import('@/views/About/Donation.vue') }
        ]
      }
    ]
  }
]
```

### 2.4 组件结构设计

#### 核心布局组件

**1. AppLayout.vue** - 主布局
```vue
<template>
  <el-container class="app-container">
    <el-header>
      <AppHeader />
    </el-header>
    <el-container>
      <el-aside width="200px" v-if="!isMobile">
        <AppSidebar />
      </el-aside>
      <el-main>
        <router-view v-slot="{ Component }">
          <transition name="fade" mode="out-in">
            <component :is="Component" />
          </transition>
        </router-view>
      </el-main>
    </el-container>
    <el-footer>
      <AppFooter />
    </el-footer>
  </el-container>
</template>
```

**2. AppHeader.vue** - 顶部导航
- Logo + 标题
- 主题切换器
- 用户信息 + 退出按钮
- 移动端汉堡菜单

**3. AppSidebar.vue** - 侧边导航
- 配置管理
- EPG 管理
- 直播源管理
- 台标管理
- 系统管理
- 关于

### 2.5 PHP 后端 API 重构方案

#### 方案一：最小改动（推荐）
在 `epg/api/` 目录下创建专用 API 文件，复用现有 `manage.php` 的逻辑。

**epg/api/auth.php** - 认证 API
```php
<?php
require_once '../public.php';
session_start();

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'POST':
        // 登录
        if (isset($_POST['action']) && $_POST['action'] === 'login') {
            $password = md5($_POST['password']);
            if ($password === $Config['manage_password']) {
                $_SESSION['loggedin'] = true;
                $_SESSION['can_access_phpliteadmin'] = true;
                $_SESSION['can_access_tinyfilemanager'] = true;
                echo json_encode(['success' => true, 'message' => '登录成功']);
            } else {
                echo json_encode(['success' => false, 'message' => '密码错误']);
            }
        }
        // 修改密码
        elseif (isset($_POST['action']) && $_POST['action'] === 'change_password') {
            // ...密码修改逻辑
        }
        // 退出登录
        elseif (isset($_POST['action']) && $_POST['action'] === 'logout') {
            session_destroy();
            echo json_encode(['success' => true]);
        }
        break;
        
    case 'GET':
        // 检查登录状态
        echo json_encode([
            'loggedin' => isset($_SESSION['loggedin']) && $_SESSION['loggedin']
        ]);
        break;
}
```

**epg/api/config.php** - 配置管理 API
```php
<?php
require_once '../public.php';
session_start();

// 检查登录
if (!isset($_SESSION['loggedin'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // 获取配置
        echo json_encode($Config);
        break;
        
    case 'POST':
        // 更新配置
        $input = json_decode(file_get_contents('php://input'), true);
        // ... 更新配置逻辑（复用 manage.php 的 updateConfigFields）
        break;
}
```

**API 列表：**
| API 端点 | 方法 | 功能 | 对应旧代码 |
|---------|------|------|-----------|
| `/api/auth.php` | POST | 登录/登出/修改密码 | manage.php (login section) |
| `/api/config.php` | GET/POST | 获取/更新配置 | manage.php (get_config, update_config) |
| `/api/epg.php` | GET/POST | EPG 数据管理 | manage.php (get_channel, get_epg_by_channel) |
| `/api/live.php` | GET/POST | 直播源管理 | manage.php (get_live_data) |
| `/api/icon.php` | GET/POST | 台标管理 | manage.php (get_icon) |
| `/api/system.php` | GET/POST | 系统日志、更新 | manage.php (get_update_logs, get_cron_logs) |

#### 方案二：完全重构（可选）
如果需要更规范的 RESTful API，可以引入轻量级框架如 Slim 或 Lumen。

### 2.6 数据交互方案

#### Axios 封装
```typescript
// api/index.ts
import axios from 'axios'
import { ElMessage } from 'element-plus'
import { useAuthStore } from '@/stores/auth'

const apiClient = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '/epg/api',
  timeout: 30000,
  headers: {
    'Content-Type': 'application/json'
  }
})

// 请求拦截器
apiClient.interceptors.request.use(
  (config) => {
    const authStore = useAuthStore()
    // 添加 session 处理（PHP session 基于 cookie）
    config.withCredentials = true
    return config
  },
  (error) => Promise.reject(error)
)

// 响应拦截器
apiClient.interceptors.response.use(
  (response) => response.data,
  (error) => {
    if (error.response?.status === 401) {
      const authStore = useAuthStore()
      authStore.logout()
      router.push('/login')
    }
    ElMessage.error(error.response?.data?.message || '请求失败')
    return Promise.reject(error)
  }
)

export default apiClient
```

#### API 模块示例
```typescript
// api/config.ts
import apiClient from './index'

export interface Config {
  xml_urls: string[]
  channel_mappings: Record<string, string>
  days_to_keep: number
  start_time: string
  end_time: string
  interval_time: number
  // ... 其他配置项
}

export const configApi = {
  getConfig: () => apiClient.get<Config>('/config.php'),
  updateConfig: (config: Partial<Config>) => 
    apiClient.post('/config.php', config),
  resetConfig: () => 
    apiClient.post('/config.php', { action: 'reset' })
}
```

### 2.7 状态管理方案

```typescript
// stores/config.ts
import { defineStore } from 'pinia'
import { ref } from 'vue'
import { configApi, type Config } from '@/api/config'

export const useConfigStore = defineStore('config', () => {
  const config = ref<Config | null>(null)
  const loading = ref(false)

  async function fetchConfig() {
    loading.value = true
    try {
      config.value = await configApi.getConfig()
    } finally {
      loading.value = false
    }
  }

  async function updateConfig(newConfig: Partial<Config>) {
    loading.value = true
    try {
      await configApi.updateConfig(newConfig)
      await fetchConfig() // 重新获取最新配置
    } finally {
      loading.value = false
    }
  }

  return {
    config,
    loading,
    fetchConfig,
    updateConfig
  }
})
```

### 2.8 移动端适配方案

**响应式设计：**
1. 使用 Element Plus 的响应式栅格系统
2. 移动端使用 Drawer 替代 Sidebar
3. 表格在移动端切换为卡片视图
4. 使用 `@media` 查询适配不同屏幕

**示例：**
```vue
<template>
  <!-- 桌面端 -->
  <el-aside v-if="!isMobile" width="200px">
    <AppSidebar />
  </el-aside>
  
  <!-- 移动端 -->
  <el-drawer
    v-model="drawerVisible"
    v-if="isMobile"
    direction="ltr"
    size="80%"
  >
    <AppSidebar @close="drawerVisible = false" />
  </el-drawer>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { useWindowSize } from '@vueuse/core'

const { width } = useWindowSize()
const isMobile = computed(() => width.value < 768)
const drawerVisible = ref(false)
</script>
```

### 2.9 主题系统设计

```typescript
// composables/useTheme.ts
import { ref, watch } from 'vue'

export type Theme = 'light' | 'dark' | 'auto'

export function useTheme() {
  const theme = ref<Theme>(
    (localStorage.getItem('theme') as Theme) || 'auto'
  )

  const applyTheme = (newTheme: Theme) => {
    document.body.classList.remove('light', 'dark')
    
    if (newTheme === 'auto') {
      const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches
      document.body.classList.add(prefersDark ? 'dark' : 'light')
    } else {
      document.body.classList.add(newTheme)
    }
  }

  watch(theme, (newTheme) => {
    localStorage.setItem('theme', newTheme)
    applyTheme(newTheme)
  }, { immediate: true })

  return {
    theme,
    setTheme: (newTheme: Theme) => { theme.value = newTheme }
  }
}
```

## 📋 三、代码迁移映射表

### 3.1 HTML → Vue 组件映射

| 旧 HTML 文件 | 新 Vue 组件 | 功能 |
|-------------|------------|------|
| login.html | Login.vue | 登录页面 |
| manage.html (主表单) | Config/Index.vue | 配置主页 |
| manage.html (EPG 地址) | Config/EpgSource.vue | EPG 源配置 |
| manage.html (频道别名) | Config/ChannelMapping.vue | 频道别名 |
| manage.html (定时任务) | Config/Scheduler.vue | 定时任务 |
| manage.html (更多设置) | Config/Advanced.vue | 高级设置 |
| manage.html (频道管理模态框) | Epg/ChannelList.vue | 频道列表 |
| manage.html (台标管理模态框) | Icon/Index.vue | 台标管理 |
| manage.html (直播源管理模态框) | Live/Index.vue | 直播源管理 |
| manage.html (更新日志模态框) | System/UpdateLog.vue | 更新日志 |
| manage.html (定时日志模态框) | System/CronLog.vue | 定时日志 |
| manage.html (访问日志模态框) | System/AccessLog.vue | 访问日志 |
| manage.html (帮助模态框) | About/Help.vue | 使用说明 |
| manage.html (版本日志模态框) | About/Version.vue | 版本信息 |

### 3.2 JavaScript 函数 → Composables 映射

| 旧 JS 函数 (manage.js) | 新 Composable | 功能 |
|----------------------|--------------|------|
| showModal() | useModal.ts | 模态框显示控制 |
| showMessageModal() | useNotification.ts | 消息提示 |
| updateConfigFields() | useConfig.ts | 配置更新 |
| showExecResult() | useSystem.ts | 执行结果显示 |
| logout() | useAuth.ts | 退出登录 |
| commentAll() | - (Vue 组件内部方法) | 注释切换 |
| loadChannelData() | useEpg.ts | 加载频道数据 |
| loadLiveData() | useLive.ts | 加载直播源数据 |
| loadIconData() | useIcon.ts | 加载台标数据 |

### 3.3 PHP API → TypeScript API 映射

| PHP 端点/函数 | TypeScript API | HTTP 方法 |
|--------------|---------------|----------|
| manage.php?login | authApi.login() | POST |
| manage.php?change_password | authApi.changePassword() | POST |
| manage.php?get_config | configApi.getConfig() | GET |
| manage.php?update_config | configApi.updateConfig() | POST |
| manage.php?get_channel | epgApi.getChannels() | GET |
| manage.php?get_epg_by_channel | epgApi.getEpgByChannel() | GET |
| manage.php?get_icon | iconApi.getIcons() | GET |
| manage.php?get_live_data | liveApi.getLiveData() | GET |
| manage.php?get_update_logs | systemApi.getUpdateLogs() | GET |
| manage.php?get_cron_logs | systemApi.getCronLogs() | GET |
| update.php | systemApi.updateData() | POST |

## 📝 四、实施步骤计划（Todo List）

### Phase 1: 项目初始化 (1-2天)

- [ ] **1.1 创建 Vue 3 项目**
  ```bash
  cd /path/to/iptv-tool
  npm create vite@latest frontend -- --template vue-ts
  cd frontend
  npm install
  ```

- [ ] **1.2 安装核心依赖**
  ```bash
  npm install vue-router pinia axios
  npm install element-plus @element-plus/icons-vue
  npm install @vueuse/core
  npm install -D tailwindcss postcss autoprefixer
  npm install -D @types/node
  ```

- [ ] **1.3 配置开发环境**
  - 配置 `vite.config.ts`（代理、别名等）
  - 配置 `tsconfig.json`
  - 配置 Tailwind CSS
  - 创建 `.env.development` 和 `.env.production`

- [ ] **1.4 设置项目结构**
  - 创建所有目录（按照 2.2 目录结构）
  - 设置路径别名 `@` 指向 `src`

### Phase 2: 基础架构搭建 (2-3天)

- [ ] **2.1 路由系统**
  - 创建 `router/index.ts`
  - 配置所有路由（按照 2.3 路由结构）
  - 实现路由守卫（登录验证）

- [ ] **2.2 状态管理**
  - 创建 Pinia 实例
  - 实现 `stores/auth.ts`
  - 实现 `stores/config.ts`
  - 实现其他 store

- [ ] **2.3 API 封装**
  - 创建 Axios 实例配置
  - 实现请求/响应拦截器
  - 创建所有 API 模块（auth, config, epg, live, icon, system）

- [ ] **2.4 Composables**
  - 实现 `useAuth.ts`
  - 实现 `useTheme.ts`
  - 实现 `useModal.ts`
  - 实现 `useNotification.ts`
  - 实现其他通用 composables

- [ ] **2.5 类型定义**
  - 定义 `types/config.ts`（配置类型）
  - 定义 `types/epg.ts`（EPG 类型）
  - 定义 `types/live.ts`（直播源类型）
  - 定义 `types/api.ts`（API 响应类型）

### Phase 3: 布局组件开发 (2-3天)

- [ ] **3.1 主布局**
  - 实现 `AppLayout.vue`
  - 实现响应式布局（桌面/移动端）

- [ ] **3.2 头部组件**
  - 实现 `AppHeader.vue`
  - Logo + 标题
  - 主题切换器
  - 用户信息
  - 移动端菜单按钮

- [ ] **3.3 侧边栏组件**
  - 实现 `AppSidebar.vue`
  - 导航菜单（Element Plus Menu）
  - 移动端 Drawer 适配

- [ ] **3.4 底部组件**
  - 实现 `AppFooter.vue`
  - 版本信息
  - 链接（GitHub、使用说明、打赏）

- [ ] **3.5 主题系统**
  - 实现 `ThemeSwitcher.vue`
  - 集成 `useTheme` composable
  - CSS 变量定义（light/dark 主题）

### Phase 4: 公共组件开发 (3-4天)

- [ ] **4.1 表单组件**
  - `FormInput.vue`
  - `FormTextarea.vue`
  - `FormSelect.vue`
  - `FormCheckbox.vue`

- [ ] **4.2 数据展示组件**
  - `DataTable.vue`（支持排序、筛选、分页）
  - `LogViewer.vue`（日志查看器）
  - `CodeEditor.vue`（代码/配置编辑器）

- [ ] **4.3 交互组件**
  - `Modal.vue`（通用模态框）
  - `LoadingSpinner.vue`（加载动画）
  - 通知组件（使用 Element Plus Notification）

### Phase 5: 核心页面开发 (5-7天)

- [ ] **5.1 登录页面**
  - `Login.vue`
  - 登录表单
  - 修改密码功能
  - 表单验证
  - 登录状态持久化

- [ ] **5.2 仪表盘**
  - `Dashboard.vue`
  - 系统概览（EPG 数量、直播源数量等）
  - 快捷操作卡片
  - 最近更新日志

- [ ] **5.3 配置管理**
  - `Config/Index.vue` - 配置主页（汇总视图）
  - `Config/EpgSource.vue` - EPG 源配置
    - Textarea 编辑器（支持注释切换 Ctrl+/）
    - 保存配置按钮
  - `Config/ChannelMapping.vue` - 频道别名配置
    - 键值对编辑器
    - 导入/导出功能
  - `Config/Scheduler.vue` - 定时任务配置
    - 时间选择器
    - 间隔设置
  - `Config/Advanced.vue` - 高级设置
    - Token 设置
    - User-Agent 设置
    - IP 黑白名单
    - 数据库切换
    - 缓存配置

- [ ] **5.4 EPG 管理**
  - `Epg/Index.vue` - EPG 数据概览
  - `Epg/ChannelList.vue` - 频道列表（DataTable）
  - `Epg/ChannelBind.vue` - 频道绑定 EPG 源
  - `Epg/GenerateList.vue` - 生成列表管理

- [ ] **5.5 直播源管理**
  - `Live/Index.vue` - 直播源列表
  - `Live/SourceConfig.vue` - 源配置管理
  - `Live/SpeedTest.vue` - 测速管理
  - `Live/Template.vue` - 模板管理

- [ ] **5.6 台标管理**
  - `Icon/Index.vue` - 台标列表（网格视图）
  - `Icon/Upload.vue` - 上传台标
  - `Icon/Mapping.vue` - 台标映射管理

- [ ] **5.7 系统管理**
  - `System/UpdateLog.vue` - 更新日志（LogViewer）
  - `System/CronLog.vue` - 定时日志
  - `System/AccessLog.vue` - 访问日志（带统计图表）
  - `System/Database.vue` - 数据库管理（集成 phpLiteAdmin）
  - `System/FileManager.vue` - 文件管理（集成 TinyFileManager）

- [ ] **5.8 关于页面**
  - `About/Help.vue` - 使用说明（Markdown 渲染）
  - `About/Version.vue` - 版本日志
  - `About/Donation.vue` - 打赏页面

### Phase 6: PHP 后端 API 开发 (3-4天)

- [ ] **6.1 创建 API 目录结构**
  ```bash
  mkdir -p epg/api
  ```

- [ ] **6.2 实现认证 API**
  - `epg/api/auth.php`
  - 登录、登出、修改密码
  - Session 管理

- [ ] **6.3 实现配置 API**
  - `epg/api/config.php`
  - 获取配置
  - 更新配置
  - 复用 `manage.php` 的 `updateConfigFields` 逻辑

- [ ] **6.4 实现 EPG API**
  - `epg/api/epg.php`
  - 获取频道列表
  - 获取频道 EPG 数据
  - 频道绑定管理
  - 生成列表管理

- [ ] **6.5 实现直播源 API**
  - `epg/api/live.php`
  - 获取直播源数据
  - 解析源信息
  - 下载源数据
  - 测速管理

- [ ] **6.6 实现台标 API**
  - `epg/api/icon.php`
  - 获取台标列表
  - 上传台标
  - 删除台标
  - 台标映射

- [ ] **6.7 实现系统 API**
  - `epg/api/system.php`
  - 获取更新日志
  - 获取定时日志
  - 获取访问日志
  - 触发数据更新
  - 系统信息

- [ ] **6.8 CORS 处理**
  - 为开发环境配置 CORS 头
  - 生产环境使用 Nginx 反向代理

### Phase 7: 功能整合与测试 (3-4天)

- [ ] **7.1 功能联调**
  - 登录流程测试
  - 配置保存测试
  - 数据更新测试
  - 所有 CRUD 操作测试

- [ ] **7.2 错误处理**
  - API 错误提示优化
  - 表单验证完善
  - 边界情况处理

- [ ] **7.3 性能优化**
  - 组件懒加载
  - 图片懒加载
  - 列表虚拟滚动（长列表）
  - 防抖/节流处理

- [ ] **7.4 移动端适配**
  - 响应式布局检查
  - 触摸交互优化
  - 移动端菜单测试

- [ ] **7.5 浏览器兼容性**
  - Chrome/Edge 测试
  - Firefox 测试
  - Safari 测试

### Phase 8: 部署与文档 (2-3天)

- [ ] **8.1 生产构建**
  - 优化 Vite 配置
  - 构建生产版本
  - 资源压缩与优化

- [ ] **8.2 Docker 集成**
  - 更新 Dockerfile（添加 Nginx）
  - 更新 docker-compose.yml
  - 配置 Nginx 反向代理（Vue SPA + PHP API）

- [ ] **8.3 文档编写**
  - 更新 README.md（添加 Vue 版本说明）
  - 编写开发文档
  - 编写部署文档
  - API 文档（可选）

- [ ] **8.4 迁移指南**
  - 旧版到新版的迁移步骤
  - 数据兼容性说明
  - 回退方案

### Phase 9: 发布与维护 (持续)

- [ ] **9.1 Beta 测试**
  - 内部测试
  - 收集反馈
  - 修复 Bug

- [ ] **9.2 正式发布**
  - 发布新版本
  - 更新 CHANGELOG.md
  - GitHub Release

- [ ] **9.3 后续优化**
  - 根据用户反馈迭代
  - 添加新功能
  - 性能监控与优化

## 🔧 五、技术实现细节

### 5.1 Vite 配置示例

```typescript
// vite.config.ts
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'path'

export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': resolve(__dirname, 'src')
    }
  },
  server: {
    port: 3000,
    proxy: {
      '/epg/api': {
        target: 'http://localhost:5678',
        changeOrigin: true
      }
    }
  },
  build: {
    outDir: '../epg/dist',
    emptyOutDir: true,
    rollupOptions: {
      output: {
        manualChunks: {
          'element-plus': ['element-plus'],
          'vue-vendor': ['vue', 'vue-router', 'pinia']
        }
      }
    }
  }
})
```

### 5.2 环境变量配置

```bash
# .env.development
VITE_API_BASE_URL=/epg/api
VITE_APP_TITLE=IPTV工具箱

# .env.production
VITE_API_BASE_URL=/epg/api
VITE_APP_TITLE=IPTV工具箱
```

### 5.3 Nginx 配置（生产环境）

```nginx
server {
    listen 80;
    server_name localhost;
    root /htdocs;

    # Vue SPA
    location / {
        try_files /epg/dist/$uri /epg/dist/index.html;
    }

    # PHP API
    location /epg/api/ {
        try_files $uri $uri/ /epg/api/$1.php?$query_string;
        fastcgi_pass unix:/var/run/php-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    # 兼容旧版（可选）
    location /epg/manage.php {
        fastcgi_pass unix:/var/run/php-fpm.sock;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    # 公共接口（保持不变）
    location /epg/index.php {
        fastcgi_pass unix:/var/run/php-fpm.sock;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    # 静态资源缓存
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

### 5.4 Dockerfile 更新

```dockerfile
FROM php:8.1-fpm-alpine

# 安装 Nginx
RUN apk add --no-cache nginx

# 复制 Nginx 配置
COPY nginx.conf /etc/nginx/http.d/default.conf

# ... 其他 PHP 配置 ...

# 启动脚本
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["sh", "-c", "php-fpm & nginx -g 'daemon off;'"]
```

## 🚀 六、快速启动指南

### 开发模式

```bash
# 1. 安装依赖
cd frontend
npm install

# 2. 启动 PHP 后端（Docker）
docker-compose up -d

# 3. 启动 Vue 开发服务器
npm run dev

# 访问: http://localhost:3000
```

### 生产构建

```bash
# 1. 构建 Vue 项目
cd frontend
npm run build

# 2. 构建 Docker 镜像
cd ..
docker build -t iptv-tool-vue:latest .

# 3. 运行
docker run -d -p 5678:80 iptv-tool-vue:latest
```

## 📊 七、性能对比估算

| 指标 | 旧版（原生 JS） | 新版（Vue 3） | 改进 |
|-----|----------------|--------------|------|
| **首屏加载** | ~800ms | ~600ms | ⬇️ 25% |
| **交互响应** | ~200ms | ~50ms | ⬇️ 75% |
| **代码可维护性** | ⭐⭐ | ⭐⭐⭐⭐⭐ | ⬆️ 150% |
| **开发效率** | 1x | 2-3x | ⬆️ 200% |
| **Bundle 大小** | ~100KB | ~250KB | ⬆️ 150% (可接受) |

**注：** Bundle 大小增加是因为引入了 Vue、Router、Pinia 等框架，但带来的开发效率和维护性提升是值得的。

## 🎯 八、风险与挑战

### 8.1 兼容性风险
- **问题：** 旧版 PHP Session 可能与新版前端不兼容
- **解决方案：** 保持 Session 机制不变，前端通过 `withCredentials: true` 携带 Cookie

### 8.2 迁移成本
- **问题：** 2300+ 行 JS 代码重写工作量大
- **解决方案：** 分阶段迁移，优先迁移核心功能，非核心功能可保留旧版

### 8.3 学习曲线
- **问题：** 团队需要学习 Vue 3、TypeScript、Vite
- **解决方案：** 提供详细文档和示例代码，渐进式学习

### 8.4 SEO 问题
- **问题：** SPA 不利于 SEO（不过管理后台无需 SEO）
- **解决方案：** 管理后台无需 SEO，公共接口（index.php）保持不变

## 📚 九、参考资源

### 官方文档
- [Vue 3 文档](https://cn.vuejs.org/)
- [Vite 文档](https://cn.vitejs.dev/)
- [Pinia 文档](https://pinia.vuejs.org/zh/)
- [Vue Router 文档](https://router.vuejs.org/zh/)
- [Element Plus 文档](https://element-plus.org/zh-CN/)

### 推荐工具
- [VueUse](https://vueuse.org/) - Vue 组合式工具集
- [UnoCSS](https://unocss.dev/) - 原子化 CSS 引擎
- [unplugin-auto-import](https://github.com/antfu/unplugin-auto-import) - 自动导入
- [vite-plugin-pages](https://github.com/hannoeru/vite-plugin-pages) - 基于文件的路由

### 示例项目
- [vue-vben-admin](https://github.com/vbenjs/vue-vben-admin) - 企业级后台管理系统
- [Soybean Admin](https://github.com/honghuangdc/soybean-admin) - 清新优雅的后台管理系统

## ✅ 总结

本方案提供了从传统 PHP + 原生 JS 到现代 Vue 3 + Vite 的完整迁移路径，包括：

1. ✅ **完整的技术方案** - 目录结构、路由、组件、API 设计
2. ✅ **详细的迁移映射表** - HTML→Vue、JS→Composables、PHP→API
3. ✅ **可执行的实施计划** - 9 个阶段，30+ 具体任务
4. ✅ **最小改动原则** - 后端 API 复用现有逻辑，降低风险
5. ✅ **渐进式迁移** - 可分阶段实施，旧版可并行存在

**预计总工期：** 20-30 个工作日（1 人全职开发）

**建议：** 优先实施 Phase 1-6，完成核心功能迁移后发布 Beta 版本，收集反馈后再完善。
