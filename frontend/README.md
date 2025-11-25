# IPTV Tool - Vue 3 Frontend

这是 IPTV 工具箱的 Vue 3 + Vite 前端应用。

## 功能特性

✅ **已实现：**
- 用户认证（登录/登出）
- 配置管理（EPG源、频道别名、定时任务等）
- 响应式布局
- Element Plus UI组件库

🚧 **框架已就绪，待扩展：**
- EPG 管理
- 直播源管理
- 台标管理
- 系统管理

## 安装

```bash
cd frontend
npm install
```

## 开发

```bash
npm run dev
```

访问 http://localhost:3000

**注意：** 开发模式下，API 请求会被代理到 `http://localhost:5678/epg/api`，请确保 PHP 后端正在运行。

## 构建

```bash
npm run build
```

构建产物将输出到 `../epg/dist/` 目录。

## 项目结构

```
frontend/
├── public/              # 静态资源
├── src/
│   ├── api/            # API 接口封装
│   │   ├── index.js    # Axios 配置
│   │   ├── auth.js     # 认证 API
│   │   └── config.js   # 配置 API
│   ├── assets/         # 资源文件
│   │   └── styles/     # 样式文件
│   ├── components/     # 组件
│   │   └── Layout/     # 布局组件
│   ├── router/         # 路由配置
│   ├── stores/         # Pinia 状态管理
│   │   ├── auth.js     # 认证状态
│   │   └── config.js   # 配置状态
│   ├── views/          # 页面组件
│   │   ├── Auth/       # 认证页面
│   │   ├── Config/     # 配置管理
│   │   └── Dashboard.vue # 仪表盘
│   ├── App.vue         # 根组件
│   └── main.js         # 入口文件
├── index.html
├── vite.config.js
└── package.json
```

## 添加新功能

### 1. 创建新页面

在 `src/views/` 下创建新的 Vue 组件，例如 `src/views/Epg/Index.vue`。

### 2. 添加路由

在 `src/router/index.js` 中添加路由配置：

```javascript
{
  path: '/epg',
  name: 'Epg',
  component: () => import('@/views/Epg/Index.vue')
}
```

### 3. 添加菜单项

在 `src/components/Layout/AppLayout.vue` 中添加菜单项：

```html
<el-menu-item index="/epg">
  <el-icon><Document /></el-icon>
  <span>EPG 管理</span>
</el-menu-item>
```

### 4. 创建 API 模块

在 `src/api/` 下创建新的 API 模块，例如 `src/api/epg.js`：

```javascript
import apiClient from './index'

export const epgApi = {
  getChannels: () => {
    return apiClient.get('/epg.php?action=get_channel')
  }
}
```

### 5. 创建 Store

在 `src/stores/` 下创建新的状态管理模块。

## 技术栈

- **Vue 3** - 渐进式 JavaScript 框架
- **Vite** - 下一代前端构建工具
- **Vue Router** - 官方路由管理器
- **Pinia** - 官方状态管理库
- **Element Plus** - Vue 3 UI 组件库
- **Axios** - HTTP 客户端
- **@vueuse/core** - Vue 组合式工具集

## 环境变量

创建 `.env.development` 和 `.env.production` 文件来配置环境变量：

```
# .env.development
VITE_API_BASE_URL=/epg/api

# .env.production
VITE_API_BASE_URL=/epg/api
```

## 故障排查

### 登录后立即跳转回登录页

检查：
1. PHP 后端是否正在运行
2. Session 是否正常工作
3. API 响应是否正确

### API 请求失败

检查：
1. Vite 代理配置是否正确
2. PHP 后端 API 是否可访问
3. CORS 配置是否正确（开发环境）

### 构建失败

确保所有依赖已正确安装：
```bash
rm -rf node_modules package-lock.json
npm install
```

## 生产部署

1. 构建前端：
```bash
npm run build
```

2. 构建产物会输出到 `../epg/dist/`

3. 配置 Nginx 提供 SPA 路由支持：
```nginx
location / {
    try_files $uri $uri/ /index.html;
}
```

## 许可证

与主项目保持一致。
