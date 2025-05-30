<?php
/**
 * @file index.php
 * @brief 主页处理脚本
 *
 * 该脚本处理来自客户端的请求，根据查询参数获取指定日期和频道的节目信息，
 * 并从 SQLite 数据库中提取或返回默认数据。
 *
 * 作者: Tak
 * GitHub: https://github.com/taksssss/EPG-Server
 */

// 引入公共脚本
require_once 'public.php';

// 获取当前完整的 URL
$requestUrl = $_SERVER['REQUEST_URI'];

// 修正 URL 格式：如果存在多个 `?`，将后续的 `?` 替换为 `&`
if (substr_count($requestUrl, '?') > 1) {
    $requestUrl = preg_replace('/&/', '?', preg_replace('/\?/', '&', $requestUrl), 1);
}

// 解析 URL 中的查询参数，特殊处理 5+ 频道
parse_str(str_replace('5+', '5%2B', parse_url($requestUrl, PHP_URL_QUERY)), $query_params);

// 获取 URL 中的 token 参数并验证
$tokenRange = $Config['token_range'] ?? 1;
$live = $query_params['live'] ?? '';
if ($tokenRange !== 0) {
    $allowedTokens = array_map('trim', explode(',', $Config['token'] ?? ''));
    $token = $query_params['token'] ?? '';
    if (!in_array($token, $allowedTokens) && (($tokenRange !== 2 && $live) || 
        ($tokenRange !== 1 && !$live))) {
        http_response_code(403);
        echo '访问被拒绝：无效的 Token。';
        exit;
    }
}

// 获取请求的 User-Agent 并验证
$userAgentRange = $Config['user_agent_range'] ?? 0;
if ($userAgentRange !== 0) {
    $allowedUserAgents = array_map('trim', explode(',', $Config['user_agent'] ?? ''));
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if (!in_array($userAgent, $allowedUserAgents) && (($userAgentRange !== 2 && $live) || 
       ($userAgentRange !== 1 && !$live))) {
        http_response_code(403);
        echo '访问被拒绝：无效的 User-Agent。';
        exit;
    }
}

// 记录访问日志
if ($Config['debug_mode'] ?? 0) {
    $logFile = __DIR__ . '/data/access.log';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $time = date('Y-m-d H:i:s');
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $url = rawurldecode($_SERVER['REQUEST_URI'] ?? 'unknown');
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $logEntry = "[$time] [$ip] [$method] $url | UA: $userAgent\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

// 禁止输出错误提示
error_reporting(0);

// 初始化响应头信息
$init = [
    'status' => 200,
    'headers' => [
        'content-type' => 'application/json'
    ]
];

// 生成响应
function makeRes($body, $status = 200, $headers = []) {
    $headers['Access-Control-Allow-Origin'] = '*';
    http_response_code($status);
    foreach ($headers as $key => $value) {
        header("$key: $value");
    }
    echo $body;
}

// 获取当前日期
function getNowDate() {
    return date('Y-m-d');
}

// 格式化时间
function getFormatTime($time) {
    if (strlen($time) < 8) {
        return ['date' => getNowDate(), 'time' => ''];
    }

    $date = substr($time, 0, 4) . '-' . substr($time, 4, 2) . '-' . substr($time, 6, 2);
    $time = strlen($time) >= 12 ? substr($time, 8, 2) . ':' . substr($time, 10, 2) : '';

    return ['date' => $date, 'time' => $time];
}

// 从数据库读取 diyp、lovetv 数据，兼容未安装 memcached 的情况
function readEPGData($date, $oriChannelName, $cleanChannelName, $db, $type) {
    // 默认缓存 24 小时，更新数据时清空
    $cache_time = 24 * 3600;

    // 检查 Memcached 状态
    $memcached_enabled = class_exists('Memcached') && ($memcached = new Memcached())->addServer('localhost', 11211);
    $cache_key = base64_encode("{$date}_{$cleanChannelName}_{$type}");

    if ($memcached_enabled) {
        // 从缓存中读取数据
        $cached_data = $memcached->get($cache_key);
        if ($cached_data) {
            return $cached_data;
        }
    }

    // 获取数据库类型（mysql 或 sqlite）
    $concat = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
        ? "CONCAT('%', channel, '%')"
        : "'%' || channel || '%'";

    // 优先精准匹配，其次正向模糊匹配，最后反向模糊匹配
    $stmt = $db->prepare("
        SELECT epg_diyp
        FROM epg_data
        WHERE (
            (channel = :channel
            OR channel LIKE :like_channel
            OR :channel LIKE $concat)
            AND date = :date
        )
        ORDER BY
            CASE
                WHEN channel = :channel THEN 1
                WHEN channel LIKE :like_channel THEN 2
                ELSE 3
            END,
            CASE
                WHEN channel = :channel THEN NULL
                WHEN channel LIKE :like_channel THEN LENGTH(channel)
                ELSE -LENGTH(channel)
            END
        LIMIT 1
    ");
    $stmt->execute([
        ':date' => $date,
        ':channel' => $cleanChannelName,
        ':like_channel' => $cleanChannelName . '%'
    ]);
    $row = $stmt->fetchColumn();

    if (!$row) {
        return false;
    }

    // 在解码和添加 icon 后再编码为 JSON
    $rowArray = json_decode($row, true);
    unset($rowArray['source']); // 移除 source 字段
    $iconUrl = iconUrlMatch($cleanChannelName) ?? iconUrlMatch($oriChannelName);
    $rowArray = array_merge(
        array_slice($rowArray, 0, array_search('url', array_keys($rowArray)) + 1),
        ['icon' => $iconUrl],
        array_slice($rowArray, array_search('url', array_keys($rowArray)) + 1)
    );
    $row = json_encode($rowArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    if ($type === 'diyp') {
        // 如果 Memcached 可用，将结果存储到缓存中
        if ($memcached_enabled) {
            $memcached->set($cache_key, $row, $cache_time);
        }
        return $row;
    }

    if ($type === 'lovetv') {
        $diyp_data = $rowArray;
        $date = $diyp_data['date'];
        $program = array_map(function($epg) use ($date) {
            $start_time = strtotime($date . ' ' . $epg['start']);
            $end_time = strtotime($date . ' ' . $epg['end']);
            $duration = $end_time - $start_time;
            return [
                'st' => $start_time,
                'et' => $end_time,
                'eventType' => '',
                'eventId' => '',
                't' => $epg['title'],
                'showTime' => gmdate('H:i', $duration),
                'duration' => $duration
            ];
        }, $diyp_data['epg_data']);

        // 查找当前节目
        $current_programme = $date === date('Y-m-d') ? findCurrentProgramme($program) : null;

        // 生成 lovetv 数据
        $lovetv_data = [
            $oriChannelName => [
                'isLive' => $current_programme ? $current_programme['t'] : '',
                'liveSt' => $current_programme ? $current_programme['st'] : 0,
                'channelName' => $diyp_data['channel_name'],
                'lvUrl' => $diyp_data['url'],
                'icon' => $diyp_data['icon'],
                'program' => $program
            ]
        ];

        $response = json_encode($lovetv_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // 如果 Memcached 可用，将结果存储到缓存中
        if ($memcached_enabled) {
            $memcached->set($cache_key, $response, $cache_time);
        }

        return $response;
    }

    return false;
}

// 查找当前节目
function findCurrentProgramme($programmes) {
    $now = time();
    foreach ($programmes as $programme) {
        if ($programme['st'] <= $now && $programme['et'] >= $now) {
            return $programme;
        }
    }
    return null;
}

// 处理直播源请求
function liveFetchHandler($query_params) {
    global $Config, $liveDir, $serverUrl, $liveFileDir;

    header('Content-Type: text/plain');

    // 计算文件路径
    $isValidFile = false;
    if (!empty($query_params['url'])) {
        $url = $query_params['url'];
        $filePath = sprintf('%s/%s.%s', $liveFileDir, md5(urlencode($url)), $query_params['live']);
        if (($query_params['latest'] === '1' && doParseSourceInfo($url)) === true || 
            file_exists($filePath) || doParseSourceInfo($url) === true) { // 判断是否需要获取最新文件
            $isValidFile = true;
        }
    } else {
        $filePath = $liveDir . ($query_params['live'] === 'txt' ? 'tv.txt' : ($query_params['live'] === 'm3u' ? 'tv.m3u' : ''));
        $isValidFile = file_exists($filePath);
    }

    // 如果文件存在或成功解析了源数据
    if ($isValidFile) {
        $content = file_get_contents($filePath);
    } else {
        echo "文件不存在或无效的 live 类型";
        exit;
    }

    // 处理 TVG URL 替换
    $tvgUrl = $serverUrl . ($query_params['live'] === 'm3u' ? '/t.xml.gz' : '/');
    if ($query_params['live'] === 'm3u') {
        $content = preg_replace('/(#EXTM3U x-tvg-url=")(.*?)(")/', '$1' . $tvgUrl . '$3', $content, 1);
        $content = str_replace("tvg-logo=\"/data/icon/", "tvg-logo=\"$serverUrl/data/icon/", $content);
    }

    echo $content;
    exit;
}

// 处理请求
function fetchHandler($query_params) {
    global $init, $db, $Config;

    // 处理直播源请求
    if (isset($query_params['live'])) {
        liveFetchHandler($query_params);
    }

    // 获取并清理频道名称，繁体转换成简体
    $oriChannelName = $query_params['ch'] ?? $query_params['channel'] ?? '';
    $cleanChannelName = cleanChannelName($oriChannelName, $t2s = true);

    $date = isset($query_params['date']) ? getFormatTime(preg_replace('/\D+/', '', $query_params['date']))['date'] : getNowDate();

    // 频道参数为空时，直接返回 t.xml 文件数据
    if (empty($cleanChannelName)) {
        if ($Config['gen_xml'] === 1) {
            header('Content-Type: application/xml');
            header('Content-Disposition: attachment; filename="t.xml"');
            readfile('./t.xml');
        } else {
            // 输出消息并设置404状态码
            echo "404 Not Found. <br>未生成 xmltv 文件";
            http_response_code(404);
        }
        exit;
    }

    // 返回 diyp、lovetv 数据
    if (isset($query_params['ch']) || isset($query_params['channel'])) {
        $type = isset($query_params['ch']) ? 'diyp' : 'lovetv';
        $response = readEPGData($date, $oriChannelName, $cleanChannelName, $db, $type);
        if ($response) {
            makeRes($response, $init['status'], $init['headers']);
            exit;
        }

        // 无法获取到数据时返回默认数据
        $ret_default = $Config['ret_default'] ?? true;
        $iconUrl = iconUrlMatch($cleanChannelName) ?? iconUrlMatch($oriChannelName);
        if ($type === 'diyp') {
            // 返回默认 diyp 数据
            $default_diyp_program_info = [
                'channel_name' => $cleanChannelName,
                'date' => $date,
                'url' => "https://github.com/taksssss/EPG-Server",
                'icon' => $iconUrl,
                'epg_data' => !$ret_default ? '' : array_map(function($hour) {
                    return [
                        'start' => sprintf('%02d:00', $hour),
                        'end' => sprintf('%02d:00', ($hour + 1) % 24),
                        'title' => '精彩节目',
                        'desc' => ''
                    ];
                }, range(0, 23, 1))
            ];
            $response = json_encode($default_diyp_program_info, JSON_UNESCAPED_UNICODE);
        } else {
            // 返回默认 lovetv 数据
            $default_lovetv_program_info = [
                $cleanChannelName => [
                    'isLive' => '',
                    'liveSt' => 0,
                    'channelName' => $cleanChannelName,
                    'lvUrl' => 'https://github.com/taksssss/EPG-Server',
                    'icon' => $iconUrl,
                    'program' => !$ret_default ? '' : array_map(function($hour) {
                        return [
                            'st' => strtotime(sprintf('%02d:00', $hour)),
                            'et' => strtotime(sprintf('%02d:00', ($hour + 1) % 24)),
                            't' => '精彩节目',
                            'd' => ''
                        ];
                    }, range(0, 23, 1))
                ]
            ];
            $response = json_encode($default_lovetv_program_info, JSON_UNESCAPED_UNICODE);
        }
        makeRes($response, $init['status'], $init['headers']);
    }
}

// 执行请求处理
fetchHandler($query_params);

?>
