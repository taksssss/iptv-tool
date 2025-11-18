# ✅ Vue 3 迁移实施清单

## 📋 项目初始化阶段

### Week 1: 环境搭建 (2-3天)

- [ ] **1.1 创建 Vue 3 项目**
  ```bash
  cd /path/to/iptv-tool
  npm create vite@latest frontend -- --template vue-ts
  cd frontend
  npm install
  ```

- [ ] **1.2 安装核心依赖**
  ```bash
  # 核心库
  npm install vue@^3.4.0 vue-router@^4.2.5 pinia@^2.1.7 axios@^1.6.0
  
  # UI 组件库
  npm install element-plus@^2.5.0 @element-plus/icons-vue@^2.3.1
  
  # 工具库
  npm install @vueuse/core@^10.7.0
  
  # 开发依赖
  npm install -D @vitejs/plugin-vue@^5.0.0 @types/node@^20.10.0
  npm install -D typescript@~5.3.0 vue-tsc@^1.8.25
  npm install -D eslint@^8.55.0 eslint-plugin-vue@^9.19.0
  npm install -D @typescript-eslint/eslint-plugin@^6.14.0
  npm install -D @typescript-eslint/parser@^6.14.0
  npm install -D prettier@^3.1.0
  npm install -D tailwindcss@^3.3.6 postcss@^8.4.32 autoprefixer@^10.4.16
  ```

- [ ] **1.3 复制模板文件**
  ```bash
  # 从 vue-templates 目录复制配置文件
  cp vue-templates/package.json frontend/
  cp vue-templates/vite.config.ts frontend/
  cp vue-templates/tsconfig.json frontend/
  ```

- [ ] **1.4 创建目录结构**
  ```bash
  cd frontend/src
  mkdir -p api assets/{styles,images} components/{Layout,Common,Form}
  mkdir -p composables router stores types utils views
  mkdir -p views/{Config,Epg,Live,Icon,System,About}
  ```

- [ ] **1.5 配置环境变量**
  - 创建 `.env.development`
  - 创建 `.env.production`
  - 配置 API 基础路径

- [ ] **1.6 配置 Vite**
  - 配置路径别名 `@`
  - 配置开发服务器代理
  - 配置构建输出路径

- [ ] **1.7 配置 ESLint + Prettier**
  - 创建 `.eslintrc.json`
  - 创建 `.prettierrc.json`
  - 配置 Vue 3 规则

---

## 🏗️ 基础架构阶段

### Week 2: 核心功能搭建 (3-4天)

#### 路由系统

- [ ] **2.1 创建路由配置**
  - [ ] `router/index.ts` - 路由主文件
  - [ ] 配置登录路由 (无需认证)
  - [ ] 配置管理后台路由 (需要认证)
  - [ ] 实现路由守卫 (beforeEach)
  - [ ] 配置路由懒加载

#### 状态管理

- [ ] **2.2 创建 Pinia Stores**
  - [ ] `stores/auth.ts` - 认证状态
    - `isLoggedIn`
    - `login()`, `logout()`, `checkLoginStatus()`
  - [ ] `stores/config.ts` - 配置状态
    - `config`, `loading`
    - `fetchConfig()`, `updateConfig()`
  - [ ] `stores/epg.ts` - EPG 数据状态
  - [ ] `stores/live.ts` - 直播源状态
  - [ ] `stores/system.ts` - 系统状态

#### API 封装

- [ ] **2.3 创建 API 模块**
  - [ ] `api/index.ts` - Axios 实例配置
    - 配置 baseURL
    - 配置 withCredentials
    - 请求拦截器
    - 响应拦截器 (401 跳转登录)
  - [ ] `api/auth.ts` - 认证 API
    - `login()`, `logout()`, `changePassword()`, `checkLoginStatus()`
  - [ ] `api/config.ts` - 配置 API
    - `getConfig()`, `updateConfig()`, `getEnv()`
  - [ ] `api/epg.ts` - EPG API
  - [ ] `api/live.ts` - 直播源 API
  - [ ] `api/icon.ts` - 台标 API
  - [ ] `api/system.ts` - 系统 API

#### TypeScript 类型

- [ ] **2.4 定义类型**
  - [ ] `types/api.ts` - API 响应类型
  - [ ] `types/config.ts` - 配置类型
  - [ ] `types/epg.ts` - EPG 类型
  - [ ] `types/live.ts` - 直播源类型

#### Composables

- [ ] **2.5 创建组合式函数**
  - [ ] `composables/useAuth.ts` - 认证逻辑
  - [ ] `composables/useTheme.ts` - 主题切换
  - [ ] `composables/useModal.ts` - 模态框控制
  - [ ] `composables/useNotification.ts` - 通知提示
  - [ ] `composables/useTable.ts` - 表格数据处理

---

## 🎨 布局组件阶段

### Week 3: 布局与公共组件 (3-4天)

#### 布局组件

- [ ] **3.1 主布局**
  - [ ] `components/Layout/AppLayout.vue`
    - 响应式布局（桌面/移动端）
    - 集成 Header、Sidebar、Footer
    - 路由出口 (router-view)

- [ ] **3.2 头部组件**
  - [ ] `components/Layout/AppHeader.vue`
    - Logo + 标题
    - 主题切换器
    - 用户信息下拉菜单
    - 移动端汉堡菜单按钮
    - 修改密码对话框

- [ ] **3.3 侧边栏组件**
  - [ ] `components/Layout/AppSidebar.vue`
    - Element Plus 菜单组件
    - 导航项配置
    - 路由跳转
    - 移动端适配 (Drawer)

- [ ] **3.4 底部组件**
  - [ ] `components/Layout/AppFooter.vue`
    - GitHub 链接
    - 版本信息
    - 使用说明链接
    - 打赏链接

- [ ] **3.5 主题切换器**
  - [ ] `components/Layout/ThemeSwitcher.vue`
    - 三态切换 (Light/Dark/Auto)
    - 图标显示
    - LocalStorage 持久化
    - CSS 变量切换

#### 公共组件

- [ ] **3.6 表单组件**
  - [ ] `components/Form/FormInput.vue`
  - [ ] `components/Form/FormTextarea.vue`
  - [ ] `components/Form/FormSelect.vue`
  - [ ] `components/Form/FormCheckbox.vue`

- [ ] **3.7 数据展示组件**
  - [ ] `components/Common/DataTable.vue`
    - 排序、筛选、分页
    - 响应式表格/卡片切换
  - [ ] `components/Common/LogViewer.vue`
    - 日志实时显示
    - 滚动加载
  - [ ] `components/Common/CodeEditor.vue`
    - 代码高亮
    - 快捷键支持 (Ctrl+/)

- [ ] **3.8 交互组件**
  - [ ] `components/Common/Modal.vue` (通用模态框)
  - [ ] `components/Common/LoadingSpinner.vue`

---

## 📄 页面组件阶段

### Week 4-5: 核心页面开发 (7-10天)

#### 登录与首页

- [ ] **4.1 登录页面**
  - [ ] `views/Login.vue`
    - 登录表单
    - 表单验证
    - 修改密码对话框
    - 响应式布局

- [ ] **4.2 仪表盘**
  - [ ] `views/Dashboard.vue`
    - 系统概览卡片
    - 快捷操作按钮
    - 最近日志显示

#### 配置管理

- [ ] **4.3 配置页面**
  - [ ] `views/Config/Index.vue` - 配置主页
    - 数据保存天数
    - 快速配置面板
  - [ ] `views/Config/EpgSource.vue` - EPG 源配置
    - Textarea 编辑器
    - 注释切换 (Ctrl+/)
    - 保存/重置按钮
    - 频道绑定 EPG 源配置
  - [ ] `views/Config/ChannelMapping.vue` - 频道别名
    - 键值对编辑器
    - 添加/删除映射
    - 导入/导出
  - [ ] `views/Config/Scheduler.vue` - 定时任务
    - 开始时间选择
    - 结束时间选择
    - 间隔周期设置
    - 定时日志查看
  - [ ] `views/Config/Advanced.vue` - 高级设置
    - Token 配置
    - User-Agent 配置
    - IP 黑白名单
    - 数据库切换 (SQLite/MySQL)
    - 缓存配置
    - 其他高级选项

#### EPG 管理

- [ ] **4.4 EPG 页面**
  - [ ] `views/Epg/Index.vue` - EPG 概览
    - 数据统计
    - 快捷操作
  - [ ] `views/Epg/ChannelList.vue` - 频道列表
    - DataTable 展示
    - 频道搜索
    - 频道详情
    - 批量操作
  - [ ] `views/Epg/ChannelBind.vue` - 频道绑定
    - 频道 EPG 源绑定管理
  - [ ] `views/Epg/GenerateList.vue` - 生成列表
    - xmltv 生成列表管理

#### 直播源管理

- [ ] **4.5 直播源页面**
  - [ ] `views/Live/Index.vue` - 直播源列表
    - 源列表展示
    - 源状态显示
  - [ ] `views/Live/SourceConfig.vue` - 源配置
    - 添加/编辑直播源
    - TXT/M3U 格式支持
  - [ ] `views/Live/SpeedTest.vue` - 测速管理
    - 测速配置
    - 测速结果展示
  - [ ] `views/Live/Template.vue` - 模板管理
    - 模板列表
    - 添加/编辑模板

#### 台标管理

- [ ] **4.6 台标页面**
  - [ ] `views/Icon/Index.vue` - 台标列表
    - 网格视图展示
    - 台标搜索
    - 模糊匹配设置
  - [ ] `views/Icon/Upload.vue` - 上传台标
    - 文件上传
    - 批量上传
  - [ ] `views/Icon/Mapping.vue` - 台标映射
    - 频道-台标映射管理

#### 系统管理

- [ ] **4.7 系统页面**
  - [ ] `views/System/UpdateLog.vue` - 更新日志
    - LogViewer 组件
    - 日志筛选
    - 清除日志
  - [ ] `views/System/CronLog.vue` - 定时日志
    - 定时任务执行记录
    - 时间表显示
  - [ ] `views/System/AccessLog.vue` - 访问日志
    - 访问记录
    - 统计图表
    - IP 筛选
  - [ ] `views/System/Database.vue` - 数据库管理
    - 集成 phpLiteAdmin (iframe)
  - [ ] `views/System/FileManager.vue` - 文件管理
    - 集成 TinyFileManager (iframe)

#### 关于页面

- [ ] **4.8 关于页面**
  - [ ] `views/About/Help.vue` - 使用说明
    - Markdown 渲染
    - 使用手册展示
  - [ ] `views/About/Version.vue` - 版本信息
    - 版本日志
    - 更新检查
  - [ ] `views/About/Donation.vue` - 打赏页面
    - 打赏二维码
    - 鸣谢列表

---

## 🔌 PHP 后端 API 阶段

### Week 6: API 开发 (4-5天)

#### API 目录创建

- [ ] **5.1 创建 API 目录**
  ```bash
  cd epg
  mkdir api
  ```

#### 认证 API

- [ ] **5.2 实现认证 API**
  - [ ] `epg/api/auth.php`
    - POST `/api/auth.php?action=login` - 登录
    - POST `/api/auth.php?action=logout` - 登出
    - POST `/api/auth.php?action=change_password` - 修改密码
    - GET `/api/auth.php` - 检查登录状态
  - **复用逻辑：** 复用 `manage.php` 的登录和密码验证逻辑

#### 配置 API

- [ ] **5.3 实现配置 API**
  - [ ] `epg/api/config.php`
    - GET `/api/config.php` - 获取配置
    - POST `/api/config.php` - 更新配置
    - GET `/api/config.php?action=get_env` - 获取环境信息
  - **复用逻辑：** 复用 `manage.php` 的 `updateConfigFields()` 函数

#### EPG API

- [ ] **5.4 实现 EPG API**
  - [ ] `epg/api/epg.php`
    - GET `/api/epg.php?action=get_channel` - 获取频道列表
    - GET `/api/epg.php?action=get_epg&channel=xxx` - 获取频道 EPG
    - GET `/api/epg.php?action=get_channel_bind` - 获取频道绑定
    - POST `/api/epg.php?action=save_channel_bind` - 保存频道绑定
    - GET `/api/epg.php?action=get_gen_list` - 获取生成列表
    - POST `/api/epg.php?action=save_gen_list` - 保存生成列表
  - **复用逻辑：** 复用 `manage.php` 相关查询逻辑

#### 直播源 API

- [ ] **5.5 实现直播源 API**
  - [ ] `epg/api/live.php`
    - GET `/api/live.php` - 获取直播源列表
    - GET `/api/live.php?action=parse_source` - 解析源信息
    - POST `/api/live.php?action=download_source` - 下载源数据
    - POST `/api/live.php?action=delete_source` - 删除源配置
    - POST `/api/live.php?action=speed_test` - 测速
  - **复用逻辑：** 复用 `manage.php` 直播源相关逻辑

#### 台标 API

- [ ] **5.6 实现台标 API**
  - [ ] `epg/api/icon.php`
    - GET `/api/icon.php` - 获取台标列表
    - POST `/api/icon.php?action=upload` - 上传台标
    - DELETE `/api/icon.php?action=delete` - 删除台标
    - POST `/api/icon.php?action=delete_unused` - 删除未使用台标
  - **复用逻辑：** 复用 `manage.php` 台标管理逻辑

#### 系统 API

- [ ] **5.7 实现系统 API**
  - [ ] `epg/api/system.php`
    - GET `/api/system.php?action=update_logs` - 获取更新日志
    - GET `/api/system.php?action=cron_logs` - 获取定时日志
    - GET `/api/system.php?action=access_logs` - 获取访问日志
    - POST `/api/system.php?action=update` - 触发数据更新
    - GET `/api/system.php?action=version_log` - 获取版本日志
    - GET `/api/system.php?action=readme` - 获取使用说明
    - POST `/api/system.php?action=clear_access_log` - 清除访问日志
  - **复用逻辑：** 复用 `manage.php` 日志查询逻辑，调用 `update.php`

#### CORS 配置

- [ ] **5.8 配置 CORS**
  - [ ] 开发环境：在每个 API 文件添加 CORS 头
  - [ ] 生产环境：使用 Nginx 反向代理（无需 CORS）

---

## 🧪 测试与优化阶段

### Week 7: 联调与测试 (4-5天)

#### 功能测试

- [ ] **6.1 登录流程测试**
  - [ ] 登录成功跳转
  - [ ] 登录失败提示
  - [ ] Session 持久化
  - [ ] 修改密码功能
  - [ ] 退出登录

- [ ] **6.2 配置管理测试**
  - [ ] 配置加载
  - [ ] 配置保存
  - [ ] 表单验证
  - [ ] 错误处理

- [ ] **6.3 数据管理测试**
  - [ ] EPG 数据加载
  - [ ] 频道列表展示
  - [ ] 直播源管理
  - [ ] 台标上传/删除

- [ ] **6.4 日志查看测试**
  - [ ] 更新日志加载
  - [ ] 定时日志加载
  - [ ] 访问日志统计

#### 错误处理

- [ ] **6.5 错误处理完善**
  - [ ] API 错误提示
  - [ ] 表单验证错误
  - [ ] 网络错误处理
  - [ ] 401 自动跳转登录
  - [ ] 边界情况处理

#### 性能优化

- [ ] **6.6 性能优化**
  - [ ] 组件懒加载
  - [ ] 图片懒加载
  - [ ] 列表虚拟滚动（长列表）
  - [ ] 防抖/节流处理
  - [ ] 代码分割优化

#### 移动端适配

- [ ] **6.7 移动端适配**
  - [ ] 响应式布局检查
  - [ ] 触摸交互优化
  - [ ] 移动端菜单测试
  - [ ] 表格/卡片切换

#### 浏览器兼容性

- [ ] **6.8 浏览器兼容性测试**
  - [ ] Chrome/Edge 测试
  - [ ] Firefox 测试
  - [ ] Safari 测试
  - [ ] 移动端浏览器

---

## 🚀 部署阶段

### Week 8: 部署与文档 (3-4天)

#### 生产构建

- [ ] **7.1 优化 Vite 配置**
  - [ ] 配置代码分割
  - [ ] 配置资源压缩
  - [ ] 配置 CDN（可选）

- [ ] **7.2 构建生产版本**
  ```bash
  cd frontend
  npm run build
  ```
  - [ ] 检查构建输出
  - [ ] 验证资源路径
  - [ ] 测试构建产物

#### Docker 集成

- [ ] **7.3 更新 Dockerfile**
  - [ ] 添加 Nginx 安装
  - [ ] 复制 Vue 构建产物
  - [ ] 配置启动脚本

- [ ] **7.4 配置 Nginx**
  - [ ] 创建 `nginx.conf`
  - [ ] 配置 SPA 路由（try_files）
  - [ ] 配置 API 代理
  - [ ] 配置静态资源缓存
  - [ ] 配置 Gzip 压缩

- [ ] **7.5 更新 docker-compose.yml**
  - [ ] 配置 Nginx 服务
  - [ ] 配置 PHP-FPM
  - [ ] 配置卷挂载

#### 测试部署

- [ ] **7.6 本地 Docker 测试**
  ```bash
  docker build -t iptv-tool-vue:latest .
  docker run -d -p 5678:80 iptv-tool-vue:latest
  ```
  - [ ] 访问测试
  - [ ] 功能验证
  - [ ] 性能检查

#### 文档编写

- [ ] **7.7 更新文档**
  - [ ] 更新 `README.md`
    - 添加 Vue 版本说明
    - 更新部署步骤
    - 添加开发指南
  - [ ] 编写 `DEVELOPMENT.md`（开发文档）
    - 环境搭建
    - 开发流程
    - 代码规范
  - [ ] 编写 `DEPLOYMENT.md`（部署文档）
    - Docker 部署
    - Nginx 配置
    - 故障排查
  - [ ] 编写 `API.md`（API 文档，可选）
    - API 端点列表
    - 请求/响应示例

#### 迁移指南

- [ ] **7.8 编写迁移指南**
  - [ ] `MIGRATION.md`
    - 旧版到新版的迁移步骤
    - 数据兼容性说明
    - 回退方案
    - 常见问题 (FAQ)

---

## 🎉 发布阶段

### Week 9: 发布与维护

#### Beta 测试

- [ ] **8.1 Beta 测试**
  - [ ] 发布 Beta 版本到 GitHub
  - [ ] 收集用户反馈
  - [ ] 修复 Bug
  - [ ] 性能调优

#### 正式发布

- [ ] **8.2 正式发布**
  - [ ] 更新 `CHANGELOG.md`
  - [ ] 创建 GitHub Release
  - [ ] 发布 Docker 镜像到 Docker Hub
  - [ ] 更新项目主页

#### 后续维护

- [ ] **8.3 后续优化**
  - [ ] 根据用户反馈迭代
  - [ ] 添加新功能
  - [ ] 性能监控
  - [ ] 安全更新

---

## 📊 进度跟踪

### 总体进度估算

| 阶段 | 预计时间 | 状态 |
|-----|---------|------|
| Week 1: 项目初始化 | 2-3天 | ⬜ 未开始 |
| Week 2: 基础架构 | 3-4天 | ⬜ 未开始 |
| Week 3: 布局组件 | 3-4天 | ⬜ 未开始 |
| Week 4-5: 页面组件 | 7-10天 | ⬜ 未开始 |
| Week 6: PHP API | 4-5天 | ⬜ 未开始 |
| Week 7: 测试优化 | 4-5天 | ⬜ 未开始 |
| Week 8: 部署文档 | 3-4天 | ⬜ 未开始 |
| Week 9: 发布维护 | 持续 | ⬜ 未开始 |

**总计：** 约 26-35 个工作日（1人全职开发）

---

## 🎯 里程碑

- [ ] **Milestone 1:** 完成项目初始化和基础架构 (Week 1-2)
- [ ] **Milestone 2:** 完成布局和公共组件 (Week 3)
- [ ] **Milestone 3:** 完成核心页面 50% (Week 4)
- [ ] **Milestone 4:** 完成核心页面 100% (Week 5)
- [ ] **Milestone 5:** 完成 PHP API 开发 (Week 6)
- [ ] **Milestone 6:** 完成测试和优化 (Week 7)
- [ ] **Milestone 7:** 完成部署和文档 (Week 8)
- [ ] **Milestone 8:** 正式发布 (Week 9)

---

## 💡 注意事项

### 开发建议
1. **渐进式开发：** 先完成核心功能，再添加高级特性
2. **频繁测试：** 每完成一个模块就进行测试
3. **代码规范：** 使用 ESLint + Prettier 保持代码一致性
4. **Git 提交：** 小步提交，便于回滚
5. **文档同步：** 代码和文档同步更新

### 风险控制
1. **保留旧版：** 旧版 `manage.php` 保留作为备用
2. **数据备份：** 迁移前备份数据库
3. **灰度发布：** 先发布 Beta 版收集反馈
4. **回退方案：** 准备快速回退到旧版的方案

### 优先级
1. **P0（必须）：** 登录、配置管理、EPG 基本功能
2. **P1（重要）：** 直播源管理、台标管理、日志查看
3. **P2（可选）：** 高级功能、统计图表、实时推送

---

**祝开发顺利！🎉**
