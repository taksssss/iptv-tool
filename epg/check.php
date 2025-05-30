<?php
// 检测是否为 AJAX 请求或 CLI 运行
if (!(isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    && php_sapi_name() !== 'cli') {
    http_response_code(403); // 返回403禁止访问
    exit('禁止直接访问');
}

// 检测 ffmpeg 是否安装
if (!shell_exec('which ffprobe')) {
    echo '<p>未检测到 ffmpeg 环境，请使用以下指令重新部署：
        <br>docker rm php-epg -f && docker run -d --name php-epg -v /etc/epg:/htdocs/data -p 5678:80 -e ENABLE_FFMPEG=true --restart unless-stopped taksss/php-epg:latest</p>';
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

// 文件路径
$channelsFilePath = $liveDir . 'channels.csv';
$channelsBackFilePath = $liveDir . 'channels_orig.csv';
$channelsInfoFilePath = $liveDir . 'channels_info.csv';

// cleanMode 参数为 true 时，清除测速数据
if (isset($_GET['cleanMode']) && $_GET['cleanMode'] === 'true') {
    // 读取 channels_info.csv 文件
    if (!file_exists($channelsInfoFilePath)) die('channels_info.csv 文件不存在');
    $channelsInfo = array_map('str_getcsv', file($channelsInfoFilePath));
    $infoHeaders = array_shift($channelsInfo);
    $tagIndex = array_search('tag', $infoHeaders);
    $speedIndex = array_search('speed', $infoHeaders);

    if ($tagIndex === false || $speedIndex === false) die('channels_info.csv 文件缺少字段');

    // 读取 channels.csv 文件
    if (!file_exists($channelsFilePath)) die('channels.csv 文件不存在');
    $channels = array_map('str_getcsv', file($channelsFilePath));
    $headers = array_shift($channels);
    $disableIndex = array_search('disable', $headers);
    $modifiedIndex = array_search('modified', $headers);

    if ($disableIndex === false || $modifiedIndex === false) die('channels.csv 文件缺少字段');

    // 更新 channels.csv 中与 channels_info.csv 中 tag 匹配的记录
    foreach ($channels as &$channel) {
        foreach ($channelsInfo as $infoRow) {
            if ($infoRow[$speedIndex] === 'N/A' && 
                $infoRow[$tagIndex] === $channel[array_search('tag', $headers)]) {
                $channel[$disableIndex] = '0'; // 清除禁用标志
                $channel[$modifiedIndex] = '0'; // 清除修改标志
                break;
            }
        }
    }

    // 重新写回更新后的 channels.csv 文件
    array_unshift($channels, $headers);
    $fileHandle = fopen($channelsFilePath, 'w');
    foreach ($channels as &$channel) {
        fputcsv($fileHandle, $channel);
    }
    fclose($fileHandle);

    // 仅清空 channels_info.csv 中的数据，保留表头
    $fileHandle = fopen($channelsInfoFilePath, 'w');
    fputcsv($fileHandle, $infoHeaders); // 写入表头
    fclose($fileHandle);

    echo "测速数据已清除。";
    exit;
}

echo '<strong><span style="color: red;">前台测速过程中请勿关闭浏览器</span></strong><br><br>';

// 如果备份文件不存在，尝试从原文件创建备份
if (!file_exists($channelsBackFilePath) && !copy($channelsFilePath, $channelsBackFilePath)) {
    die('无法创建 channels_orig.csv 备份文件');
}
$channels = array_map('str_getcsv', file($channelsBackFilePath));

// 提取表头，定位 streamUrl 和 tag 索引
$headers = array_shift($channels);
$streamUrlIndex = array_search('streamUrl', $headers);
$tagIndex = array_search('tag', $headers);
$channelNameIndex = array_search('channelName', $headers);

// 确保必要字段存在
if ($streamUrlIndex === false || $tagIndex === false) {
    die('channels_orig.csv 文件缺少必要字段');
}

// 确保 disable 和 modified 字段存在
foreach (['disable', 'modified'] as $field) {
    if (!in_array($field, $headers)) {
        $headers[] = $field;
    }
}

$infoHeaders = ['tag', 'resolution', 'speed'];
$total = count($channels);
$channelsInfoMap = [];

// 只读取一次已有的 channels_info.csv
$infoData = [];
if (file_exists($channelsInfoFilePath)) {
    $rows = array_map('str_getcsv', file($channelsInfoFilePath));
    array_shift($rows); // 去掉表头
    foreach ($rows as $row) {
        $infoData[$row[0]] = [$row[1], $row[2]];
    }
}

foreach ($channels as $i => $channel) {
    $streamUrl = strtok($channel[$streamUrlIndex], '$'); // 处理带有 $ 的 URL
    $tag = $channel[$tagIndex];
    $channelName = $channelNameIndex !== false ? $channel[$channelNameIndex] : '未知频道';

    // 跳过空的 streamUrl
    if (empty($streamUrl)) continue;

    echo "(" . ($i + 1) . "/{$total}): {$channelName} - {$streamUrl}<br>";

    // 跳过 IPv6 源
    if ($checkIPv6 === 0 && preg_match('#^https?://\[[a-f0-9:]+\]#i', $streamUrl)) {
        echo '<strong><span style="color: red;">跳过 IPv6 源</span></strong><br><br>';
        continue;
    }

    // 使用 ffprobe 直接测量访问速度
    $startTime = microtime(true);
    $cmd = "ffprobe -rw_timeout 2000000 -v error -select_streams v:0 -show_entries stream=width,height -of csv=p=0 \"{$streamUrl}\"";
    exec($cmd, $output, $returnVar);
    $duration = round((microtime(true) - $startTime) * 1000);

    // 初始化字段索引
    $disableIndex = array_search('disable', $headers);
    $modifiedIndex = array_search('modified', $headers);

    // 如果 ffprobe 执行失败，访问速度设为 "N/A"，并设置 disable 和 modified 为 1
    if ($returnVar !== 0) {
        $resolution = $speed = 'N/A';
        $channel[$disableIndex] = '1';
        $channel[$modifiedIndex] = '1';
        echo '<strong><span style="color: red;">无法获取流信息，已停用</span></strong><br><br>';
    } else {
        $speed = $duration;
        $resolution = 'N/A';
        $channel[$disableIndex] = '0'; // 访问成功，清除禁用标志
        $channel[$modifiedIndex] = '0';

        // 解析 ffprobe 输出的分辨率
        foreach ($output as $line) {
            if (strpos($line, ',') !== false) {
                list($width, $height) = array_pad(explode(',', $line), 2, '未知');
                $resolution = "{$width}x{$height}";
                if ($width < $minWidth || $height < $minHeight) {
                    $channel[$disableIndex] = '1';
                    $channel[$modifiedIndex] = '1';
                    echo "<strong><span style='color: red;'>分辨率不满足 {$minWidth}x{$minHeight}，已停用</span></strong><br>";
                    break;
                }
            }
        }

        echo "分辨率: {$resolution}, 访问速度: {$speed} ms<br><br>";
    }

    // 更新内存映射及写入文件
    $infoData[$tag] = [$resolution, $speed];
    $channelsInfoMap[$tag] = is_numeric($speed) ? (int)$speed : PHP_INT_MAX;
    $channels[$i] = $channel;
    $fileHandle = fopen($channelsInfoFilePath, 'w');
    fputcsv($fileHandle, $infoHeaders);
    foreach ($infoData as $t => [$res, $spd]) {
        fputcsv($fileHandle, [$t, $res, $spd]);
    }
    fclose($fileHandle);
}

echo "检测完成，已更新 channels_info.csv 文件。<br>";

// 按响应速度排序
if ($sortByDelay == 1) {
    $channelNameIndex = array_search('channelName', $headers);
    $tagIndex = array_search('tag', $headers);

    // 按频道名称分组
    $groupedChannels = [];
    foreach ($channels as $channel) {
        $groupedChannels[$channel[$channelNameIndex]][] = $channel;
    }

    // 对每个分组进行排序
    foreach ($groupedChannels as &$group) {
        usort($group, function ($a, $b) use ($channelsInfoMap, $tagIndex) {
            return ($channelsInfoMap[$a[$tagIndex]] ?? PHP_INT_MAX-1) <=> ($channelsInfoMap[$b[$tagIndex]] ?? PHP_INT_MAX-1);
        });
    }
    unset($group); // 避免引用问题

    // 合并回 channels
    $channels = array_merge(...array_values($groupedChannels));
}

// 单个频道接口数量限制
if ($urlsLimit > 0) {
    $counts = [];
    $channels = array_filter($channels, function($channel) use (&$counts, $channelNameIndex, $urlsLimit) {
        $name = $channel[$channelNameIndex] ?? '';
        $counts[$name] = ($counts[$name] ?? 0) + 1;
        return $counts[$name] <= $urlsLimit;
    });
}

// 重新生成 M3U 和 TXT 文件
$channelsData = [];
foreach ($channels as $row) {
    $channelsData[] = array_combine($headers, $row);
}
generateLiveFiles($channelsData, 'tv', $saveOnly = true);

// 重新写回 channels.csv 文件
array_unshift($channels, $headers);
$fileHandle = fopen($channelsFilePath, 'w');
foreach ($channels as $channel) {
    fputcsv($fileHandle, $channel);
}
fclose($fileHandle);

?>
