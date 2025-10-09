# Go Migration Guide

## 项目已用 Golang 重写

本项目已从 PHP 完全重写为 Golang，保持所有原有功能不变。

### 主要变化

1. **编程语言**: PHP → Golang
2. **Web 服务器**: Apache + PHP → 内置 Go HTTP 服务器
3. **容器镜像**: 从 PHP Alpine (20MB) → Go Alpine (更小，约 15MB)
4. **性能**: 显著提升，更低的内存占用和更快的响应时间

### 功能对应

所有 PHP 文件已被 Go 代码替代：

- `index.php` → `internal/handlers/index.go` - EPG 接口处理
- `manage.php` → `internal/handlers/manage.go` - 管理界面
- `update.php` → `internal/handlers/update.go` - 数据更新
- `check.php` → `internal/handlers/check.go` - 速度检测
- `proxy.php` → `internal/handlers/proxy.go` - 流媒体代理
- `cron.php` → `internal/cron/` - 定时任务
- `scraper.php` → `internal/scraper/` - 数据抓取
- `public.php` → `internal/utils/` - 公共函数

### 部署方式

#### Docker Compose (推荐)

```bash
docker-compose up -d
```

#### Docker 手动部署

```bash
docker build -t iptv-tool .
docker run -d --name iptv-tool \
  -p 5678:80 \
  -v $HOME/epg:/app/epg/data \
  --restart unless-stopped \
  iptv-tool
```

#### 直接运行 (需要 Go 1.21+)

```bash
go build -o iptv-tool main.go
./iptv-tool -data ./epg/data -port 5678
```

### 配置文件

配置文件格式保持不变，仍使用 `data/config.json`。所有配置项完全兼容。

### 数据库

- 支持 SQLite 和 MySQL，与 PHP 版本完全兼容
- 数据库表结构保持一致
- 可以直接使用现有数据库

### API 接口

所有 API 接口保持不变：

- XMLTV: `http://host:5678/index.php` 或 `http://host:5678/`
- DIYP/百川: `http://host:5678/index.php?ch=CCTV1`
- 超级直播: `http://host:5678/index.php?channel=CCTV1`
- 管理界面: `http://host:5678/manage.php`

### 优势

1. **更好的性能**: Go 的并发模型提供更好的性能
2. **更低的资源占用**: 内存和 CPU 使用更少
3. **单一二进制**: 无需安装运行时，部署更简单
4. **更好的类型安全**: 编译时类型检查减少运行时错误
5. **更快的启动时间**: 容器启动更快

### 注意事项

- PHP 原始代码已备份到 `php-backup/` 目录
- 所有功能已在 Go 中实现，但某些高级功能可能需要进一步测试
- 如遇到问题，可以暂时回退到 PHP 版本 (使用 `Dockerfile.php.bak`)

### 开发

```bash
# 安装依赖
go mod download

# 运行测试
go test ./...

# 构建
go build -o iptv-tool main.go

# 运行
./iptv-tool -data ./epg/data -port 5678
```

### 贡献

欢迎提交 Issue 和 Pull Request！
