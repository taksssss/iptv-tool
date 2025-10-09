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
 * - 支持 IPv6 检测、最低分辨率限制、测速结果缓存与复用
 * - 支持 cleanMode 参数，清理测速数据并重置频道状态
 * - 支持按延迟排序、同频道接口数量限制，并自动更新数据库及生成 M3U/TXT 文件
 *
 * 参数说明：
 * - backgroundMode=true   后台运行测速（关闭浏览器也继续执行）
 * - cleanMode=true        清空测速数据并重置频道状态
 *
 * 作者: Tak
 * GitHub: https://github.com/taksssss/iptv-tool
 */

// 检测是否为 AJAX 请求或 CLI 运行
if (!(isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    && php_sapi_name() !== 'cli') {
    http_response_code(403); // 返回403禁止访问
    exit('禁止直接访问');
}

// 检测 ffmpeg 是否安装
if (!shell_exec('which ffprobe')) {
    echo '<p>未检测到 ffmpeg 环境，请重新部署。<p>';
    exit;
}

// 如果启用 backgroundMode，则在后台执行自身并退出
if (isset($_GET['backgroundMode']) && $_GET['backgroundMode'] === 'true') {
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
$checkIPv6 = $Config['check_ipv6'] ?? 0;
$minWidth = $Config['min_resolution_width'] ?? 0;
$minHeight = $Config['min_resolution_height'] ?? 0;
$urlsLimit = $Config['urls_limit'] ?? 0;
$sortByDelay = $Config['sort_by_delay'] ?? 0;
$liveSourceConfig = $Config['live_source_config'] ?? 'default';

// cleanMode 参数为 true 时，清除测速数据
if (isset($_GET['cleanMode']) && $_GET['cleanMode'] === 'true') {
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
$headers = [];

$stmt = $db->prepare("SELECT * FROM channels WHERE config = ?");
$stmt->execute([$liveSourceConfig]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if (!$headers) $headers = array_keys($row);
    $channels[] = array_values($row);
}

// 定位字段索引
$streamUrlIndex = array_search('streamUrl', $headers);
$channelNameIndex = array_search('channelName', $headers);
$disableIndex = array_search('disable', $headers);
$modifiedIndex = array_search('modified', $headers);

// 确保必要字段存在
if ($streamUrlIndex === false) {
    die('channels 表缺少必要字段');
}

// 初始化测速映射表
$channelsInfoMap = [];
$total = count($channels);
$testedUrls = [];

foreach ($channels as $i => $channel) {
    $streamUrl = strtok($channel[$streamUrlIndex], '$'); // 处理带有 $ 的 URL
    $channelName = $channelNameIndex !== false ? $channel[$channelNameIndex] : '未知频道';

    // 跳过空的 streamUrl
    if (empty($streamUrl)) continue;

    echo "(" . ($i + 1) . "/{$total}): {$channelName} - {$streamUrl}<br>";

    // 跳过 IPv6 源
    if ($checkIPv6 === 0 && preg_match('#^https?://\[[a-f0-9:]+\]#i', $streamUrl)) {
        echo '<strong><span style="color: red;">跳过 IPv6 源</span></strong><br><br>';
        continue;
    }

    if (isset($testedUrls[$streamUrl])) {
        // 如果已经测速过，直接复用结果
        [$resolution, $speed, $disable, $modified] = $testedUrls[$streamUrl];
        echo "<em>复用测速结果：分辨率: {$resolution}, 访问速度: {$speed} ms</em><br><br>";
    } else {
        // 使用 ffprobe 测速
        $startTime = microtime(true);
        $cmd = "ffprobe -rw_timeout 2000000 -v error -select_streams v:0 -show_entries stream=width,height -of csv=p=0 \"{$streamUrl}\"";
        exec($cmd, $output, $returnVar);
        $duration = round((microtime(true) - $startTime) * 1000);

        if ($returnVar !== 0) {
            $resolution = $speed = 'N/A';
            $disable = 1;
            $modified = 1;
            echo '<strong><span style="color: red;">无法获取流信息，已停用</span></strong><br><br>';
        } else {
            $speed = $duration;
            $resolution = 'N/A';
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

        // 写入数据库
        $stmt = $db->prepare(
            ($Config['db_type'] === 'sqlite' ? "INSERT OR REPLACE" : "REPLACE") .
            " INTO channels_info (streamUrl, resolution, speed) VALUES (?, ?, ?)"
        );
        $stmt->execute([$streamUrl, $resolution, $speed]);
    }

    // 更新内存映射和频道状态
    $channelsInfoMap[$streamUrl] = is_numeric($speed) ? (int)$speed : PHP_INT_MAX;
    $channel[$disableIndex] = $disable;
    $channel[$modifiedIndex] = $modified;
    $channels[$i] = $channel;
}

echo "检测完成，已更新 channels_info 表。<br>";

// 按响应速度排序
if ($sortByDelay == 1) {
    // 按频道名称分组
    $groupedChannels = [];
    foreach ($channels as $channel) {
        $groupedChannels[$channel[$channelNameIndex]][] = $channel;
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
$placeholders = implode(', ', array_fill(0, count($headers), '?'));
$sql = "INSERT INTO channels (" . implode(', ', $headers) . ") VALUES ($placeholders)";
$stmt = $db->prepare($sql);
foreach ($channels as $row) {
    $channelsData[] = array_combine($headers, $row);
    $stmt->execute($row);
}
$db->commit();

// 重新生成 M3U 和 TXT 文件
generateLiveFiles($channelsData, 'tv', $liveSourceConfig, $saveOnly = true);

?>