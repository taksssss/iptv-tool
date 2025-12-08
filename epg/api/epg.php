<?php
/**
 * EPG API
 * 处理 EPG 数据相关请求
 */

require_once __DIR__ . '/../public.php';
initialDB();

// CORS headers
header('Access-Control-Allow-Origin: http://localhost:3000');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');
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
            // 获取频道列表（完整实现，匹配 manage.php lines 228-258）
            $channels = $db->query("SELECT DISTINCT channel FROM epg_data ORDER BY channel ASC")->fetchAll(PDO::FETCH_COLUMN);
            
            // 将频道忽略字符插入到频道列表的开头
            $channel_ignore_chars = [
                ['original' => '【频道忽略字符】', 'mapped' => $Config['channel_ignore_chars'] ?? "&nbsp, -"]
            ];
            
            $channelMappings = $Config['channel_mappings'] ?? [];
            $mappedChannels = $channel_ignore_chars;
            
            foreach ($channelMappings as $mapped => $original) {
                if (($index = array_search(strtoupper($mapped), $channels)) !== false) {
                    $mappedChannels[] = [
                        'original' => $mapped,
                        'mapped' => $original
                    ];
                    unset($channels[$index]);
                }
            }
            
            foreach ($channels as $channel) {
                $mappedChannels[] = [
                    'original' => $channel,
                    'mapped' => ''
                ];
            }
            
            echo json_encode([
                'channels' => $mappedChannels,
                'count' => count($mappedChannels)
            ]);
            break;
            
        case 'get_epg':
        case 'get_epg_by_channel':
            // 获取指定频道的 EPG 数据（完整实现，匹配 manage.php lines 260-278）
            $channel = $_GET['channel'] ?? '';
            $date = urldecode($_GET['date'] ?? date('Y-m-d'));
            
            if (empty($channel)) {
                echo json_encode([
                    'channel' => '',
                    'source' => '',
                    'date' => $date,
                    'epg' => '请指定频道'
                ]);
                exit;
            }
            
            $stmt = $db->prepare("SELECT epg_diyp FROM epg_data WHERE channel = :channel AND date = :date");
            $stmt->execute([':channel' => $channel, ':date' => $date]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                $epgData = json_decode($result['epg_diyp'], true);
                $epgSource = $epgData['source'] ?? '';
                $epgOutput = "";
                
                foreach ($epgData['epg_data'] as $epgItem) {
                    $epgOutput .= "{$epgItem['start']} {$epgItem['title']}\n";
                }
                
                echo json_encode([
                    'channel' => $channel,
                    'source' => $epgSource,
                    'date' => $date,
                    'epg' => trim($epgOutput)
                ]);
            } else {
                echo json_encode([
                    'channel' => $channel,
                    'source' => '',
                    'date' => $date,
                    'epg' => '无节目信息'
                ]);
            }
            break;
            
        case 'get_channel_bind_epg':
            // 获取频道绑定 EPG 源配置（完整实现，匹配 manage.php lines 310-330）
            $channels = $db->query("SELECT DISTINCT channel FROM epg_data ORDER BY channel ASC")->fetchAll(PDO::FETCH_COLUMN);
            $channelBindEpg = $Config['channel_bind_epg'] ?? [];
            $xmlUrls = $Config['xml_urls'] ?? [];
            
            $dbResponse = array_map(function($epgSrc) use ($channelBindEpg) {
                $cleanEpgSrc = trim(explode('#', strpos($epgSrc, '=>') !== false ? explode('=>', $epgSrc)[1] : ltrim($epgSrc, '# '))[0]);
                $isInactive = strpos(trim($epgSrc), '#') === 0;
                return [
                    'epg_src' => ($isInactive ? '【已停用】' : '') . $cleanEpgSrc,
                    'channels' => $channelBindEpg[$cleanEpgSrc] ?? ''
                ];
            }, array_filter($xmlUrls, function($epgSrc) {
                // 去除空行和包含 tvmao、cntv 的行
                return !empty(ltrim($epgSrc, '# ')) && strpos($epgSrc, 'tvmao') === false && strpos($epgSrc, 'cntv') === false;
            }));
            
            // 将已停用的排到后面
            $dbResponse = array_merge(
                array_filter($dbResponse, function($item) { return strpos($item['epg_src'], '【已停用】') === false; }),
                array_filter($dbResponse, function($item) { return strpos($item['epg_src'], '【已停用】') !== false; })
            );
            
            echo json_encode($dbResponse);
            break;
            
        case 'save_channel_bind_epg':
            // 保存频道绑定 EPG 源配置
            $data = json_decode(file_get_contents('php://input'), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $data = $_POST['data'] ?? [];
                if (is_string($data)) {
                    $data = json_decode($data, true);
                }
            }
            
            $bindData = [];
            foreach ($data as $item) {
                $epgSrc = preg_replace('/^【已停用】/', '', $item['epg_src'] ?? '');
                if (!empty($epgSrc) && !empty($item['channels'])) {
                    $bindData[$epgSrc] = str_replace("，", ",", trim($item['channels']));
                }
            }
            
            $Config['channel_bind_epg'] = $bindData;
            file_put_contents($configPath, json_encode($Config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            
            echo json_encode(['success' => true, 'message' => '保存成功']);
            break;
            
        case 'get_channel_match':
            // 获取频道匹配数据
            $channels = $db->query("SELECT channel FROM gen_list")->fetchAll(PDO::FETCH_COLUMN);
            
            if (empty($channels)) {
                echo json_encode([
                    'ori_channels' => [],
                    'clean_channels' => [],
                    'match' => [],
                    'type' => []
                ]);
                exit;
            }
            
            // 清理频道名
            $lines = implode("\n", array_map('cleanChannelName', $channels));
            $cleanChannels = explode("\n", ($Config['cht_to_chs'] ?? false) ? t2s($lines) : $lines);
            
            // 获取 EPG 数据中的频道
            $epgData = $db->query("SELECT channel FROM epg_data")->fetchAll(PDO::FETCH_COLUMN);
            $channelMap = array_combine($cleanChannels, $channels);
            
            $matches = [];
            foreach ($cleanChannels as $cleanChannel) {
                $originalChannel = $channelMap[$cleanChannel];
                $matchResult = null;
                $matchType = '未匹配';
                
                // 精确匹配
                if (in_array($cleanChannel, $epgData)) {
                    $matchResult = $cleanChannel;
                    $matchType = '精确匹配';
                    if ($cleanChannel !== $originalChannel) {
                        $matchType = '繁简/别名/忽略';
                    }
                } else {
                    // 模糊匹配
                    foreach ($epgData as $epgChannel) {
                        // 正向模糊（EPG频道包含源频道）
                        if (stripos($epgChannel, $cleanChannel) !== false) {
                            if (!isset($matchResult) || mb_strlen($epgChannel) < mb_strlen($matchResult)) {
                                $matchResult = $epgChannel;
                                $matchType = '正向模糊';
                            }
                        }
                        // 反向模糊（源频道包含EPG频道）
                        elseif (stripos($cleanChannel, $epgChannel) !== false) {
                            if (!isset($matchResult) || mb_strlen($epgChannel) > mb_strlen($matchResult)) {
                                $matchResult = $epgChannel;
                                $matchType = '反向模糊';
                            }
                        }
                    }
                }
                
                $matches[$cleanChannel] = [
                    'ori_channel' => $originalChannel,
                    'clean_channel' => $cleanChannel,
                    'match' => $matchResult,
                    'type' => $matchType
                ];
            }
            
            echo json_encode($matches);
            break;
            
        case 'get_gen_list':
            // 获取生成列表
            $genList = $db->query("SELECT channel FROM gen_list")->fetchAll(PDO::FETCH_COLUMN);
            echo json_encode($genList);
            break;
            
        default:
            throw new Exception('未知的操作');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}

