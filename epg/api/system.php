<?php
/**
 * System API
 * 处理系统信息相关请求
 */

require_once __DIR__ . '/../public.php';

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// 检查登录状态
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => '未登录']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'update_logs':
            // 获取更新日志
            $logs = getUpdateLogs();
            echo $logs;
            break;
            
        case 'cron_logs':
            // 获取定时日志
            $logs = getCronLogs();
            echo $logs;
            break;
            
        case 'access_logs':
            // 获取访问日志
            $logs = getAccessLogs();
            echo $logs;
            break;
            
        case 'access_stats':
            // 获取访问统计
            $stats = getAccessStats();
            echo json_encode($stats);
            break;
            
        case 'clear_access_log':
            // 清除访问日志
            $result = clearAccessLog();
            echo json_encode($result);
            break;
            
        case 'update':
            // 触发数据更新
            $result = triggerUpdate();
            echo json_encode($result);
            break;
            
        case 'version_log':
            // 获取版本日志
            $log = getVersionLog();
            echo $log;
            break;
            
        case 'readme':
            // 获取使用说明
            $readme = getReadme();
            echo $readme;
            break;
            
        default:
            throw new Exception('未知的操作');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}

/**
 * 获取更新日志
 */
function getUpdateLogs() {
    $logFile = __DIR__ . '/../log/update.log';
    if (file_exists($logFile)) {
        return file_get_contents($logFile);
    }
    return '暂无日志';
}

/**
 * 获取定时日志
 */
function getCronLogs() {
    $logFile = __DIR__ . '/../log/cron.log';
    if (file_exists($logFile)) {
        return file_get_contents($logFile);
    }
    return '暂无日志';
}

/**
 * 获取访问日志
 */
function getAccessLogs() {
    $logFile = __DIR__ . '/../log/access.log';
    if (file_exists($logFile)) {
        return file_get_contents($logFile);
    }
    return '暂无日志';
}

/**
 * 获取访问统计
 */
function getAccessStats() {
    global $db;
    
    try {
        $stmt = $db->query("SELECT COUNT(*) as total FROM access_log");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return ['total' => $row['total'] ?? 0];
    } catch (Exception $e) {
        return ['total' => 0];
    }
}

/**
 * 清除访问日志
 */
function clearAccessLog() {
    $logFile = __DIR__ . '/../log/access.log';
    if (file_exists($logFile)) {
        file_put_contents($logFile, '');
    }
    
    global $db;
    try {
        $db->exec("DELETE FROM access_log");
        return ['success' => true, 'message' => '清除成功'];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * 触发数据更新
 */
function triggerUpdate() {
    // Implement update logic
    return ['success' => true, 'message' => '更新已触发'];
}

/**
 * 获取版本日志
 */
function getVersionLog() {
    $logFile = __DIR__ . '/../../CHANGELOG.md';
    if (file_exists($logFile)) {
        return file_get_contents($logFile);
    }
    return '# 版本日志\n\n暂无版本日志';
}

/**
 * 获取使用说明
 */
function getReadme() {
    $readmeFile = __DIR__ . '/../../README.md';
    if (file_exists($readmeFile)) {
        return file_get_contents($readmeFile);
    }
    return '# 使用说明\n\n暂无使用说明';
}
?>
