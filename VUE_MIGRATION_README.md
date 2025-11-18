# 📦 Vue 3 迁移方案 - 完整文档包

本目录包含将 IPTV 工具箱从传统 PHP + 原生 JS 重构为现代 Vue 3 + Vite 架构的完整方案。

## 📚 文档目录

### 核心文档

| 文档 | 说明 | 用途 |
|------|------|------|
| [VUE_MIGRATION_PLAN.md](./VUE_MIGRATION_PLAN.md) | **Vue 3 重构完整方案** | 技术架构、目录结构、组件设计 |
| [CODE_MIGRATION_MAPPING.md](./CODE_MIGRATION_MAPPING.md) | **代码迁移映射表** | 旧代码→新代码对照表 |
| [IMPLEMENTATION_TODO.md](./IMPLEMENTATION_TODO.md) | **实施待办清单** | 分步实施计划（Week 1-9） |

### 模板代码

| 目录/文件 | 说明 |
|---------|------|
| [vue-templates/](./vue-templates/) | Vue 3 前端模板代码 |
| ├── package.json | 依赖配置 |
| ├── vite.config.ts | Vite 构建配置 |
| ├── tsconfig.json | TypeScript 配置 |
| ├── api/ | API 封装示例 |
| ├── components/ | 组件示例 |
| ├── views/ | 页面示例 |
| └── stores/ | 状态管理示例 |
| [epg/api-examples/](./epg/api-examples/) | PHP API 后端示例代码 |
| ├── auth.php | 认证 API |
| ├── config.php | 配置管理 API |
| └── README.md | API 开发指南 |

## 🎯 快速开始

### 1. 阅读文档（30分钟）

```bash
# 按顺序阅读以下文档：
1. VUE_MIGRATION_PLAN.md      # 了解整体方案
2. CODE_MIGRATION_MAPPING.md  # 理解代码对应关系
3. IMPLEMENTATION_TODO.md     # 查看实施步骤
```

### 2. 初始化 Vue 项目（1小时）

```bash
# 创建 Vue 3 项目
cd /path/to/iptv-tool
npm create vite@latest frontend -- --template vue-ts

# 安装依赖
cd frontend
npm install

# 复制模板配置
cp ../vue-templates/package.json .
cp ../vue-templates/vite.config.ts .
cp ../vue-templates/tsconfig.json .

# 安装依赖
npm install
```

### 3. 创建目录结构（15分钟）

```bash
cd frontend/src
mkdir -p api assets/{styles,images} components/{Layout,Common,Form}
mkdir -p composables router stores types utils views
mkdir -p views/{Config,Epg,Live,Icon,System,About}
```

### 4. 复制示例代码（30分钟）

```bash
# 复制 API 封装示例
cp -r ../vue-templates/api ./src/

# 复制组件示例
cp -r ../vue-templates/components ./src/

# 复制页面示例
cp -r ../vue-templates/views ./src/

# 复制状态管理示例
cp -r ../vue-templates/stores ./src/
```

### 5. 启动开发服务器（5分钟）

```bash
# 确保 PHP 后端运行在 5678 端口
docker-compose up -d

# 启动 Vue 开发服务器
npm run dev

# 访问 http://localhost:3000
```

## 📊 方案概览

### 技术栈对比

| 层面 | 旧版 | 新版 |
|-----|------|------|
| **前端框架** | 原生 HTML + JS | Vue 3 (Composition API) |
| **构建工具** | 无 | Vite |
| **UI 组件** | 自定义 CSS | Element Plus |
| **路由** | 单页面 + 模态框 | Vue Router (SPA) |
| **状态管理** | 全局变量 | Pinia |
| **HTTP 请求** | fetch | Axios |
| **类型检查** | 无 | TypeScript |
| **后端** | PHP (manage.php) | PHP RESTful API |

### 核心改进

✅ **模块化架构** - 组件化设计，易于维护  
✅ **响应式数据** - Vue 3 响应式系统，自动 UI 更新  
✅ **路由管理** - SPA 路由，无刷新页面切换  
✅ **状态管理** - 集中式状态管理，数据流清晰  
✅ **类型安全** - TypeScript 提供类型检查  
✅ **开发效率** - HMR 热更新，极速开发体验  
✅ **代码复用** - Composables 逻辑复用  
✅ **移动适配** - 响应式设计，完美支持移动端  

## 📋 功能模块映射

### 旧版模态框 → 新版独立页面

| 旧版（模态框） | 新版（路由页面） | 路径 |
|-------------|---------------|------|
| 频道管理模态框 | 频道列表页面 | `/epg/channels` |
| 台标管理模态框 | 台标管理页面 | `/icon` |
| 直播源管理模态框 | 直播源页面 | `/live` |
| 更新日志模态框 | 更新日志页面 | `/system/update-log` |
| 定时日志模态框 | 定时日志页面 | `/system/cron-log` |
| 帮助模态框 | 帮助页面 | `/about/help` |

### 主要功能拆分

**配置管理** (原 manage.html 主表单)
- `/config` - 配置主页
- `/config/epg-source` - EPG 源配置
- `/config/channel-mapping` - 频道别名
- `/config/scheduler` - 定时任务
- `/config/advanced` - 高级设置

## 🔧 API 设计

### RESTful API 端点

| 功能模块 | API 端点 | 说明 |
|---------|---------|------|
| 认证 | `/api/auth.php` | 登录、登出、修改密码 |
| 配置 | `/api/config.php` | 获取/更新配置 |
| EPG | `/api/epg.php` | EPG 数据管理 |
| 直播源 | `/api/live.php` | 直播源管理 |
| 台标 | `/api/icon.php` | 台标管理 |
| 系统 | `/api/system.php` | 日志、更新等 |

### 公共接口保持不变

以下接口保持兼容，无需修改：
- `/epg/index.php` - EPG 公共接口
- `/epg/proxy.php` - 直播源代理
- `/epg/cron.php` - 定时任务（后台）

## 📈 实施进度估算

| 阶段 | 时间 | 说明 |
|-----|------|------|
| Week 1 | 2-3天 | 项目初始化、环境搭建 |
| Week 2 | 3-4天 | 基础架构（路由、状态、API） |
| Week 3 | 3-4天 | 布局和公共组件 |
| Week 4-5 | 7-10天 | 核心页面开发 |
| Week 6 | 4-5天 | PHP API 开发 |
| Week 7 | 4-5天 | 测试与优化 |
| Week 8 | 3-4天 | 部署与文档 |
| Week 9 | 持续 | 发布与维护 |

**总计：** 约 26-35 个工作日（1人全职）

## 🎓 学习资源

### 官方文档
- [Vue 3 文档](https://cn.vuejs.org/)
- [Vite 文档](https://cn.vitejs.dev/)
- [Element Plus](https://element-plus.org/zh-CN/)
- [Pinia](https://pinia.vuejs.org/zh/)
- [Vue Router](https://router.vuejs.org/zh/)

### 推荐阅读
- Vue 3 Composition API 指南
- TypeScript 基础教程
- RESTful API 设计最佳实践

## ⚠️ 注意事项

### 兼容性保证
- ✅ 数据库完全兼容（共享同一数据库）
- ✅ 配置文件兼容（共用 config.json）
- ✅ Session 兼容（新旧版共享登录状态）
- ✅ 公共接口不变（index.php 保持不变）

### 渐进式迁移
1. **阶段 1：** 双版本并存（旧 manage.php + 新 Vue SPA）
2. **阶段 2：** 功能逐步迁移到 Vue
3. **阶段 3：** 新版成为主版本，旧版可选保留

### 回退方案
- 保留旧版 `manage.php` 作为备用
- 数据库无变动，可随时切回
- Docker 镜像支持版本回退

## 🐛 故障排查

### 常见问题

**Q: Vue 开发服务器无法连接 PHP API？**  
A: 检查 `vite.config.ts` 中的代理配置，确保 PHP 后端运行在 5678 端口。

**Q: 登录后刷新页面又要重新登录？**  
A: 检查 Axios 配置中的 `withCredentials: true`，确保携带 Cookie。

**Q: 构建后访问 404？**  
A: 检查 Nginx 配置，SPA 需要 `try_files` 规则。

## 📞 支持

如有问题或建议，请：
1. 查阅相关文档
2. 在 GitHub 提 Issue
3. 参考示例代码

---

**祝重构顺利！🚀**

*Created by: GitHub Copilot*  
*Date: 2024-11-18*
