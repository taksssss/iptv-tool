# 📋 代码迁移映射详细表

## 一、页面/组件映射

### 1.1 登录相关

| 旧代码 | 新代码 | 说明 |
|-------|--------|------|
| `assets/html/login.html` | `frontend/src/views/Login.vue` | 完整的登录页面组件 |
| `login.html` 中的登录表单 | `Login.vue` 中的 `el-form` | 使用 Element Plus 表单 |
| `login.html` 中的修改密码模态框 | `Login.vue` 中的 `el-dialog` | 集成在同一组件中 |
| `assets/css/login.css` | `Login.vue` 中的 `<style scoped>` | CSS 模块化 |

### 1.2 管理页面主体

| 旧代码 | 新代码 | 说明 |
|-------|--------|------|
| `assets/html/manage.html` | 拆分为多个 Vue 组件 | 模块化设计 |
| `manage.html` 顶部导航 | `components/AppHeader.vue` | 独立头部组件 |
| `manage.html` 主表单区域 | `views/Config/Index.vue` | 配置主页 |
| `manage.html` 底部信息 | `components/AppFooter.vue` | 独立底部组件 |
| `assets/css/manage.css` | 分散到各个组件的 `<style scoped>` | CSS 模块化 |

### 1.3 配置管理模块

| 旧代码片段 | 新 Vue 组件 | 功能 |
|----------|------------|------|
| `<textarea id="xml_urls">` | `Config/EpgSource.vue` | EPG 源配置编辑器 |
| `<textarea id="channel_mappings">` | `Config/ChannelMapping.vue` | 频道别名编辑器 |
| `<input id="start_time">` | `Config/Scheduler.vue` | 定时任务时间选择 |
| `<select id="days_to_keep">` | `Config/Index.vue` | 数据保存天数设置 |
| "更多设置" 按钮触发的模态框 | `Config/Advanced.vue` | 高级配置页面 |

### 1.4 模态框 → 独立页面

| 旧模态框 (manage.html) | 新 Vue 页面 | 路由路径 |
|---------------------|-----------|---------|
| `<div id="channelModal">` | `views/Epg/ChannelList.vue` | `/epg/channels` |
| `<div id="iconModal">` | `views/Icon/Index.vue` | `/icon` |
| `<div id="liveModal">` | `views/Live/Index.vue` | `/live` |
| `<div id="updateLogModal">` | `views/System/UpdateLog.vue` | `/system/update-log` |
| `<div id="cronLogModal">` | `views/System/CronLog.vue` | `/system/cron-log` |
| `<div id="accessLogModal">` | `views/System/AccessLog.vue` | `/system/access-log` |
| `<div id="helpModal">` | `views/About/Help.vue` | `/about/help` |
| `<div id="versionLogModal">` | `views/About/Version.vue` | `/about/version` |

## 二、JavaScript 函数映射

### 2.1 核心功能函数 (manage.js)

| 旧函数名 | 新位置 | 类型 | 说明 |
|---------|--------|------|------|
| `showModal(modalId)` | `composables/useModal.ts` | Composable | 通用模态框控制 |
| `showMessageModal(message)` | `useNotification.ts` | Composable | 使用 ElMessage |
| `updateConfigFields()` | `stores/config.ts` | Store Action | 配置更新逻辑 |
| `logout()` | `stores/auth.ts` | Store Action | 退出登录 |
| `commentAll(textareaId)` | 组件内部方法 | Method | 注释切换功能 |
| `loadChannelData()` | `composables/useEpg.ts` | Composable | 频道数据加载 |
| `loadIconData()` | `composables/useIcon.ts` | Composable | 台标数据加载 |
| `loadLiveData()` | `composables/useLive.ts` | Composable | 直播源数据加载 |
| `showExecResult(url)` | `composables/useSystem.ts` | Composable | 执行结果显示 |
| `deleteUnusedIcons()` | `api/icon.ts` | API Function | 删除未使用台标 |

### 2.2 DOM 操作 → 响应式数据

| 旧代码 (jQuery 风格) | 新代码 (Vue 3) | 说明 |
|-------------------|---------------|------|
| `document.getElementById('xml_urls').value` | `const xmlUrls = ref('')` | 响应式变量 |
| `$('#channelModal').show()` | `showChannelModal.value = true` | 响应式显示控制 |
| `document.querySelector('.checkbox').checked` | `const isDark = ref(false)` | 响应式状态 |
| `document.body.classList.add('dark')` | `useTheme().setTheme('dark')` | Composable 函数 |

### 2.3 事件监听 → Vue 事件

| 旧代码 | 新代码 | 说明 |
|-------|--------|------|
| `form.addEventListener('submit', ...)` | `<form @submit.prevent="handleSubmit">` | Vue 模板事件 |
| `button.onclick = function() {...}` | `<button @click="handleClick">` | Vue 模板事件 |
| `window.addEventListener('DOMContentLoaded', ...)` | `onMounted(() => {...})` | Vue 生命周期钩子 |
| `document.addEventListener('keydown', ...)` | `@keydown="handleKeydown"` | Vue 键盘事件 |

## 三、PHP 后端映射

### 3.1 manage.php 功能拆分

| 原 manage.php 功能 | 新 API 文件 | HTTP 方法 | 端点 |
|------------------|-----------|---------|------|
| 登录验证 (POST + login) | `api/auth.php` | POST | `/api/auth.php?action=login` |
| 修改密码 (POST + change_password) | `api/auth.php` | POST | `/api/auth.php?action=change_password` |
| 检查登录状态 | `api/auth.php` | GET | `/api/auth.php` |
| 退出登录 | `api/auth.php` | POST | `/api/auth.php?action=logout` |
| 获取配置 (GET + get_config) | `api/config.php` | GET | `/api/config.php` |
| 更新配置 (POST + update_config) | `api/config.php` | POST | `/api/config.php` |
| 获取频道列表 (GET + get_channel) | `api/epg.php` | GET | `/api/epg.php?action=get_channel` |
| 获取频道 EPG (GET + get_epg_by_channel) | `api/epg.php` | GET | `/api/epg.php?action=get_epg&channel=xxx` |
| 获取台标列表 (GET + get_icon) | `api/icon.php` | GET | `/api/icon.php` |
| 上传台标 (POST) | `api/icon.php` | POST | `/api/icon.php?action=upload` |
| 获取直播源 (GET + get_live_data) | `api/live.php` | GET | `/api/live.php` |
| 获取更新日志 (GET + get_update_logs) | `api/system.php` | GET | `/api/system.php?action=update_logs` |
| 获取定时日志 (GET + get_cron_logs) | `api/system.php` | GET | `/api/system.php?action=cron_logs` |
| 触发数据更新 | `api/system.php` | POST | `/api/system.php?action=update` |

### 3.2 公共函数复用

| 原 public.php 函数 | 复用方式 | 说明 |
|------------------|---------|------|
| `initialDB()` | 直接在 API 文件中 `require_once '../public.php'` | 初始化数据库 |
| `$Config` 全局变量 | API 文件中直接使用 | 配置对象 |
| `$db` PDO 实例 | API 文件中直接使用 | 数据库连接 |
| OpenCC 简繁转换 | API 文件中直接使用 | 保持不变 |

### 3.3 URL 路径变化

| 旧 URL | 新 URL (前端) | 新 URL (API) | 说明 |
|--------|--------------|-------------|------|
| `/epg/manage.php` | `/` (SPA 路由) | N/A | 前端接管 |
| `/epg/manage.php?get_config` | N/A | `/epg/api/config.php` | RESTful API |
| `/epg/index.php` | `/epg/index.php` | `/epg/index.php` | **保持不变**（公共接口） |
| `/epg/proxy.php` | `/epg/proxy.php` | `/epg/proxy.php` | **保持不变**（代理服务） |

## 四、数据流映射

### 4.1 配置保存流程对比

**旧流程：**
```
用户填写表单 
  → 点击"保存配置" 
  → manage.js 收集表单数据 
  → fetch('manage.php', {method: 'POST', body: formData}) 
  → manage.php 处理 POST 请求 
  → updateConfigFields() 函数 
  → 写入 config.json 
  → 返回 JSON 响应 
  → manage.js 显示成功消息
```

**新流程：**
```
用户填写表单 (Vue 响应式表单)
  → 点击"保存配置" 
  → configStore.updateConfig(formData) (Pinia Action)
  → configApi.updateConfig(formData) (Axios)
  → api/config.php 处理 POST 请求
  → 复用 manage.php 的 updateConfigFields() 逻辑
  → 写入 config.json
  → 返回 JSON 响应
  → Axios 拦截器处理响应
  → Pinia Store 更新状态
  → Vue 组件自动更新 UI
  → ElMessage 显示成功提示
```

### 4.2 登录认证流程对比

**旧流程：**
```
用户输入密码 
  → 提交表单到 manage.php 
  → PHP Session 验证 
  → 设置 $_SESSION['loggedin'] = true 
  → 重定向或返回 HTML
```

**新流程：**
```
用户输入密码
  → authStore.login(password) (Pinia Action)
  → authApi.login({password}) (Axios)
  → api/auth.php 验证密码
  → 设置 $_SESSION['loggedin'] = true
  → 返回 JSON {success: true}
  → Pinia Store 更新 isLoggedIn = true
  → Router 跳转到首页 ('/')
```

### 4.3 数据更新流程

**旧流程：**
```
点击"更新数据"按钮 
  → showExecResult('update.php') 
  → 打开模态框显示日志 
  → 长轮询 update.php 
  → 显示实时日志
```

**新流程：**
```
点击"更新数据"按钮
  → systemApi.updateData() (Axios)
  → api/system.php?action=update
  → 调用 update.php 逻辑
  → 返回日志或进度
  → LogViewer 组件显示日志
  → (可选) WebSocket 实时推送进度
```

## 五、样式迁移映射

### 5.1 CSS 类名映射

| 旧 CSS 类 (manage.css) | 新方案 | 说明 |
|----------------------|--------|------|
| `.container` | Element Plus `el-container` | 使用 UI 库布局 |
| `.form-row` | `el-row` + `el-col` | 响应式栅格 |
| `.button-container` | `el-space` 或 flexbox | 按钮组布局 |
| `.modal` | `el-dialog` | Element Plus 对话框 |
| `.modal-content` | `el-dialog` 内部 | 自动样式 |
| `.checkbox` | `el-switch` | Element Plus 开关 |
| `.footer` | `AppFooter.vue` scoped style | 组件样式 |

### 5.2 主题系统映射

| 旧代码 | 新代码 | 说明 |
|-------|--------|------|
| `body.dark` 类 | `useTheme()` composable | 主题管理 |
| `localStorage.getItem('theme')` | `useTheme()` 内部实现 | 持久化 |
| CSS 变量 (--bg-color, etc.) | Element Plus 主题变量 | 统一主题系统 |

## 六、第三方库/工具映射

| 旧工具/库 | 新工具/库 | 用途 |
|---------|---------|------|
| 原生 fetch API | Axios | HTTP 请求 |
| 原生 DOM 操作 | Vue 响应式系统 | 数据绑定 |
| 无路由 | Vue Router | SPA 路由 |
| 无状态管理 | Pinia | 状态管理 |
| 原生模态框 | Element Plus Dialog | 对话框 |
| 原生表单验证 | Element Plus Form | 表单验证 |
| 无组件库 | Element Plus | UI 组件 |
| Font Awesome 5.15.4 | Element Plus Icons | 图标库 |
| 手动主题切换 | `useTheme` + CSS 变量 | 主题系统 |

## 七、构建/部署映射

### 7.1 开发环境

| 旧方式 | 新方式 | 说明 |
|-------|--------|------|
| 直接修改 HTML/JS | `npm run dev` (Vite) | 热更新开发 |
| 刷新浏览器查看修改 | HMR 自动刷新 | 极速开发体验 |
| 无需编译 | Vite 按需编译 | ESM 原生支持 |

### 7.2 生产构建

| 旧方式 | 新方式 | 说明 |
|-------|--------|------|
| 直接使用源文件 | `npm run build` | 编译压缩 |
| 无打包 | Rollup 打包 | 代码分割 |
| 手动压缩 | 自动压缩 | Terser/esbuild |
| 直接部署 PHP | Nginx 反向代理 | SPA + API 分离 |

### 7.3 目录结构对比

**旧结构：**
```
epg/
├── manage.php
├── index.php
├── assets/
│   ├── html/
│   │   ├── manage.html
│   │   └── login.html
│   ├── js/
│   │   └── manage.js
│   └── css/
│       ├── manage.css
│       └── login.css
```

**新结构：**
```
epg/
├── api/              # 新增 API 目录
│   ├── auth.php
│   ├── config.php
│   ├── epg.php
│   └── ...
├── dist/             # 新增 Vue 构建输出
│   ├── index.html
│   ├── assets/
│   │   ├── index-[hash].js
│   │   └── index-[hash].css
│   └── ...
├── manage.php        # 保留（兼容）
├── index.php         # 保留（公共接口）
└── ...

frontend/             # 新增 Vue 源码目录
├── src/
│   ├── views/
│   ├── components/
│   ├── api/
│   └── ...
└── package.json
```

## 八、渐进式迁移路径

### 阶段 1：双版本并存
- 旧版：`/epg/manage.php` 继续工作
- 新版：`/` (访问 Vue SPA)
- API：新旧接口共存

### 阶段 2：功能迁移
- 核心功能迁移到 Vue
- 旧版保留为备用
- 数据共享（同一数据库）

### 阶段 3：完全切换
- 新版成为主版本
- 旧版废弃或移除
- 清理旧代码

## 九、兼容性注意事项

### 9.1 Session 共享
- 新旧版本共享同一 PHP Session
- Vue 前端使用 `withCredentials: true` 携带 Cookie
- API 文件需要 `session_start()`

### 9.2 数据库兼容
- 新旧版本使用同一数据库
- 无需数据迁移
- 表结构保持不变

### 9.3 配置文件兼容
- 继续使用 `data/config.json`
- 新增字段向下兼容
- 旧版可读取新配置

## 十、快速查找表

### 查找旧代码对应的新位置

**示例 1：** 我在 `manage.js` 中看到 `showModal('channel')`，对应新代码在哪？
- **答：** `composables/useModal.ts` 或直接在 `views/Epg/ChannelList.vue` 组件中使用 `el-dialog`

**示例 2：** `manage.php` 中的 `get_config` 逻辑移到哪了？
- **答：** `api/config.php` 的 GET 请求处理部分，前端通过 `configApi.getConfig()` 调用

**示例 3：** 登录表单在 `login.html`，新版在哪？
- **答：** `views/Login.vue` 组件

**示例 4：** 主题切换逻辑在哪？
- **答：** `composables/useTheme.ts` + `components/ThemeSwitcher.vue`

**示例 5：** 频道列表模态框变成什么了？
- **答：** 独立页面 `views/Epg/ChannelList.vue`，路由路径 `/epg/channels`
