<?php
/**
 * @file check.php
 * @brief 频道测速与管理脚本
 *
 * 该脚本用于对 IPTV 频道源进行批量检测，利用 ffprobe 测试流媒体源的分辨率和响应速度，
 * 并将结果写入数据库，用于频道可用性与质量的维护。
 *
 * 功能说明：
 * - 前台模式：实时测速并输出结果（需保持浏览器开启）
 * - 后台模式：支持 backgroundMode 参数，后台执行测速任务，不依赖浏览器
 * - 支持测速过滤规则、最低分辨率限制、测速结果缓存与复用
 * - 支持 cleanMode 参数，清理测速数据并重置频道状态
 * - 支持按延迟排序、同频道接口数量限制，并自动更新数据库及生成 M3U/TXT 文件
 *
 * 参数说明：
 * - backgroundMode=1      后台运行测速（关闭浏览器也继续执行）
 * - cleanMode=1           清空测速数据并重置频道状态
 *
 * 作者: Tak
 * GitHub: https://github.com/taksssss/iptv-tool
 */

// 检测是否有运行权限
session_start();
if (php_sapi_name() !== 'cli' && (empty($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true)) {
    http_response_code(403);
    exit('无访问权限，请先登录。');
}
session_write_close();

// 检测 ffmpeg 是否安装
if (!shell_exec('which ffprobe')) {
    echo '<p>未检测到 ffmpeg 环境，请重新部署。<p>';
    exit;
}

// 如果启用 backgroundMode，则在后台执行自身并退出
if (!empty($_GET['backgroundMode'])) {
    exec("pgrep -f ffprobe", $output);
    if (count($output) > 0) exit('已有任务在运行。');
    exec("php check.php > /dev/null 2>&1 &");
    exit('已切换至后台运行，关闭浏览器不影响执行。<br>请自行刷新页面查看结果。');
}

// 禁用 PHP 输出缓冲
ob_implicit_flush(true);
@ob_end_flush();

// 设置 header，防止浏览器缓存输出
header('X-Accel-Buffering: no');

// 引入公共脚本
require_once 'public.php';

// 读取 Config 文件
$checkSpeedFilter = $Config['check_speed_filter'] ?? 1;
$checkSpeedFilterRulesRaw = isset($Config['check_speed_filter_rules'])
    ? trim((string)$Config['check_speed_filter_rules'])
    : 'regex:/^https?:\/\/\[[a-f0-9:]+\]/i';
$checkSpeedFilterRules = array_values(array_filter(
    array_map('trim', preg_split('/\r\n|\r|\n/', $checkSpeedFilterRulesRaw)),
    function ($rule) {
        return !preg_match('/^\s*(#|$)/', $rule);
    }
));

function hitCheckSpeedFilterRule($streamUrl, $rules) {
    foreach ($rules as $rule) {
        if (stripos($rule, 'regex:') === 0) {
            $pattern = substr($rule, 6);
            if (@preg_match($pattern, '') === false) {
                continue;
            }
            if (preg_match($pattern, $streamUrl)) {
                return $rule;
            }
            continue;
        }

        if (stripos($streamUrl, $rule) !== false) {
            return $rule;
        }
    }
    return false;
}

$minWidth = $Config['min_resolution_width'] ?? 0;
$minHeight = $Config['min_resolution_height'] ?? 0;
$urlsLimit = $Config['urls_limit'] ?? 0;
$sortByDelay = $Config['sort_by_delay'] ?? 0;
$liveSourceConfig = $Config['live_source_config'] ?? 'default';
$token = $Config['token'];

// cleanMode 参数为 1 时，清除测速数据
if (!empty($_GET['cleanMode'])) {
    // 查找 channels_info 表中 speed = 'N/A' 的 streamUrl 列表
    $stmt = $db->prepare("SELECT streamUrl FROM channels_info WHERE speed = 'N/A'");
    $stmt->execute();
    $urlsToClear = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($urlsToClear)) {
        // 将 channels 表中对应 streamUrl 的 disable 和 modified 字段清零
        $placeholders = rtrim(str_repeat('?,', count($urlsToClear)), ',');
        $sql = "UPDATE channels SET disable = 0, modified = 0 WHERE streamUrl IN ($placeholders)";
        $stmt = $db->prepare($sql);
        $stmt->execute($urlsToClear);
    }

    // 清空 channels_info 表中所有测速数据（保留表结构）
    $db->exec("DELETE FROM channels_info");

    echo "测速数据已清除。";
    exit;
}

echo '<strong><span style="color: red;">前台测速过程中请勿关闭浏览器</span></strong><br><br>';

// 从数据库读取 channels 数据
$channels = [];
$channelHeaders = [];

$stmt = $db->prepare("SELECT * FROM channels WHERE config = ?");
$stmt->execute([$liveSourceConfig]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if (!$channelHeaders) $channelHeaders = array_keys($row);
    $channels[] = array_values($row);
}

// 定位字段索引并校验
foreach (['groupPrefix', 'groupTitle', 'streamUrl', 'channelName', 'disable', 'modified'] as $field) {
    ${$field . 'Index'} = array_search($field, $channelHeaders);
    if (${$field . 'Index'} === false) {
        die("channels 表缺少必要字段: {$field}");
    }
}

// 初始化测速映射表
$channelsInfoMap = [];
$total = count($channels);
$testedUrls = [];

foreach ($channels as $i => $channel) {
    $oriUrl = $channel[$streamUrlIndex];

    // 获取 http-referrer、http-user-agent
    $requestHeaders = [];
    if (preg_match('/^#EXTVLCOPT:http-referrer=(.+)$/mi', $oriUrl, $m)) {
        $requestHeaders['Referer'] = trim($m[1]);
    }
    if (preg_match('/^#EXTVLCOPT:http-user-agent=(.+)$/mi', $oriUrl, $m)) {
        $requestHeaders['User-Agent'] = trim($m[1]);
    }

    // 取最后一行为 URL
    $raw = preg_split('/\r\n|\r|\n/', trim($oriUrl));
    $raw = trim(end($raw));

    // 处理 #NOPROXY、#PROXY
    $raw = str_replace('#NOPROXY', '', $raw);
    if (preg_match('/#PROXY=([^#]+)/', $raw, $m)) {
        $raw = decryptUrl(urldecode($m[1]), $token);
    }

    // 处理带有 $ 的 URL
    $streamUrl = strtok($raw, '$');

    $channelName = $channelNameIndex !== false ? $channel[$channelNameIndex] : '未知频道';

    // 跳过空的 streamUrl
    if (empty($streamUrl)) continue;

    echo "(" . ($i + 1) . "/{$total}): {$channelName} - {$streamUrl}<br>";

    // 按规则过滤测速源
    if ((int)$checkSpeedFilter === 1) {
        $hitRule = hitCheckSpeedFilterRule($streamUrl, $checkSpeedFilterRules);
        if ($hitRule !== false) {
            echo '<strong><span style="color: red;">已过滤测速源（规则：' . htmlspecialchars($hitRule, ENT_QUOTES, 'UTF-8') . '）</span></strong><br><br>';
            continue;
        }
    }

    // 初始化变量
    $resolution = $speed = 'N/A';
    $disable = $modified = 1;

    if (isset($testedUrls[$streamUrl])) {
        // 如果已经测速过，直接复用结果
        [$resolution, $speed, $disable, $modified] = $testedUrls[$streamUrl];
        $channelsInfoMap[$oriUrl] = is_numeric($speed) ? (int)$speed : PHP_INT_MAX;
        echo "<em>复用测速结果：分辨率: {$resolution}, 访问速度: {$speed} ms</em><br><br>";
    } else {
        // 使用 ffprobe 测速
        $startTime = microtime(true);

        // 拼接 headers
        $requestHeaderStr = '';
        foreach ($requestHeaders as $k => $v) {
            $requestHeaderStr .= "{$k}: {$v}\r\n";
        }
        $requestHeaderStr = $requestHeaderStr ? '-headers ' . escapeshellarg($requestHeaderStr) . ' ' : '';

        $cmd = "ffprobe -rw_timeout 2000000 {$requestHeaderStr}"
             . "-v error -select_streams v:0 "
             . "-show_entries stream=width,height "
             . "-of csv=p=0 "
             . escapeshellarg($streamUrl);
        exec($cmd, $output, $returnVar);
        $duration = round((microtime(true) - $startTime) * 1000);

        if ($returnVar !== 0) {
            echo '<strong><span style="color: red;">无法获取流信息，已停用</span></strong><br><br>';
        } else {
            $speed = $duration;
            $disable = 0;
            $modified = 0;

            // 提取分辨率
            foreach ($output as $line) {
                if (strpos($line, ',') !== false) {
                    list($width, $height) = array_pad(explode(',', $line), 2, '未知');
                    $resolution = "{$width}x{$height}";
                    if ($width < $minWidth || $height < $minHeight) {
                        $disable = 1;
                        $modified = 1;
                        echo "<strong><span style='color: red;'>分辨率不满足 {$minWidth}x{$minHeight}，已停用</span></strong><br>";
                        break;
                    }
                }
            }

            echo "分辨率: {$resolution}, 访问速度: {$speed} ms<br><br>";
        }

        // 缓存测速结果
        $testedUrls[$streamUrl] = [$resolution, $speed, $disable, $modified];
    }

    // 写入数据库
    $sql = "
        INSERT INTO channels_info (streamUrl, resolution, speed)
        VALUES (?, ?, ?)
    ";
    $sql .= ($Config['db_type'] === 'sqlite')
        ? " ON CONFLICT(streamUrl) DO UPDATE SET
                resolution = excluded.resolution,
                speed = excluded.speed"
        : " ON DUPLICATE KEY UPDATE
                resolution = VALUES(resolution),
                speed = VALUES(speed)";
    $stmt = $db->prepare($sql);
    $stmt->execute([$oriUrl, $resolution, $speed]);

    // 更新内存映射和频道状态
    $channelsInfoMap[$oriUrl] = is_numeric($speed) ? (int)$speed : PHP_INT_MAX;
    $channel[$disableIndex] = $disable;
    $channel[$modifiedIndex] = $modified;
    $channels[$i] = $channel;
}

echo "检测完成，已更新 channels_info 表。<br>";

// 按响应速度排序
if ($sortByDelay == 1) {
    // 按 channelName + groupPrefix + groupTitle 分组
    $groupedChannels = [];
    foreach ($channels as $channel) {
        $key = md5($channel[$channelNameIndex] . $channel[$groupPrefixIndex] . $channel[$groupTitleIndex]);
        $groupedChannels[$key][] = $channel;
    }

    // 对每个分组进行排序
    foreach ($groupedChannels as &$group) {
        usort($group, function ($a, $b) use ($channelsInfoMap, $streamUrlIndex) {
            return ($channelsInfoMap[$a[$streamUrlIndex]] ?? PHP_INT_MAX-1) <=> ($channelsInfoMap[$b[$streamUrlIndex]] ?? PHP_INT_MAX-1);
        });
    }
    unset($group); // 避免引用问题

    // 合并回 channels
    $channels = array_merge(...array_values($groupedChannels));
}

// 单个频道接口数量限制
if ($urlsLimit > 0) {
    $counts = [];
    foreach ($channels as &$channel) {
        $name = $channel[$channelNameIndex] ?? '';
        $counts[$name] = ($counts[$name] ?? 0) + 1;
        if ($counts[$name] > $urlsLimit) {
            $channel[$disableIndex] = '1';
        }
    }
    unset($channel); // 清除引用
}

// 重新写回 channels
$db->beginTransaction();
$stmt = $db->prepare("DELETE FROM channels WHERE config = ?");
$stmt->execute([$liveSourceConfig]);
$channelsData = [];
$placeholders = implode(', ', array_fill(0, count($channelHeaders), '?'));
$sql = "INSERT INTO channels (" . implode(', ', $channelHeaders) . ") VALUES ($placeholders)";
$stmt = $db->prepare($sql);
foreach ($channels as $row) {
    $channelsData[] = array_combine($channelHeaders, $row);
    $stmt->execute($row);
}
$db->commit();

// 重新生成 M3U 和 TXT 文件
generateLiveFiles($channelsData, 'tv', $liveSourceConfig, $saveOnly = true);

?>