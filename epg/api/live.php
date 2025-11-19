<?php
/**
 * Live Source Management API
 * Complete implementation matching original manage.php lines 384-596
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

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_live_data':
            // Read live source file content (manage.php lines 384-486)
            if (isset($_GET['live_source_config'])) {
                $Config['live_source_config'] = $_GET['live_source_config'];
                file_put_contents($configPath, json_encode($Config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }
            
            $sourceJsonPath = $liveDir . 'source.json';
            $templateJsonPath = $liveDir . 'template.json';
            
            if (!file_exists($sourceJsonPath)) {
                $sourceTxtPath = $liveDir . 'source.txt';
                $default = file_exists($sourceTxtPath)
                    ? array_values(array_filter(array_map('trim', file($sourceTxtPath))))
                    : [];
            
                file_put_contents($sourceJsonPath, json_encode(['default' => $default], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            
                if (file_exists($sourceTxtPath)) {
                    @unlink($sourceTxtPath);
                }
            }
            
            if (!file_exists($templateJsonPath) && file_exists($templateTxtPath = $liveDir . 'template.txt')) {
                file_put_contents($templateJsonPath, json_encode([
                    'default' => array_values(array_filter(array_map('trim', file($templateTxtPath))))
                ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                @unlink($templateTxtPath);
            }
            
            $sourceJson = json_decode(@file_get_contents($sourceJsonPath), true) ?: [];
            $templateJson = json_decode(@file_get_contents($templateJsonPath), true) ?: [];
            $liveSourceConfig = $Config['live_source_config'] ?? 'default';
            $liveSourceConfig = isset($sourceJson[$liveSourceConfig]) ? $liveSourceConfig : 'default';
            $sourceContent = implode("\n", $sourceJson[$liveSourceConfig] ?? []);
            $templateContent = implode("\n", $templateJson[$liveSourceConfig] ?? []);

            // Generate config dropdown options
            $configOptions = [];
            foreach ($sourceJson as $key => $_) {
                $configOptions[] = [
                    'value' => $key,
                    'label' => ($key === 'default') ? '默认' : $key,
                    'selected' => ($key == $liveSourceConfig)
                ];
            }

            // Get pagination parameters
            $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
            $perPage = isset($_GET['per_page']) ? max(1, min(1000, intval($_GET['per_page']))) : 100;
            $offset = ($page - 1) * $perPage;
            
            // Get search keyword
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

            // Get total count
            $countSql = "SELECT COUNT(*) FROM channels c WHERE c.config = ?" . $searchCondition;
            $countStmt = $db->prepare($countSql);
            $countStmt->execute($searchParams);
            $totalCount = $countStmt->fetchColumn();

            // Read channel data (paginated), merge with speed test info
            $dataSql = "
                SELECT 
                    c.*, 
                    REPLACE(ci.resolution, 'x', '<br>x<br>') AS resolution,
                    CASE WHEN " . ($is_sqlite ? "ci.speed GLOB '[0-9]*'" : "ci.speed REGEXP '^[0-9]+$'") . " 
                        THEN " . ($is_sqlite ? "ci.speed || '<br>ms'" : "CONCAT(ci.speed, '<br>ms')") . " 
                        ELSE ci.speed 
                    END AS speed
                FROM channels c
                LEFT JOIN channels_info ci ON c.streamUrl = ci.streamUrl
                WHERE c.config = ?" . $searchCondition . "
                LIMIT ? OFFSET ?
            ";
            $stmt = $db->prepare($dataSql);
            $stmt->execute(array_merge($searchParams, [$perPage, $offset]));
            $channelsData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'source_content' => $sourceContent,
                'template_content' => $templateContent,
                'channels' => $channelsData,
                'config_options' => $configOptions,
                'total_count' => $totalCount,
                'page' => $page,
                'per_page' => $perPage,
            ]);
            break;
            
        case 'parse_source_info':
            // Parse live source (manage.php lines 488-496)
            $parseResult = doParseSourceInfo();
            if ($parseResult !== true) {
                echo json_encode(['success' => 'part', 'message' => $parseResult]);
            } else {
                echo json_encode(['success' => 'full']);
            }
            break;
            
        case 'download_source_data':
            // Download live source data (manage.php lines 498-511)
            $url = filter_var(($_GET['url']), FILTER_VALIDATE_URL);
            if ($url) {
                $data = downloadData($url, '', 5);
                if ($data !== false) {
                    echo json_encode(['success' => true, 'data' => $data]);
                } else {
                    echo json_encode(['success' => false, 'message' => '无法获取URL内容']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => '无效的URL']);
            }
            break;
            
        case 'delete_source_config':
            // Delete live source configuration (manage.php lines 527-545)
            $config = $_GET['live_source_config'];
            $db->prepare("DELETE FROM channels WHERE config = ?")->execute([$config]);
            foreach (['source', 'template'] as $file) {
                $filePath = $liveDir . "{$file}.json";
                if (file_exists($filePath)) {
                    $json = json_decode(file_get_contents($filePath), true);
                    if (isset($json[$config])) {
                        unset($json[$config]);
                        file_put_contents($filePath, json_encode($json, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                    }
                }
            }
            $id = md5(urlencode($config));
            foreach (['m3u', 'txt'] as $ext) {
                @unlink("$liveFileDir{$id}.{$ext}");
            }
            echo json_encode(['success' => true]);
            break;
            
        case 'delete_unused_live_data':
            // Clean up unused live source cache and modification records (manage.php lines 547-596)
            $sourceFilePath = $liveDir . 'source.json';
            $sourceJson = json_decode(@file_get_contents($sourceFilePath), true);
            $urls = [];
            foreach ((array)$sourceJson as $key => $list) {
                if (is_array($list)) {
                    $urls = array_merge($urls, $list);
                }
                $urls[] = $key;
            }
        
            // Process live source URLs, remove comments and clean format
            $cleanUrls = array_map(function($url) {
                return trim(explode('#', ltrim($url, '# '))[0]);
            }, $urls);
        
            // Delete unused /file cache files
            $parentRltPath = '/' . basename(dirname(__DIR__)) . '/data/live/file/';
            $deletedFileCount = 0;
            foreach (scandir($liveFileDir) as $file) {
                if ($file === '.' || $file === '..') continue;
        
                $fileRltPath = $parentRltPath . $file;
                $matched = false;
                foreach ($cleanUrls as $url) {
                    if (!$url) continue;
                    $urlMd5 = md5(urlencode($url));
                    if (stripos($fileRltPath, $url) !== false || stripos($fileRltPath, $urlMd5) !== false) {
                        $matched = true;
                        break;
                    }
                }
                if (!$matched && @unlink($liveFileDir . $file)) {
                    $deletedFileCount++;
                }
            }
            @unlink($liveDir . 'tv.m3u');
            @unlink($liveDir . 'tv.txt');
        
            // Clear all channels.modified = 1 records in database (all configs)
            $stmt = $db->prepare("UPDATE channels SET modified = 0 WHERE modified = 1");
            $stmt->execute();
        
            // Return cleanup result
            echo json_encode([
                'success' => true,
                'message' => "共清理了 $deletedFileCount 个缓存文件。<br>已清除所有修改标记。<br>正在重新解析..."
            ]);
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