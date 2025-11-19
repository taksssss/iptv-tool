<?php
/**
 * System Management API
 * Complete implementation matching original manage.php lines 218-226, 598-811
 */

require_once __DIR__ . '/../public.php';
initialDB();

session_start();

// Check authentication
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// CORS headers
header('Access-Control-Allow-Origin: http://localhost:3000');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'get_update_logs':
            // Get update logs (manage.php lines 218-221)
            $logs = $db->query("SELECT * FROM update_log")->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($logs);
            break;
            
        case 'get_cron_logs':
            // Get cron logs (manage.php lines 223-226)
            $logs = $db->query("SELECT * FROM cron_log")->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($logs);
            break;
            
        case 'get_access_log':
            // Get access log with pagination and real-time updates (manage.php lines 644-703)
            $limit = isset($_GET['limit']) ? min(1000, max(1, (int)$_GET['limit'])) : 100;
            $beforeId = isset($_GET['before_id']) ? (int)$_GET['before_id'] : 0;
            $afterId = isset($_GET['after_id']) ? (int)$_GET['after_id'] : 0;
        
            if ($beforeId > 0) {
                // Load older logs (scroll up)
                $stmt = $db->prepare("SELECT * FROM access_log WHERE id < ? ORDER BY id DESC LIMIT ?");
                $stmt->execute([$beforeId, $limit]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $rows = array_reverse($rows); // Reverse to maintain time order
            } elseif ($afterId > 0) {
                // Load new logs (polling)
                $stmt = $db->prepare("SELECT * FROM access_log WHERE id > ? ORDER BY id ASC");
                $stmt->execute([$afterId]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                // Initial load of latest logs
                $stmt = $db->prepare("SELECT * FROM access_log ORDER BY id DESC LIMIT ?");
                $stmt->execute([$limit]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $rows = array_reverse($rows); // Reverse to maintain time order
            }
        
            if (!$rows) {
                echo json_encode(['success' => true, 'changed' => false, 'logs' => [], 'has_more' => false]);
                break;
            }
        
            $logs = [];
            $minId = PHP_INT_MAX;
            $maxId = 0;
            foreach ($rows as $row) {
                $logs[] = [
                    'id' => (int)$row['id'],
                    'text' => "[{$row['access_time']}] [{$row['client_ip']}] "
                        . ($row['access_denied'] ? "{$row['deny_message']} " : '')
                        . "[{$row['method']}] {$row['url']} | UA: {$row['user_agent']}"
                ];
                $minId = min($minId, (int)$row['id']);
                $maxId = max($maxId, (int)$row['id']);
            }
            
            // Check if there are older logs
            $hasMore = false;
            if ($minId < PHP_INT_MAX) {
                $checkStmt = $db->prepare("SELECT COUNT(*) FROM access_log WHERE id < ?");
                $checkStmt->execute([$minId]);
                $hasMore = $checkStmt->fetchColumn() > 0;
            }
        
            echo json_encode([ 
                'success' => true, 
                'changed' => count($logs) > 0, 
                'logs' => $logs, 
                'min_id' => $minId < PHP_INT_MAX ? $minId : 0,
                'max_id' => $maxId,
                'has_more' => $hasMore
            ]);
            break;
            
        case 'filter_access_log_by_ip':
            // Filter access log by IP (manage.php lines 705-733)
            $ip = isset($_GET['ip']) ? $_GET['ip'] : '';
            
            if (empty($ip)) {
                echo json_encode(['success' => false, 'message' => 'IP地址不能为空']);
                break;
            }
            
            $stmt = $db->prepare("SELECT * FROM access_log WHERE client_ip = ? ORDER BY id ASC");
            $stmt->execute([$ip]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $logs = [];
            foreach ($rows as $row) {
                $logs[] = [
                    'id' => (int)$row['id'],
                    'text' => "[{$row['access_time']}] [{$row['client_ip']}] "
                        . ($row['access_denied'] ? "{$row['deny_message']} " : '')
                        . "[{$row['method']}] {$row['url']} | UA: {$row['user_agent']}"
                ];
            }
            
            echo json_encode([
                'success' => true,
                'ip' => $ip,
                'logs' => $logs,
                'count' => count($logs)
            ]);
            break;
            
        case 'get_access_stats':
            // Get access statistics (manage.php lines 735-774)
            $stmt = $db->query("
                SELECT client_ip, DATE(access_time) AS date,
                        COUNT(*) AS total, SUM(access_denied) AS deny
                FROM access_log
                GROUP BY client_ip, date
            ");
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        
            $ipData = [];
            $dates = [];
        
            foreach ($rows as $r) {
                $ip = $r['client_ip'];
                $date = $r['date'];
                $dates[$date] = true;
        
                if (!isset($ipData[$ip])) {
                    $ipData[$ip] = ['ip' => $ip, 'counts' => [], 'total' => 0, 'deny' => 0];
                }
        
                $ipData[$ip]['counts'][$date] = (int)$r['total'];
                $ipData[$ip]['total'] += (int)$r['total'];
                $ipData[$ip]['deny'] += (int)$r['deny'];
            }
        
            $dates = array_keys($dates);
            sort($dates);
        
            foreach ($ipData as &$row) {
                $counts = [];
                foreach ($dates as $d) {
                    $counts[] = isset($row['counts'][$d]) ? $row['counts'][$d] : 0;
                }
                $row['counts'] = $counts;
            }
            unset($row);
            
            echo json_encode(['success' => true, 'ipData' => array_values($ipData), 'dates' => $dates]);
            break;
            
        case 'clear_access_log':
            // Clear access log (manage.php lines 776-779)
            $res = $db->exec("DELETE FROM access_log") !== false;
            echo json_encode(['success' => $res]);
            break;
            
        case 'get_ip_list':
            // Get IP whitelist/blacklist (manage.php lines 781-791)
            $filename = basename($_GET['file'] ?? 'ipBlackList.txt'); // Only allow base filename
            $file_path = __DIR__ . "/../data/{$filename}";
        
            if (file_exists($file_path)) {
                $content = file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                echo json_encode(['success' => true, 'list' => $content]);
            } else {
                echo json_encode(['success' => true, 'list' => []]);
            }
            break;
            
        case 'save_content_to_file':
            // Save content to file
            $filename = basename($_POST['filename'] ?? '');
            $content = $_POST['content'] ?? '';
            
            if (empty($filename)) {
                throw new Exception('文件名不能为空');
            }
            
            $file_path = __DIR__ . "/../data/{$filename}";
            if (file_put_contents($file_path, $content) !== false) {
                echo json_encode(['success' => true, 'message' => '保存成功']);
            } else {
                throw new Exception('保存失败');
            }
            break;
            
        case 'get_version_log':
            // Get version log (manage.php lines 598-634)
            $checkUpdateEnable = !isset($Config['check_update']) || $Config['check_update'] == 1;
            $checkUpdate = isset($_GET['do_check_update']) && $_GET['do_check_update'] === 'true';
            if (!$checkUpdateEnable && $checkUpdate) {
                echo json_encode(['success' => true, 'is_updated' => false]);
                return;
            }

            $localFile = __DIR__ . '/../data/CHANGELOG.md';
            $url = 'https://gitee.com/taksssss/iptv-tool/raw/main/CHANGELOG.md';
            $isUpdated = false;
            $updateMessage = '';
            if ($checkUpdate) {
                $remoteContent = @file_get_contents($url);
                if ($remoteContent === false) {
                    echo json_encode(['success' => false, 'message' => '无法获取远程版本日志']);
                    return;
                }
                $localContent = file_exists($localFile) ? file_get_contents($localFile) : '';
                if (strtok($localContent, "\n") !== strtok($remoteContent, "\n")) {
                    file_put_contents($localFile, $remoteContent);
                    $isUpdated = !empty($localContent) ? true : false;
                    $updateMessage = '<h3 style="color: red;">🔔 检测到新版本，请自行更新。（该提醒仅显示一次）</h3>';
                }
            }

            $markdownContent = file_exists($localFile) ? file_get_contents($localFile) : false;
            if ($markdownContent === false) {
                echo json_encode(['success' => false, 'message' => '无法读取版本日志']);
                return;
            }

            require_once __DIR__ . '/../assets/Parsedown.php';
            $htmlContent = (new Parsedown())->text($markdownContent);
            echo json_encode(['success' => true, 'content' => $updateMessage . $htmlContent, 'is_updated' => $isUpdated]);
            break;
            
        case 'get_readme_content':
            // Get help documentation (manage.php lines 636-642)
            $readmeFile = __DIR__ . '/../assets/html/readme.md';
            $readmeContent = file_exists($readmeFile) ? file_get_contents($readmeFile) : '';
            require_once __DIR__ . '/../assets/Parsedown.php';
            $htmlContent = (new Parsedown())->text($readmeContent);
            echo json_encode(['success' => true, 'content' => $htmlContent]);
            break;
            
        case 'test_redis':
            // Test Redis connection (manage.php lines 793-811)
            $redisConfig = $Config['redis'] ?? [];
            try {
                $redis = new Redis();
                $redis->connect($redisConfig['host'] ?: '127.0.0.1', $redisConfig['port'] ? (int)$redisConfig['port'] : 6379);
                if (!empty($redisConfig['password'])) {
                    $redis->auth($redisConfig['password']);
                }
                if ($redis->ping()) {
                    $Config['cached_type'] = 'redis';
                    file_put_contents($configPath, json_encode($Config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false]);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false]);
            }
            break;
            
        case 'download_access_log':
            // Download access log as text file
            $stmt = $db->query("SELECT * FROM access_log ORDER BY id ASC");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $content = "";
            foreach ($rows as $row) {
                $content .= "[{$row['access_time']}] [{$row['client_ip']}] ";
                if ($row['access_denied']) {
                    $content .= "{$row['deny_message']} ";
                }
                $content .= "[{$row['method']}] {$row['url']} | UA: {$row['user_agent']}\n";
            }
            
            header('Content-Type: text/plain');
            header('Content-Disposition: attachment; filename="access_log.txt"');
            echo $content;
            exit;
            break;
            
        case 'query_ip_location':
            // Query IP location using Baidu API
            $ip = $_GET['ip'] ?? '';
            if (empty($ip)) {
                throw new Exception('IP地址不能为空');
            }
            
            $url = "https://opendata.baidu.com/api.php?query={$ip}&co=&resource_id=6006&oe=utf8";
            $response = @file_get_contents($url);
            if ($response) {
                $data = json_decode($response, true);
                if (isset($data['data'][0]['location'])) {
                    echo json_encode(['success' => true, 'location' => $data['data'][0]['location']]);
                } else {
                    echo json_encode(['success' => false, 'message' => '无法查询IP位置']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => '查询失败']);
            }
            break;
            
        default:
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>