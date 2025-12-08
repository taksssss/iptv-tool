<?php
/**
 * @file auth.php
 * @brief 认证 API
 * 
 * 处理登录、登出、修改密码等认证相关操作
 * 
 * 示例用法：
 * - POST /api/auth.php?action=login          登录
 * - POST /api/auth.php?action=logout         登出
 * - POST /api/auth.php?action=change_password 修改密码
 * - GET  /api/auth.php                       检查登录状态
 */

// 引入公共脚本
require_once '../public.php';

// 启动 Session
session_start();

// 设置响应头
header('Content-Type: application/json; charset=utf-8');

// 开发环境 CORS 支持（生产环境使用 Nginx 代理）
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
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($method) {
        case 'GET':
            // 检查登录状态
            echo json_encode([
                'loggedin' => isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true
            ]);
            break;
            
        case 'POST':
            handlePost($action, $Config, $configPath);
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
 * 处理 POST 请求
 */
function handlePost($action, &$Config, $configPath) {
    switch ($action) {
        case 'login':
            handleLogin($Config);
            break;
            
        case 'logout':
            handleLogout();
            break;
            
        case 'change_password':
            handleChangePassword($Config, $configPath);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
}

/**
 * 处理登录
 */
function handleLogin(&$Config) {
    // 获取密码
    $password = $_POST['password'] ?? '';
    
    if (empty($password)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => '密码不能为空'
        ]);
        return;
    }
    
    // 验证密码（MD5）
    $hashedPassword = md5($password);
    
    if ($hashedPassword === $Config['manage_password']) {
        // 密码正确，设置 Session
        $_SESSION['loggedin'] = true;
        $_SESSION['can_access_phpliteadmin'] = true;
        $_SESSION['can_access_tinyfilemanager'] = true;
        
        echo json_encode([
            'success' => true,
            'message' => '登录成功'
        ]);
    } else {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => '密码错误'
        ]);
    }
}

/**
 * 处理登出
 */
function handleLogout() {
    // 检查是否已登录
    if (!isset($_SESSION['loggedin'])) {
        echo json_encode([
            'success' => true,
            'message' => '已退出'
        ]);
        return;
    }
    
    // 销毁 Session
    session_destroy();
    
    echo json_encode([
        'success' => true,
        'message' => '退出成功'
    ]);
}

/**
 * 处理修改密码
 */
function handleChangePassword(&$Config, $configPath) {
    // 检查是否已登录
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => '未登录'
        ]);
        return;
    }
    
    // 获取参数
    $oldPassword = $_POST['old_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    
    if (empty($newPassword)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => '新密码不能为空'
        ]);
        return;
    }
    
    // 首次设置密码（强制修改密码）
    $forceChangePassword = empty($Config['manage_password']);
    
    // 如果不是强制设置密码，则验证原密码
    if (!$forceChangePassword) {
        if (empty($oldPassword)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => '原密码不能为空'
            ]);
            return;
        }
        
        $hashedOldPassword = md5($oldPassword);
        if ($hashedOldPassword !== $Config['manage_password']) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => '原密码错误'
            ]);
            return;
        }
    }
    
    // 更新密码
    $Config['manage_password'] = md5($newPassword);
    
    // 写入配置文件
    file_put_contents(
        $configPath,
        json_encode($Config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );
    
    echo json_encode([
        'success' => true,
        'message' => '密码修改成功'
    ]);
}
