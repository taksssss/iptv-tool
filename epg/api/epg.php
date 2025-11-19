<?php
/**
 * EPG API
 * 处理 EPG 数据相关请求
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

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_channel':
            // 获取频道列表
            $channels = getChannelList();
            echo json_encode($channels);
            break;
            
        case 'get_epg':
            // 获取指定频道的 EPG 数据
            $channel = $_GET['channel'] ?? '';
            if (empty($channel)) {
                throw new Exception('频道名称不能为空');
            }
            $epgData = getEpgByChannel($channel);
            echo json_encode($epgData);
            break;
            
        case 'get_channel_bind_epg':
            // 获取频道绑定 EPG 源配置
            $bindData = $Config['channel_bind_epg'] ?? [];
            echo json_encode($bindData);
            break;
            
        case 'save_channel_bind_epg':
            // 保存频道绑定 EPG 源配置
            $data = json_decode(file_get_contents('php://input'), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('无效的 JSON 数据');
            }
            
            $bindData = [];
            foreach ($data as $item) {
                if (!empty($item['epg_src']) && !empty($item['channels'])) {
                    $bindData[$item['epg_src']] = $item['channels'];
                }
            }
            
            $Config['channel_bind_epg'] = $bindData;
            saveConfig($Config);
            
            echo json_encode(['success' => true, 'message' => '保存成功']);
            break;
            
        case 'get_channel_match':
            // 获取频道匹配数据
            echo json_encode([]);
            break;
            
        case 'get_gen_list':
            // 获取生成列表
            echo json_encode([]);
            break;
            
        default:
            throw new Exception('未知的操作');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}

/**
 * 获取频道列表
 */
function getChannelList() {
    global $db;
    
    try {
        $stmt = $db->query("SELECT channel, COUNT(*) as epg_count FROM epg_data GROUP BY channel ORDER BY channel");
        $channels = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $channels[] = $row;
        }
        return $channels;
    } catch (Exception $e) {
        return [];
    }
}

/**
 * 获取指定频道的 EPG 数据
 */
function getEpgByChannel($channel) {
    global $db;
    
    try {
        $stmt = $db->prepare("SELECT * FROM epg_data WHERE channel = ? ORDER BY start DESC LIMIT 100");
        $stmt->execute([$channel]);
        $data = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $data[] = $row;
        }
        return $data;
    } catch (Exception $e) {
        return [];
    }
}
?>
