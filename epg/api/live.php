<?php
/**
 * Live Source API
 * 处理直播源相关请求
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
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // 获取直播源列表
        $liveData = getLiveSourceList();
        echo json_encode($liveData);
    } else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        switch ($action) {
            case 'parse_source_info':
                $url = $_POST['url'] ?? '';
                if (empty($url)) {
                    throw new Exception('URL 不能为空');
                }
                $info = parseSourceInfo($url);
                echo json_encode($info);
                break;
                
            case 'download_source_data':
                $url = $_POST['url'] ?? '';
                if (empty($url)) {
                    throw new Exception('URL 不能为空');
                }
                $result = downloadSourceData($url);
                echo json_encode($result);
                break;
                
            case 'delete_source_config':
                $id = $_POST['id'] ?? '';
                if (empty($id)) {
                    throw new Exception('ID 不能为空');
                }
                $result = deleteSourceConfig($id);
                echo json_encode($result);
                break;
                
            case 'delete_unused_live_data':
                $result = deleteUnusedLiveData();
                echo json_encode($result);
                break;
                
            default:
                throw new Exception('未知的操作');
        }
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}

/**
 * 获取直播源列表
 */
function getLiveSourceList() {
    global $db;
    
    try {
        $stmt = $db->query("SELECT * FROM live_source ORDER BY id DESC");
        $sources = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $sources[] = $row;
        }
        return $sources;
    } catch (Exception $e) {
        return [];
    }
}

/**
 * 解析源信息
 */
function parseSourceInfo($url) {
    // Implement source parsing logic
    return ['success' => true, 'url' => $url];
}

/**
 * 下载源数据
 */
function downloadSourceData($url) {
    // Implement download logic
    return ['success' => true, 'message' => '下载成功'];
}

/**
 * 删除源配置
 */
function deleteSourceConfig($id) {
    global $db;
    
    try {
        $stmt = $db->prepare("DELETE FROM live_source WHERE id = ?");
        $stmt->execute([$id]);
        return ['success' => true, 'message' => '删除成功'];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * 删除未使用的直播源数据
 */
function deleteUnusedLiveData() {
    // Implement cleanup logic
    return ['success' => true, 'message' => '清理成功'];
}
?>
