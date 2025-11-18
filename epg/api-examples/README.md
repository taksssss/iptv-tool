# PHP API 示例代码

本目录包含 Vue 3 前端所需的 PHP API 示例代码。

## 📁 文件说明

| 文件 | 功能 | 端点 |
|------|------|------|
| `auth.php` | 认证 API | `/api/auth.php` |
| `config.php` | 配置管理 API | `/api/config.php` |

## 🚀 使用方法

### 1. 复制文件到 API 目录

```bash
# 创建 API 目录
mkdir -p epg/api

# 复制示例文件
cp epg/api-examples/auth.php epg/api/
cp epg/api-examples/config.php epg/api/
```

### 2. 根据需要创建其他 API 文件

参考 `auth.php` 和 `config.php` 的模式，创建：

- `epg/api/epg.php` - EPG 数据管理
- `epg/api/live.php` - 直播源管理
- `epg/api/icon.php` - 台标管理
- `epg/api/system.php` - 系统信息和日志

## 📚 API 设计模式

### 统一响应格式

```json
{
  "success": true,
  "message": "操作成功",
  "data": {}
}
```

### 错误响应格式

```json
{
  "success": false,
  "message": "错误信息",
  "error": "详细错误"
}
```

### HTTP 状态码

- `200` - 成功
- `400` - 请求错误（参数错误等）
- `401` - 未授权（未登录）
- `403` - 禁止访问
- `404` - 资源不存在
- `405` - 方法不允许
- `500` - 服务器内部错误

## 🔐 认证机制

所有 API（除了登录和公共接口）都需要先检查 Session：

```php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}
```

## 🌐 CORS 处理

### 开发环境

在每个 API 文件中添加 CORS 头：

```php
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: http://localhost:3000');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Allow-Credentials: true');
    http_response_code(200);
    exit;
}
```

### 生产环境

使用 Nginx 反向代理，无需在 PHP 中处理 CORS：

```nginx
location /epg/api/ {
    try_files $uri $uri/ /epg/api/$1.php?$query_string;
    fastcgi_pass unix:/var/run/php-fpm.sock;
    fastcgi_index index.php;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
}
```

## 📖 API 端点示例

### auth.php

```bash
# 登录
curl -X POST http://localhost:5678/epg/api/auth.php \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "action=login&password=yourpassword" \
  --cookie-jar cookies.txt

# 检查登录状态
curl -X GET http://localhost:5678/epg/api/auth.php \
  --cookie cookies.txt

# 修改密码
curl -X POST http://localhost:5678/epg/api/auth.php \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "action=change_password&old_password=old&new_password=new" \
  --cookie cookies.txt

# 退出登录
curl -X POST http://localhost:5678/epg/api/auth.php \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "action=logout" \
  --cookie cookies.txt
```

### config.php

```bash
# 获取配置
curl -X GET http://localhost:5678/epg/api/config.php \
  --cookie cookies.txt

# 更新配置
curl -X POST http://localhost:5678/epg/api/config.php \
  -H "Content-Type: application/json" \
  -d '{"days_to_keep":7,"gen_xml":true}' \
  --cookie cookies.txt

# 获取环境信息
curl -X GET "http://localhost:5678/epg/api/config.php?action=get_env"
```

## 🛠️ 开发技巧

### 1. 复用现有逻辑

API 文件应该复用 `manage.php` 中的现有逻辑，避免重复代码：

```php
// 在 config.php 中
require_once '../public.php';

// 复用 manage.php 的函数（如果已经抽取为独立函数）
// 或者直接复制相关逻辑
```

### 2. 错误处理

统一使用 try-catch 处理错误：

```php
try {
    // 业务逻辑
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
```

### 3. 输入验证

始终验证输入数据：

```php
$password = $_POST['password'] ?? '';

if (empty($password)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => '密码不能为空'
    ]);
    return;
}
```

### 4. 日志记录

在生产环境中添加日志：

```php
error_log("API /auth.php: Login attempt from " . $_SERVER['REMOTE_ADDR']);
```

## 🔒 安全建议

1. **HTTPS：** 生产环境必须使用 HTTPS
2. **CSRF 防护：** 添加 CSRF Token（可选）
3. **速率限制：** 限制登录尝试次数
4. **输入过滤：** 过滤和验证所有输入
5. **SQL 注入：** 使用 PDO 预处理语句
6. **XSS 防护：** 输出时进行转义

## 📋 TODO 列表

- [ ] 实现 `epg.php` - EPG 数据 API
- [ ] 实现 `live.php` - 直播源 API
- [ ] 实现 `icon.php` - 台标 API
- [ ] 实现 `system.php` - 系统 API
- [ ] 添加单元测试
- [ ] 添加 API 文档（Swagger/OpenAPI）
- [ ] 实现请求日志记录
- [ ] 添加速率限制

## 🔗 相关文档

- [Vue 3 迁移方案](../VUE_MIGRATION_PLAN.md)
- [代码迁移映射表](../CODE_MIGRATION_MAPPING.md)
- [实施待办清单](../IMPLEMENTATION_TODO.md)
