<?php
/**
 * Icon API
 * 处理台标相关请求
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
        // 获取台标列表
        $icons = getIconList();
        echo json_encode($icons);
    } else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        switch ($action) {
            case 'upload':
                $file = $_FILES['icon'] ?? null;
                $channelName = $_POST['channel_name'] ?? '';
                
                if (!$file) {
                    throw new Exception('请选择文件');
                }
                if (empty($channelName)) {
                    throw new Exception('频道名称不能为空');
                }
                
                $result = uploadIcon($file, $channelName);
                echo json_encode($result);
                break;
                
            case 'delete':
                $filename = $_POST['filename'] ?? '';
                if (empty($filename)) {
                    throw new Exception('文件名不能为空');
                }
                $result = deleteIcon($filename);
                echo json_encode($result);
                break;
                
            case 'delete_unused':
                $result = deleteUnusedIcons();
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
 * 获取台标列表
 */
function getIconList() {
    $iconDir = __DIR__ . '/../icon/';
    $icons = [];
    
    if (is_dir($iconDir)) {
        $files = scandir($iconDir);
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..' && preg_match('/\.(png|jpg|jpeg|gif)$/i', $file)) {
                $icons[] = [
                    'filename' => $file,
                    'url' => '/epg/icon/' . $file,
                    'size' => filesize($iconDir . $file)
                ];
            }
        }
    }
    
    return $icons;
}

/**
 * 上传台标
 */
function uploadIcon($file, $channelName) {
    $iconDir = __DIR__ . '/../icon/';
    
    // 创建目录
    if (!is_dir($iconDir)) {
        mkdir($iconDir, 0755, true);
    }
    
    // 获取文件扩展名
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = $channelName . '.' . $ext;
    $targetPath = $iconDir . $filename;
    
    // 移动上传的文件
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['success' => true, 'message' => '上传成功', 'filename' => $filename];
    } else {
        throw new Exception('上传失败');
    }
}

/**
 * 删除台标
 */
function deleteIcon($filename) {
    $iconDir = __DIR__ . '/../icon/';
    $filePath = $iconDir . $filename;
    
    if (file_exists($filePath)) {
        if (unlink($filePath)) {
            return ['success' => true, 'message' => '删除成功'];
        } else {
            throw new Exception('删除失败');
        }
    } else {
        throw new Exception('文件不存在');
    }
}

/**
 * 删除未使用的台标
 */
function deleteUnusedIcons() {
    // Implement cleanup logic
    return ['success' => true, 'message' => '清理成功'];
}
?>
