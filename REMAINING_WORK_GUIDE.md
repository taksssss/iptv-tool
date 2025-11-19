# 剩余工作实施指南

## 当前完成状态

### ✅ 已完成（35%）
1. **Phase 1: Configuration Management (100%)**
   - frontend/src/views/Config/Index.vue - 完整实现
   - epg/api/config.php - 完整实现
   - 所有键盘快捷键、验证、MySQL测试

2. **Phase 2: EPG Management (100%)**
   - epg/api/epg.php - 完整实现
   - 6个完整端点，无占位符代码
   - 频道匹配逻辑完全实现

## 剩余工作（65%）

### Phase 3: Live Source Management
需要在 `epg/api/live.php` 中实现的功能（参考 manage.php lines 384-596）：

```php
case 'get_live_data':
    // 1. 读取 source.json 和 template.json
    // 2. 处理配置切换
    // 3. 分页查询 channels 表（100/page）
    // 4. 搜索过滤（6个字段）
    // 5. 合并测速信息从 channels_info
    // 6. 返回完整数据结构
    
case 'parse_source_info':
    // 调用 doParseSourceInfo() 函数
    // 返回解析结果
    
case 'download_source_data':
    // 下载 URL 内容
    // 验证 URL
    // 返回数据或错误
    
case 'delete_source_config':
    // 删除配置对应的数据库记录
    // 删除 JSON 文件中的配置
    // 删除生成的 m3u/txt 文件
    
case 'delete_unused_live_data':
    // 清理未使用的缓存文件
    // 清除修改标记
    // 返回清理统计
```

### Phase 4: Icon Management  
需要在 `epg/api/icon.php` 中实现的功能（参考 manage.php lines 280-525）：

```php
case 'get_icon':
    // 1. 合并数据库频道和 $iconList
    // 2. 去重并排序
    // 3. 分为有台标和无台标
    // 4. 插入默认台标到开头
    // 5. 支持 get_all_icon 参数
    
case 'delete_unused_icons':
    // 1. 获取所有使用中的台标URL
    // 2. 扫描 /data/icon 目录
    // 3. 删除未使用的文件
    // 4. 返回删除数量
```

### Phase 5: System Management
需要在 `epg/api/system.php` 中实现的功能（参考 manage.php lines 218-700）：

```php
case 'get_update_logs':
    // SELECT * FROM update_log
    
case 'get_cron_logs':
    // SELECT * FROM cron_log
    
case 'get_access_log':
    // 1. 分页查询（支持 before_id, after_id）
    // 2. 格式化日志行
    // 3. 检查是否有更早日志
    // 4. 返回 logs 数组 + min_id + max_id
    
case 'get_access_stats':
    // 1. 统计每个IP在每个日期的访问次数
    // 2. 计算拒绝次数和总计
    // 3. 获取最近7天日期
    // 4. 返回统计数据
    
case 'filter_access_log_by_ip':
    // 查询特定IP的所有访问记录
    // 返回格式化的日志
    
case 'clear_access_log':
    // DELETE FROM access_log
    // 返回成功确认
    
case 'get_ip_list':
    // 读取 ipWhiteList.txt 或 ipBlackList.txt
    // 返回IP列表数组
    
case 'save_content_to_file':
    // 保存内容到指定文件
    // 返回成功确认
```

### Phase 6: About & Utilities
需要实现的功能：

```php
case 'get_version_log':
    // 1. 检查更新（可选）
    // 2. 从 Gitee 下载 CHANGELOG.md
    // 3. 使用 Parsedown 转换为 HTML
    // 4. 返回内容 + is_updated 标志
    
case 'get_readme_content':
    // 读取 assets/html/readme.md
    // 转换为 HTML
    // 返回内容
    
case 'test_redis':
    // 尝试连接 Redis
    // 返回成功/失败
```

## 实施步骤

### 1. 完成 Live Source API（优先级最高）
```bash
# 编辑 epg/api/live.php
# 实现所有 5 个 case 语句
# 参考 manage.php lines 384-596
# 测试分页、搜索、配置切换
```

### 2. 完成 Icon API
```bash
# 编辑 epg/api/icon.php
# 实现 2 个 case 语句
# 参考 manage.php lines 280-525
# 测试台标列表和删除
```

### 3. 完成 System API
```bash
# 编辑 epg/api/system.php
# 实现 8 个 case 语句
# 参考 manage.php lines 218-700
# 测试日志查询和IP管理
```

### 4. 完成 About API
```bash
# 在适当的文件中添加 3 个端点
# 实现版本检查和帮助文档
```

### 5. 更新 Vue 组件
```bash
# 确保所有 Vue 组件调用正确的API
# 添加错误处理
# 测试UI交互
```

## 代码模板

### Live Source API 模板
```php
case 'get_live_data':
    // 初始化
    $sourceJsonPath = $liveDir . 'source.json';
    $templateJsonPath = $liveDir . 'template.json';
    
    // 读取 JSON
    $sourceJson = json_decode(@file_get_contents($sourceJsonPath), true) ?: [];
    $templateJson = json_decode(@file_get_contents($templateJsonPath), true) ?: [];
    
    // 获取当前配置
    $liveSourceConfig = $Config['live_source_config'] ?? 'default';
    
    // 分页参数
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $perPage = isset($_GET['per_page']) ? max(1, min(1000, intval($_GET['per_page']))) : 100;
    $offset = ($page - 1) * $perPage;
    
    // 搜索
    $searchKeyword = isset($_GET['search']) ? trim($_GET['search']) : '';
    $searchCondition = '';
    $searchParams = [$liveSourceConfig];
    
    if (!empty($searchKeyword)) {
        $searchCondition = " AND (
            c.channelName LIKE ? OR 
            c.groupPrefix LIKE ? OR 
            c.groupTitle LIKE ? OR 
            c.streamUrl LIKE ? OR 
            c.tvgId LIKE ? OR 
            c.tvgName LIKE ?
        )";
        $searchPattern = '%' . $searchKeyword . '%';
        $searchParams = array_merge($searchParams, array_fill(0, 6, $searchPattern));
    }
    
    // 查询总数
    $countSql = "SELECT COUNT(*) FROM channels c WHERE c.config = ?" . $searchCondition;
    $countStmt = $db->prepare($countSql);
    $countStmt->execute($searchParams);
    $totalCount = $countStmt->fetchColumn();
    
    // 查询数据
    $dataSql = "
        SELECT c.*, ci.resolution, ci.speed
        FROM channels c
        LEFT JOIN channels_info ci ON c.streamUrl = ci.streamUrl
        WHERE c.config = ?" . $searchCondition . "
        LIMIT ? OFFSET ?
    ";
    $stmt = $db->prepare($dataSql);
    $stmt->execute(array_merge($searchParams, [$perPage, $offset]));
    $channelsData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 返回
    echo json_encode([
        'source_content' => implode("\n", $sourceJson[$liveSourceConfig] ?? []),
        'template_content' => implode("\n", $templateJson[$liveSourceConfig] ?? []),
        'channels' => $channelsData,
        'config_options' => array_keys($sourceJson),
        'total_count' => $totalCount,
        'page' => $page,
        'per_page' => $perPage,
    ]);
    break;
```

## 质量检查清单

实施每个功能后，确保：
- [ ] 没有 `echo json_encode([])` 空占位符
- [ ] 所有数据库查询完整实现
- [ ] 所有文件操作有错误处理
- [ ] 参数验证和清理
- [ ] 返回完整的数据结构
- [ ] 与原 manage.php 逻辑一致

## 预计工作量

- Live Source API: 2-3 小时
- Icon API: 30-45 分钟
- System API: 2-3 小时
- About API: 30 分钟
- Vue 组件更新: 1-2 小时
- 测试验证: 1 小时

**总计: 7-10 小时完整实施**

## 验收标准

所有API端点必须：
1. 返回与原 manage.php 相同格式的数据
2. 支持所有原有参数
3. 包含完整的业务逻辑
4. 无任何占位符代码
5. 通过功能测试
