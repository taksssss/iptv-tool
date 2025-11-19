<?php
/**
 * @file config.php
 * @brief 配置管理 API
 * 
 * 处理配置的获取和更新
 * 
 * 示例用法：
 * - GET  /api/config.php                获取配置
 * - POST /api/config.php                更新配置
 * - GET  /api/config.php?action=get_env 获取环境信息
 */

// 引入公共脚本
require_once '../public.php';

// 启动 Session
session_start();

// 检查登录状态（除了获取环境信息）
$action = $_GET['action'] ?? '';
if ($action !== 'get_env') {
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
}

// 设置响应头
header('Content-Type: application/json; charset=utf-8');

// 开发环境 CORS 支持
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: http://localhost:3000');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Allow-Credentials: true');
    http_response_code(200);
    exit;
}

// 处理请求
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            handleGet($Config, $serverUrl);
            break;
            
        case 'POST':
            handlePost($Config, $configPath);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * 处理 GET 请求
 */
function handleGet(&$Config, $serverUrl) {
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'get_env':
            // 获取环境信息
            $redirect = false;
            $testUrl = 'http://127.0.0.1/tv.m3u';
            $context = stream_context_create(['http' => ['method' => 'HEAD']]);
            $headers = @get_headers($testUrl, 1, $context);
            if ($headers && strpos($headers[0], '404') === false) {
                $redirect = true;
            }
            
            echo json_encode([
                'server_url' => $serverUrl,
                'redirect' => $redirect
            ]);
            break;
            
        default:
            // 获取配置
            $response = $Config;
            
            // 添加 token MD5
            if (isset($response['token'])) {
                $response['token_md5'] = substr(md5($response['token']), 0, 8);
            }
            
            echo json_encode($response);
            break;
    }
}

/**
 * 处理 POST 请求（更新配置）
 */
function handlePost(&$Config, $configPath) {
    // 获取 JSON 数据或表单数据
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }
    
    if (empty($input)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => '无效的请求数据'
        ]);
        return;
    }
    
    // 调用配置更新函数（复用 manage.php 的逻辑）
    $result = updateConfigFields($Config, $configPath, $input);
    
    echo json_encode([
        'success' => true,
        'message' => '配置已更新',
        'db_type_set' => $result['db_type_set'] ?? true,
        'interval_time' => $Config['interval_time'] ?? 0,
        'start_time' => $Config['start_time'] ?? '',
        'end_time' => $Config['end_time'] ?? ''
    ]);
}

/**
 * 更新配置字段（复用 manage.php 的逻辑）
 */
function updateConfigFields(&$Config, $configPath, $input) {
    global $db;
    
    // 提取配置字段
    $configKeys = array_keys(array_filter($input, function($key) {
        return $key !== 'update_config' && $key !== 'action';
    }, ARRAY_FILTER_USE_KEY));
    
    // 处理表单数据
    $formData = [];
    foreach ($configKeys as $key) {
        if ($key === 'target_time_zone') {
            $formData[$key] = ($input[$key] === '0') ? 0 : $input[$key];
        } else {
            $formData[$key] = is_numeric($input[$key]) ? intval($input[$key]) : $input[$key];
        }
    }
    
    // 处理 EPG URL 列表
    if (isset($input['xml_urls'])) {
        $urls = is_array($input['xml_urls']) ? $input['xml_urls'] : explode("\n", $input['xml_urls']);
        $formData['xml_urls'] = array_values(array_map(function($url) {
            return preg_replace('/^#\s*(\S+)(\s*#.*)?$/', '# $1$2', trim(str_replace(["，", "：", "！"], [",", ":", "!"], $url)));
        }, $urls));
    }
    
    // 处理定时任务间隔
    if (isset($input['interval_hour']) && isset($input['interval_minute'])) {
        $formData['interval_time'] = intval($input['interval_hour']) * 3600 + intval($input['interval_minute']) * 60;
    }
    
    // 处理 MySQL 配置
    if (isset($input['mysql_host']) && isset($input['mysql_dbname'])) {
        $formData['mysql'] = [
            'host' => $input['mysql_host'] ?? '',
            'dbname' => $input['mysql_dbname'] ?? '',
            'username' => $input['mysql_username'] ?? '',
            'password' => $input['mysql_password'] ?? ''
        ];
    }
    
    // 处理频道别名
    if (isset($input['channel_mappings'])) {
        $mappings = [];
        $lines = is_array($input['channel_mappings']) ? $input['channel_mappings'] : explode("\n", $input['channel_mappings']);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line)) {
                $parts = preg_split('/=》|=>/', $line);
                if (count($parts) === 2) {
                    $mappings[trim($parts[0])] = str_replace("，", ",", trim($parts[1]));
                }
            }
        }
        $formData['channel_mappings'] = $mappings;
    }
    
    // 处理频道绑定 EPG
    if (isset($input['channel_bind_epg'])) {
        if (is_string($input['channel_bind_epg'])) {
            $input['channel_bind_epg'] = json_decode($input['channel_bind_epg'], true);
        }
        
        if (is_array($input['channel_bind_epg'])) {
            $bindEpg = [];
            foreach ($input['channel_bind_epg'] as $item) {
                $epgSrc = preg_replace('/^【已停用】/', '', $item['epg_src'] ?? '');
                if (!empty($item['channels'])) {
                    $bindEpg[$epgSrc] = str_replace("，", ",", trim($item['channels']));
                }
            }
            $formData['channel_bind_epg'] = $bindEpg;
        }
    }
    
    // 更新 $Config
    $oldConfig = $Config;
    foreach ($formData as $key => $value) {
        $Config[$key] = $value;
    }
    
    // 检查 MySQL 有效性
    $db_type_set = true;
    if ($Config['db_type'] === 'mysql') {
        try {
            $dsn = "mysql:host={$Config['mysql']['host']};dbname={$Config['mysql']['dbname']};charset=utf8mb4";
            $testDb = new PDO($dsn, $Config['mysql']['username'] ?? null, $Config['mysql']['password'] ?? null);
            $testDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            $Config['db_type'] = 'sqlite';
            $db_type_set = false;
        }
    }
    
    // 写入配置文件
    file_put_contents($configPath, json_encode($Config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    // 重启 cron.php（如果定时任务配置改变）
    if (isset($formData['interval_time']) || isset($formData['start_time']) || isset($formData['end_time'])) {
        if ($oldConfig['start_time'] !== $Config['start_time'] || 
            $oldConfig['end_time'] !== $Config['end_time'] || 
            $oldConfig['interval_time'] !== $Config['interval_time']) {
            exec('php ' . __DIR__ . '/../cron.php > /dev/null 2>/dev/null &');
        }
    }
    
    return ['db_type_set' => $db_type_set];
}
