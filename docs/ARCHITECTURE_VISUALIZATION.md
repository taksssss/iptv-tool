# 🎨 Vue 3 迁移架构可视化

## 架构对比图

### 旧架构（单体应用）

```
┌─────────────────────────────────────────────────────────────┐
│                        浏览器                                 │
│                                                              │
│  ┌──────────────────────────────────────────────────────┐   │
│  │              manage.html (844 行)                     │   │
│  │  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌─────────┐ │   │
│  │  │ EPG配置  │ │ 频道别名 │ │ 定时任务 │ │  ...    │ │   │
│  │  │ (模态框) │ │ (模态框) │ │ (模态框) │ │ (模态框)│ │   │
│  │  └──────────┘ └──────────┘ └──────────┘ └─────────┘ │   │
│  └──────────────────────────────────────────────────────┘   │
│                           ▲                                  │
│                           │ DOM 操作                          │
│  ┌──────────────────────────────────────────────────────┐   │
│  │           manage.js (2331 行)                         │   │
│  │  ┌──────────────────────────────────────────────────┐│   │
│  │  │ showModal()  updateConfig()  loadData()  ...     ││   │
│  │  └──────────────────────────────────────────────────┘│   │
│  └──────────────────────────────────────────────────────┘   │
└──────────────────────────┬───────────────────────────────────┘
                           │ HTTP (fetch)
                           ▼
┌─────────────────────────────────────────────────────────────┐
│                      服务器 (PHP)                             │
│  ┌──────────────────────────────────────────────────────┐   │
│  │           manage.php (600 行)                         │   │
│  │  - 登录验证      - 配置更新      - 数据查询          │   │
│  │  - Session管理   - 文件操作      - ...              │   │
│  └──────────────────────────────────────────────────────┘   │
│                           ▼                                  │
│  ┌──────────────────────────────────────────────────────┐   │
│  │              SQLite / MySQL                           │   │
│  └──────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘

问题：
❌ 单体 HTML，难以维护
❌ JS 代码耦合，逻辑复杂
❌ 模态框管理混乱
❌ 无类型检查
❌ 页面刷新体验差
```

### 新架构（模块化 SPA）

```
┌─────────────────────────────────────────────────────────────┐
│                    浏览器 (Vue 3 SPA)                         │
│                                                              │
│  ┌──────────────────────────────────────────────────────┐   │
│  │                   App.vue                             │   │
│  │  ┌────────────────────────────────────────────────┐  │   │
│  │  │            Vue Router                           │  │   │
│  │  │  ┌──────────┐ ┌──────────┐ ┌──────────┐       │  │   │
│  │  │  │ /login   │ │ /config  │ │ /epg     │ ...   │  │   │
│  │  │  └──────────┘ └──────────┘ └──────────┘       │  │   │
│  │  └────────────────────────────────────────────────┘  │   │
│  │         ▲                    ▲                        │   │
│  │         │                    │                        │   │
│  │  ┌──────────────────┐ ┌──────────────────┐          │   │
│  │  │  Components      │ │  Pinia Stores    │          │   │
│  │  │  - AppLayout     │ │  - authStore     │          │   │
│  │  │  - AppHeader     │ │  - configStore   │          │   │
│  │  │  - DataTable     │ │  - epgStore      │          │   │
│  │  │  - LogViewer     │ │  - liveStore     │          │   │
│  │  └──────────────────┘ └──────────────────┘          │   │
│  │         ▲                    ▲                        │   │
│  │         └─────────┬──────────┘                        │   │
│  │                   │ 响应式数据                          │   │
│  │         ┌─────────────────────┐                       │   │
│  │         │  Composables        │                       │   │
│  │         │  - useAuth          │                       │   │
│  │         │  - useTheme         │                       │   │
│  │         │  - useModal         │                       │   │
│  │         └─────────────────────┘                       │   │
│  └──────────────────────────────────────────────────────┘   │
└──────────────────────────┬───────────────────────────────────┘
                           │ HTTP (Axios + Session Cookie)
                           ▼
┌─────────────────────────────────────────────────────────────┐
│                   服务器 (PHP RESTful API)                    │
│  ┌──────────────────────────────────────────────────────┐   │
│  │              /api/ 目录                               │   │
│  │  ┌────────────┐ ┌────────────┐ ┌────────────┐       │   │
│  │  │ auth.php   │ │ config.php │ │ epg.php    │ ...   │   │
│  │  │ - login    │ │ - get      │ │ - channels │       │   │
│  │  │ - logout   │ │ - update   │ │ - bind     │       │   │
│  │  └────────────┘ └────────────┘ └────────────┘       │   │
│  │         │              │              │               │   │
│  │         └──────────────┼──────────────┘               │   │
│  │                        │ 复用逻辑                       │   │
│  │         ┌──────────────────────────────┐              │   │
│  │         │   public.php (共享函数)      │              │   │
│  │         │   - initialDB()              │              │   │
│  │         │   - $Config                  │              │   │
│  │         │   - $db (PDO)                │              │   │
│  │         └──────────────────────────────┘              │   │
│  └──────────────────────────────────────────────────────┘   │
│                           ▼                                  │
│  ┌──────────────────────────────────────────────────────┐   │
│  │              SQLite / MySQL (不变)                     │   │
│  └──────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘

优势：
✅ 模块化组件，易于维护
✅ 响应式数据，自动更新UI
✅ SPA路由，无刷新切换
✅ TypeScript 类型检查
✅ 状态管理清晰
```

## 目录结构对比

### 旧版目录

```
epg/
├── manage.php              (600 行 - 所有后端逻辑)
├── index.php               (500 行 - 公共接口)
├── public.php              (400 行 - 公共函数)
└── assets/
    ├── html/
    │   ├── manage.html     (844 行 - 所有UI)
    │   └── login.html      (101 行)
    ├── js/
    │   └── manage.js       (2331 行 - 所有前端逻辑)
    └── css/
        ├── manage.css      (400 行)
        └── login.css       (100 行)

总计：~5000 行代码在 6 个文件中
```

### 新版目录

```
iptv-tool/
├── epg/                          # 后端（保留并优化）
│   ├── api/                      # 新增：RESTful API
│   │   ├── auth.php             (200 行 - 认证)
│   │   ├── config.php           (250 行 - 配置)
│   │   ├── epg.php              (300 行 - EPG)
│   │   ├── live.php             (200 行 - 直播源)
│   │   ├── icon.php             (150 行 - 台标)
│   │   └── system.php           (200 行 - 系统)
│   ├── manage.php               (保留，兼容旧版)
│   ├── index.php                (保留，公共接口)
│   └── public.php               (保留，共享函数)
│
└── frontend/                     # 新增：Vue 3 前端
    ├── src/
    │   ├── api/                  (7 个 API 模块，~700 行)
    │   │   ├── index.ts
    │   │   ├── auth.ts
    │   │   ├── config.ts
    │   │   └── ...
    │   │
    │   ├── components/           (15+ 组件，~1500 行)
    │   │   ├── Layout/
    │   │   │   ├── AppLayout.vue
    │   │   │   ├── AppHeader.vue
    │   │   │   ├── AppSidebar.vue
    │   │   │   └── AppFooter.vue
    │   │   ├── Common/
    │   │   │   ├── DataTable.vue
    │   │   │   ├── LogViewer.vue
    │   │   │   └── CodeEditor.vue
    │   │   └── Form/
    │   │       └── ...
    │   │
    │   ├── views/                (25+ 页面，~3000 行)
    │   │   ├── Login.vue
    │   │   ├── Dashboard.vue
    │   │   ├── Config/
    │   │   │   ├── Index.vue
    │   │   │   ├── EpgSource.vue
    │   │   │   ├── ChannelMapping.vue
    │   │   │   └── ...
    │   │   ├── Epg/
    │   │   │   └── ...
    │   │   ├── Live/
    │   │   │   └── ...
    │   │   ├── Icon/
    │   │   │   └── ...
    │   │   └── System/
    │   │       └── ...
    │   │
    │   ├── stores/               (5 个 store，~500 行)
    │   │   ├── auth.ts
    │   │   ├── config.ts
    │   │   ├── epg.ts
    │   │   ├── live.ts
    │   │   └── system.ts
    │   │
    │   ├── composables/          (5+ composables，~400 行)
    │   │   ├── useAuth.ts
    │   │   ├── useTheme.ts
    │   │   ├── useModal.ts
    │   │   └── ...
    │   │
    │   ├── router/               (1 个文件，~200 行)
    │   │   └── index.ts
    │   │
    │   ├── types/                (类型定义，~300 行)
    │   │   └── ...
    │   │
    │   └── utils/                (工具函数，~200 行)
    │       └── ...
    │
    ├── package.json
    ├── vite.config.ts
    └── tsconfig.json

总计：~7000 行代码，40+ 文件（模块化、可维护）
```

## 数据流对比

### 旧版：配置更新流程

```
用户点击"保存配置"
    ↓
manage.js 收集表单数据
    ↓
创建 FormData 对象
    ↓
fetch('manage.php', {POST, body: formData})
    ↓
[刷新页面或手动更新DOM]
    ↓
manage.php 接收 POST 数据
    ↓
解析表单字段
    ↓
updateConfigFields() 函数
    ↓
写入 config.json
    ↓
返回 JSON: {success: true}
    ↓
manage.js 显示成功消息（手动操作DOM）
```

### 新版：配置更新流程

```
用户点击"保存配置"
    ↓
ConfigForm.vue 触发 @submit
    ↓
调用 configStore.updateConfig(formData)
    ↓
Pinia Action: updateConfig()
    ↓
调用 configApi.updateConfig(formData)
    ↓
Axios POST /api/config.php (自动携带 Cookie)
    ↓
api/config.php 接收 JSON 数据
    ↓
复用 updateConfigFields() 逻辑
    ↓
写入 config.json
    ↓
返回 JSON: {success: true, ...}
    ↓
Axios 响应拦截器处理
    ↓
Pinia Store 更新状态（config.value = newData）
    ↓
Vue 响应式系统自动更新 UI
    ↓
ElMessage 自动显示成功提示
```

优势：
- 自动 UI 更新（无需手动操作 DOM）
- 统一错误处理（拦截器）
- 类型安全（TypeScript）
- 状态持久化（Pinia）

## 路由结构对比

### 旧版：单页面 + 模态框

```
/epg/manage.php
    │
    ├── [主表单区域]
    │   ├── EPG 源配置 (textarea)
    │   ├── 频道别名 (textarea)
    │   ├── 定时任务 (input)
    │   └── ...
    │
    └── [模态框]
        ├── #channelModal        (频道管理)
        ├── #iconModal           (台标管理)
        ├── #liveModal           (直播源管理)
        ├── #updateLogModal      (更新日志)
        ├── #cronLogModal        (定时日志)
        ├── #helpModal           (帮助)
        └── ...

问题：
❌ 所有功能挤在一个页面
❌ 模态框管理复杂
❌ 无法直接链接到具体功能
❌ 浏览器前进/后退不可用
```

### 新版：SPA 路由

```
/                               (根路径)
├── /login                      登录页
└── /                           主应用（需认证）
    ├── /                       仪表盘
    ├── /config                 配置管理
    │   ├── /config/epg-source      EPG 源配置
    │   ├── /config/channel-mapping  频道别名
    │   ├── /config/scheduler        定时任务
    │   └── /config/advanced         高级设置
    │
    ├── /epg                    EPG 管理
    │   ├── /epg/channels           频道列表
    │   ├── /epg/channel-bind       频道绑定
    │   └── /epg/generate-list      生成列表
    │
    ├── /live                   直播源管理
    │   ├── /live/source-config     源配置
    │   ├── /live/speed-test        测速
    │   └── /live/template          模板
    │
    ├── /icon                   台标管理
    │   ├── /icon/upload            上传
    │   └── /icon/mapping           映射
    │
    ├── /system                 系统管理
    │   ├── /system/update-log      更新日志
    │   ├── /system/cron-log        定时日志
    │   ├── /system/access-log      访问日志
    │   ├── /system/database        数据库
    │   └── /system/file-manager    文件管理
    │
    └── /about                  关于
        ├── /about/help             帮助
        ├── /about/version          版本
        └── /about/donation         打赏

优势：
✅ 每个功能独立页面
✅ 可直接链接（如 /epg/channels）
✅ 浏览器前进/后退可用
✅ 路由守卫（登录检查）
✅ 懒加载（按需加载组件）
```

## API 端点对比

### 旧版：单一端点 + Query 参数

```
GET  /epg/manage.php?get_config
GET  /epg/manage.php?get_channel
GET  /epg/manage.php?get_icon
POST /epg/manage.php?update_config
POST /epg/manage.php (login=1)

问题：
❌ 不符合 REST 规范
❌ 难以理解意图
❌ 缺乏统一设计
```

### 新版：RESTful API

```
# 认证
POST   /api/auth.php?action=login          登录
POST   /api/auth.php?action=logout         登出
POST   /api/auth.php?action=change_password 修改密码
GET    /api/auth.php                       检查状态

# 配置
GET    /api/config.php                     获取配置
POST   /api/config.php                     更新配置
GET    /api/config.php?action=get_env      环境信息

# EPG
GET    /api/epg.php?action=get_channel     频道列表
GET    /api/epg.php?action=get_epg&channel=CCTV1  频道EPG
POST   /api/epg.php?action=save_channel_bind  保存绑定

# 直播源
GET    /api/live.php                       直播源列表
POST   /api/live.php?action=download_source 下载源

# 台标
GET    /api/icon.php                       台标列表
POST   /api/icon.php?action=upload         上传台标

# 系统
GET    /api/system.php?action=update_logs  更新日志
POST   /api/system.php?action=update       触发更新

优势：
✅ 符合 REST 规范
✅ 意图清晰
✅ 易于扩展
✅ 统一响应格式
```

## 组件化示例

### 旧版：单体 HTML

```html
<!-- manage.html (844 行) -->
<div class="container">
    <h2>管理配置</h2>
    
    <!-- EPG 源配置 -->
    <label>EPG地址</label>
    <textarea id="xml_urls"></textarea>
    
    <!-- 频道别名 -->
    <label>频道别名</label>
    <textarea id="channel_mappings"></textarea>
    
    <!-- 定时任务 -->
    <label>定时任务</label>
    <input id="start_time" type="time">
    <input id="end_time" type="time">
    
    <!-- 按钮 -->
    <button onclick="updateConfig()">保存配置</button>
    <button onclick="showModal('channel')">频道管理</button>
    <button onclick="showModal('icon')">台标管理</button>
    
    <!-- 频道管理模态框 -->
    <div id="channelModal" class="modal">
        <!-- 200+ 行 HTML -->
    </div>
    
    <!-- 台标管理模态框 -->
    <div id="iconModal" class="modal">
        <!-- 200+ 行 HTML -->
    </div>
    
    <!-- ... 更多模态框 ... -->
</div>

问题：
❌ 单文件 800+ 行
❌ 逻辑混乱
❌ 难以复用
❌ 难以测试
```

### 新版：组件化

```vue
<!-- Config/Index.vue (80 行) -->
<template>
  <div class="config-container">
    <el-card>
      <h2>管理配置</h2>
      
      <!-- 使用子组件 -->
      <EpgSourceEditor v-model="config.xml_urls" />
      <ChannelMappingEditor v-model="config.channel_mappings" />
      <SchedulerConfig v-model="config.scheduler" />
      
      <el-button @click="saveConfig">保存配置</el-button>
      <el-button @click="$router.push('/epg/channels')">
        频道管理
      </el-button>
      <el-button @click="$router.push('/icon')">
        台标管理
      </el-button>
    </el-card>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useConfigStore } from '@/stores/config'
import EpgSourceEditor from './components/EpgSourceEditor.vue'
import ChannelMappingEditor from './components/ChannelMappingEditor.vue'
import SchedulerConfig from './components/SchedulerConfig.vue'

const configStore = useConfigStore()
const config = ref(configStore.config)

const saveConfig = async () => {
  await configStore.updateConfig(config.value)
}
</script>

优势：
✅ 单一职责
✅ 逻辑清晰
✅ 可复用组件
✅ 易于测试
✅ TypeScript 类型检查
```

## 状态管理示例

### 旧版：全局变量

```javascript
// manage.js
let config = null;
let channels = [];
let icons = [];

function loadConfig() {
    fetch('manage.php?get_config')
        .then(res => res.json())
        .then(data => {
            config = data;
            // 手动更新 DOM
            document.getElementById('xml_urls').value = config.xml_urls.join('\n');
        });
}

function updateConfig() {
    const formData = new FormData();
    formData.append('xml_urls', document.getElementById('xml_urls').value);
    
    fetch('manage.php', {method: 'POST', body: formData})
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert('保存成功');
                loadConfig(); // 重新加载
            }
        });
}

问题：
❌ 全局变量污染
❌ 手动 DOM 操作
❌ 状态分散
❌ 难以调试
```

### 新版：Pinia Store

```typescript
// stores/config.ts
import { defineStore } from 'pinia'
import { ref } from 'vue'
import { configApi, type Config } from '@/api/config'

export const useConfigStore = defineStore('config', () => {
  // 状态
  const config = ref<Config | null>(null)
  const loading = ref(false)

  // Actions
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
      await fetchConfig() // 自动重新加载
    } finally {
      loading.value = false
    }
  }

  return { config, loading, fetchConfig, updateConfig }
})

// 组件中使用
const configStore = useConfigStore()
await configStore.fetchConfig()  // 加载
await configStore.updateConfig({ days_to_keep: 7 })  // 更新

优势：
✅ 集中式状态管理
✅ 响应式数据（自动更新 UI）
✅ TypeScript 类型支持
✅ DevTools 调试
✅ 持久化支持
```

---

**图表说明：** 本文档提供架构、目录、数据流、路由、API、组件、状态管理的可视化对比，帮助理解迁移方案。
