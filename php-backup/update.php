<?php
/**
 * @file update.php
 * @brief 更新脚本
 * 
 * 该脚本用于定期从配置的 XML 源下载节目数据，并将其存入 SQLite 数据库中。
 * 
 * 作者: Tak
 * GitHub: https://github.com/taksssss/iptv-tool
 */

// 禁用 PHP 输出缓冲
ob_implicit_flush(true);
@ob_end_flush();

// 设置 header，防止浏览器缓存输出
header('X-Accel-Buffering: no');

// 显示 favicon
echo '<link rel="icon" href="assets/img/favicon.ico" type="image/x-icon">';
echo '<title>更新数据</title>';

// 引入脚本
require_once 'public.php';
require_once 'scraper.php';

// 设置超时时间为20分钟
set_time_limit(20*60);

// 检测是否为 AJAX 请求或 CLI 运行
if (!(isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    && php_sapi_name() !== 'cli') {
    http_response_code(403); // 返回403禁止访问
    exit('禁止直接访问，请修改update.php');
}

// 获取目标时区
$target_time_zone = $Config['target_time_zone'] ?? 0;

// 删除过期数据和日志
function deleteOldData($db, $thresholdDate, &$log_messages) {
    global $Config;

    // 删除 t.xml 和 t.xml.gz 文件
    if (!$Config['gen_xml']) {
        @unlink(__DIR__ . '/data/t.xml');
        @unlink(__DIR__ . '/data/t.xml.gz');
    }

    // 循环清理过期数据
    $tables = [
        'epg_data' => ['date', '清理EPG数据'],
        'update_log' => ['timestamp', '清理更新日志'],
        'cron_log' => ['timestamp', '清理定时日志']
    ];
    foreach ($tables as $table => $values) {
        list($column, $logMessage) = $values;
        $stmt = $db->prepare("DELETE FROM $table WHERE $column < :thresholdDate");
        $stmt->bindValue(':thresholdDate', $thresholdDate, PDO::PARAM_STR);
        $stmt->execute();
        logMessage($log_messages, "【{$logMessage}】 共 {$stmt->rowCount()} 条。");
    }

    // 清理访问日志
    if ($Config['debug_mode']) {
        $thresholdTimestamp = strtotime($thresholdDate . ' 00:00:00');
        $thresholdStr = date('Y-m-d H:i:s', $thresholdTimestamp);
    
        $stmt = $db->prepare("DELETE FROM access_log WHERE access_time < ?");
        $stmt->execute([$thresholdStr]);
    
        $deletedCount = $stmt->rowCount();
        logMessage($log_messages, "【清理访问日志】 共 $deletedCount 条。");
    }
    
    // 清理缓存数据
    $cached_type = $Config['cached_type'] ?? 'memcached';
    if ($cached_type === 'memcached' && class_exists('Memcached') && ($memcached = new Memcached())->addServer('127.0.0.1', 11211)) {
        $memcached->flush();
        logMessage($log_messages, "【Memcached】 已清空。");
    } elseif ($cached_type === 'redis' && class_exists('Redis') && ($redis = new Redis()) && $redis->connect($Config['redis']['host'], $Config['redis']['port']) 
        && (empty($Config['redis']['password']) || $redis->auth($Config['redis']['password'])) && $redis->ping()) {
        $redis->flushAll();
        logMessage($log_messages, "【Redis】 已清空。");
    } else {
        logMessage($log_messages, "【" . ucfirst($cached_type) . "】 状态异常。");
    }

    echo "<br>";
}

// 根据时间字符串和时区偏移，计算目标时区及额外偏移后的格式化日期和时间
function getFormatTime($time, $time_offset) {
    global $Config, $target_time_zone;

    preg_match('/^(\d{14})\s*([+-]\d{4})$/', $time, $m);
    $base_time = $m[1];
    $source_offset = $m[2];

    // 用 UTC 时区解析基础时间，避免系统时区干扰
    $dt = DateTime::createFromFormat('YmdHis', $base_time, new DateTimeZone('UTC'));
    $timestamp = $dt ? $dt->getTimestamp() : 0;

    $time_offset_sec = empty($time_offset) ? 0 : offsetToSeconds($time_offset);

    if (empty($target_time_zone)) {
        $total_offset = $time_offset_sec;
    } else {
        $total_offset = offsetToSeconds($target_time_zone) - offsetToSeconds($source_offset) + $time_offset_sec;
    }

    $final_time = gmdate('Y-m-d H:i', $timestamp + $total_offset);
    return explode(' ', $final_time);
}

function offsetToSeconds($offset) {
    $sign = ($offset[0] === '-') ? -1 : 1;
    $hours = intval(substr($offset, 1, 2));
    $minutes = intval(substr($offset, 3, 2));
    return $sign * ($hours * 3600 + $minutes * 60);
}

// 辅助函数：将日期和时间格式化为 XMLTV 格式
function formatTime($date, $time) {
    global $target_time_zone;
    $tz = empty($target_time_zone) ? '+0800' : $target_time_zone; // 关闭时区转换时，设为 +0800
    return date("YmdHis $tz", strtotime("$date $time"));
}

// 获取限定频道列表及映射关系
function getGenList($db) {
    global $Config;
    $channels = $db->query("SELECT channel FROM gen_list")->fetchAll(PDO::FETCH_COLUMN);
    if (empty($channels)) {
        return ['gen_list_mapping' => [], 'gen_list' => []];
    }

    $channelsSimplified = explode("\n", t2s(implode("\n", $channels)));
    $allEpgChannels = $db->query("SELECT DISTINCT channel FROM epg_data WHERE date = DATE('now')")
        ->fetchAll(PDO::FETCH_COLUMN); // 避免匹配只有历史 EPG 的频道

    $gen_list_mapping = [];
    $cleanedChannels = array_map('cleanChannelName', $channelsSimplified);

    foreach ($cleanedChannels as $index => $cleanedChannel) {
        $bestMatch = $cleanedChannel;  // 默认使用清理后的频道名
        $bestMatchLength = 0;  // 初始为0，表示未找到任何匹配

        foreach ($allEpgChannels as $epgChannel) {
            if (strcasecmp($cleanedChannel, $epgChannel) === 0) {
                $bestMatch = $epgChannel;
                break;  // 精确匹配，立即跳出循环
            }

            // 模糊匹配并选择最长的频道名称
            if ((stripos($epgChannel, $cleanedChannel) === 0 || stripos($cleanedChannel, $epgChannel) !== false) 
                && mb_strlen($epgChannel) > $bestMatchLength) {
                $bestMatch = $epgChannel;
                $bestMatchLength = mb_strlen($epgChannel);  // 更新为更长的匹配
            }
        }

        // 将原始频道名称添加到映射数组中
        $gen_list_mapping[$bestMatch][] = $channels[$index];
    }

    return [
        'gen_list_mapping' => $gen_list_mapping,
        'gen_list' => array_unique($cleanedChannels)
    ];
}

// 获取频道指定 EPG 关系
function getChannelBindEPG() {
    global $Config;
    $channelBindEPG = [];
    foreach ($Config['channel_bind_epg'] ?? [] as $epg_src => $channels) {
        foreach (array_map('trim', explode(',', $channels)) as $channel) {
            $channelBindEPG[$channel][] = $epg_src;
        }
    }
    return $channelBindEPG;
}

// 下载 XML 数据并存入数据库
function downloadXmlData($xml_url, $userAgent, $db, &$log_messages, $gen_list, $white_list, $black_list, $time_offset, $replacePattern) {
    global $Config;
    [$xml_data, $error, $mtime] = downloadData($xml_url, $userAgent);
    if ($xml_data !== false) {
        if (substr($xml_data, 0, 2) === "\x1F\x8B") { // 通过魔数判断 .gz 文件
            $mtime = $mtime ?: unpack('V', substr($xml_data, 4, 4))[1];
            if (!($xml_data = gzdecode($xml_data))) {
                logMessage($log_messages, '【解压失败】');
                return;
            }
        }
        $mtimeStr = $mtime ? ' | 修改时间：' . date('Y-m-d H:i:s', $mtime) : '';

        // 获取文件大小（字节）并转换为 KB/MB
        $fileSize = strlen($xml_data);
        $fileSizeReadable = $fileSize >= 1048576 
            ? round($fileSize / 1048576, 2) . ' MB' 
            : round($fileSize / 1024, 2) . ' KB';
        logMessage($log_messages, "【下载】 成功 | xml 文件大小：{$fileSizeReadable}{$mtimeStr}");
        
        $xml_data = mb_convert_encoding($xml_data, 'UTF-8'); // 转换成 UTF-8 编码

        // 应用多个字符串替换规则（JSON格式或老格式 a->b,...）
        if (!empty($replacePattern)) {
            $jsonRules = json_decode($replacePattern, true);
            
            if (json_last_error() === JSON_ERROR_NONE && is_array($jsonRules)) {
                // JSON格式
                foreach ($jsonRules as $search => $replace) {
                    $xml_data = str_replace($search, $replace, $xml_data);
                }
            } elseif (strpos($replacePattern, '->') !== false) {
                // 兼容老格式
                foreach (explode(',', $replacePattern) as $rule) {
                    if (strpos($rule, '->') !== false) {
                        [$search, $replace] = array_map('trim', explode('->', $rule, 2));
                        $xml_data = str_replace($search, $replace, $xml_data);
                    }
                }
            }
        }

        if (($Config['cht_to_chs'] ?? 1) === 2) { $xml_data = t2s($xml_data); }
        $db->beginTransaction();
        try {
            [$processCount, $skipCount] = processXmlData($xml_url, $xml_data, $db, $gen_list, $white_list, $black_list, $time_offset);
            $db->commit();
            logMessage($log_messages, "【更新】 成功：入库 {$processCount} 条，跳过 {$skipCount} 条");
        } catch (Exception $e) {
            $db->rollBack();
            logMessage($log_messages, "【处理数据出错！！！】 " . $e->getMessage());
        }
    } else {
        logMessage($log_messages, "【下载】 失败！！！错误信息：$error");
    }
    echo "<br>";
}

// 处理 XML 数据并逐步存入数据库
function processXmlData($xml_url, $xml_data, $db, $gen_list, $white_list, $black_list, $time_offset) {
    global $Config, $processedRecords, $channel_bind_epg, $thresholdDate;

    // 统计处理数据量
    $programmeCount = 0;
    $skipCount = 0;

    $reader = new XMLReader();
    if (!$reader->XML($xml_data)) {
        throw new Exception("无法解析 XML 数据");
    }

    $cleanChannelNames = [];

    // 读取频道数据
    while ($reader->read() && $reader->name !== 'channel');
    while ($reader->name === 'channel') {
        $channel = new SimpleXMLElement($reader->readOuterXML());
        $channelId = (string)$channel['id'];
        $cleanChannelNames[$channelId] = cleanChannelName((string)$channel->{'display-name'});
        $reader->next('channel');
    }

    // 繁简转换和频道筛选
    $simplifiedChannelNames = ($Config['cht_to_chs'] ?? 1) === 1 ? 
        explode("\n", t2s(implode("\n", $cleanChannelNames))) : $cleanChannelNames;
    $channelNamesMap = [];
    foreach ($cleanChannelNames as $channelId => $channelName) {
        $channelNameSimplified = array_shift($simplifiedChannelNames);

        // 频道指定来源且不为当前 xml_url、或不在白名单、或在黑名单中，直接跳过
        if ((!empty($channel_bind_epg) && isset($channel_bind_epg[$channelNameSimplified]) && 
            !in_array($xml_url, $channel_bind_epg[$channelNameSimplified])) || 
            (!empty($white_list) && !in_array($channelNameSimplified, $white_list) && !in_array($channelId, $white_list)) || 
            (in_array($channelNameSimplified, $black_list) || in_array($channelId, $black_list))) {
            continue;
        }

        // 当 gen_list_enable 为 0 时，插入所有数据
        if (empty($Config['gen_list_enable'])) {
            $channelNamesMap[$channelId] = $channelNameSimplified;
            continue;
        }
        
        foreach ($gen_list as $item) {
            if (stripos($channelNameSimplified, $item) !== false || 
                stripos($item, $channelNameSimplified) !== false) {
                $channelNamesMap[$channelId] = $channelNameSimplified;
                break;
            }
        }
    }

    $reader->close();
    $reader->XML($xml_data); // 重置 XMLReader
    while ($reader->read() && $reader->name !== 'programme');

    $currentChannelProgrammes = [];
    $crossDayProgrammes = []; // 保存跨天的节目数据
    
    while ($reader->name === 'programme') {
        $programmeCount++;
        $programme = new SimpleXMLElement($reader->readOuterXML());
        [$startDate, $startTime] = getFormatTime((string)$programme['start'], $time_offset);
        [$endDate, $endTime] = getFormatTime((string)$programme['stop'], $time_offset);

        // 判断数据是否符合设定期限
        if (empty($startDate) || $startDate < $thresholdDate || empty($endDate)) {
            $skipCount++;
            $reader->next('programme');
            continue;
        }

        $channelId = (string)$programme['channel'];
        $channelName = $channelNamesMap[$channelId] ?? null;
        $recordKey = $channelName . '-' . $startDate;

        // 优先处理跨天数据
        if (isset($crossDayProgrammes[$channelId][$startDate]) && !isset($processedRecords[$recordKey])) {
            $currentChannelProgrammes[$channelId]['diyp_data'][$startDate] = $crossDayProgrammes[$channelId][$startDate];
            unset($crossDayProgrammes[$channelId][$startDate]);
        }
    
        if ($channelName && !isset($processedRecords[$recordKey])) {
            $programmeData = [
                'start' => $startTime,
                'end' => $startDate === $endDate ? $endTime : '00:00',
                'title' => (string)$programme->title,
                'desc' => isset($programme->desc) ? (string)$programme->desc : ''
            ];
    
            $currentChannelProgrammes[$channelId]['diyp_data'][$startDate][] = $programmeData;
    
            // 保存跨天的节目数据
            if ($startDate !== $endDate && $endTime !== '00:00') {
                $crossDayProgrammes[$channelId][$endDate][] = [
                    'start' => '00:00',
                    'end' => $endTime,
                    'title' => $programmeData['title'],
                    'desc' => $programmeData['desc']
                ];
                $programmeCount++;
            }
    
            $currentChannelProgrammes[$channelId]['channel_name'] = $channelName;
    
            // 每次达到 50 时，插入数据并保留最后一条
            if (count($currentChannelProgrammes) >= 50) {
                $lastProgramme = array_pop($currentChannelProgrammes); // 取出最后一条
                $skipCount += insertDataToDatabase($currentChannelProgrammes, $db, $xml_url); // 插入前 49 条
                $currentChannelProgrammes = [$channelId => $lastProgramme]; // 清空并重新赋值最后一条
            }
        } else{
            $skipCount++;
        }
    
        $reader->next('programme');
    }
    
    // 插入剩余的数据
    if ($currentChannelProgrammes) {
        $skipCount += insertDataToDatabase($currentChannelProgrammes, $db, $xml_url);
    }
    
    $reader->close();

    $processCount = $programmeCount - $skipCount;
    return [$processCount, $skipCount];
}

// 从 epg_data 读取数据，生成 iconList.json 及 xmltv 文件
function processIconListAndXmltv($db, $gen_list_mapping, &$log_messages) {
    global $Config, $iconList, $iconListPath;

    $currentDate = date('Y-m-d'); // 获取当前日期
    $dateCondition = $Config['include_future_only'] ? "WHERE date >= '$currentDate'" : '';

    // 合并查询
    $query = "SELECT date, channel, epg_diyp FROM epg_data $dateCondition ORDER BY channel ASC, date ASC";
    $stmt = $db->query($query);

    // 存储节目数据以按频道分组
    $channelData = [];

    while ($program = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $channelName = $program['channel'];
        $iconUrl = iconUrlMatch($channelName, false);

        if ($iconUrl) {
            $iconList[strtoupper($channelName)] = $iconUrl;
        }

        // gen_list_enable 为 0 或存在映射，则处理频道数据
        if (empty($Config['gen_list_enable']) || isset($gen_list_mapping[$channelName])) {
            $channelData[$channelName][] = $program;
        }
    }
    
    // 更新 iconList.json 文件中的数据
    if (file_put_contents($iconListPath, 
        json_encode($iconList, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) === false) {
        logMessage($log_messages, "【台标列表】 更新 iconList.json 时发生错误！！！");
    } else {
        logMessage($log_messages, "【台标列表】 已更新 iconList.json");
    }

    // 判断是否生成 xmltv 文件
    if (empty($Config['gen_xml'])) {
        return;
    }
    
    // 创建 XMLWriter 实例
    $xmlFilePath = __DIR__ . '/data/t.xml';
    $xmlWriter = new XMLWriter();
    $xmlWriter->openUri($xmlFilePath);
    $xmlWriter->startDocument('1.0', 'UTF-8');
    $xmlWriter->startElement('tv');
    $xmlWriter->writeAttribute('generator-info-name', 'Tak');
    $xmlWriter->writeAttribute('generator-info-url', 'https://github.com/taksssss/iptv-tool');
    $xmlWriter->setIndent(true);
    $xmlWriter->setIndentString('	'); // 设置缩进

    // 将 $Config['channel_mappings'] 中的映射值转换为数组
    $channelMappings = array_map(function($mapped) {
        return strpos($mapped, 'regex:') === 0 ? [$mapped] : array_map('trim', explode(',', $mapped));
    }, $Config['channel_mappings']);

    // 逐个频道处理
    foreach ($channelData as $channelName => $programs) {
        // 为该频道生成多个 display-name ，包括原频道名、限定频道列表、频道别名
        $displayNames = array_unique(array_merge(
            [$channelName],
            $gen_list_mapping[$channelName] ?? [],
            $channelMappings[$channelName] ?? []
        ));
        foreach ($displayNames as $displayName) {
            $xmlWriter->startElement('channel');
            $xmlWriter->writeAttribute('id', htmlspecialchars($channelName, ENT_XML1, 'UTF-8'));
            $xmlWriter->startElement('display-name');
            $xmlWriter->writeAttribute('lang', 'zh');
            $xmlWriter->text($displayName);
            $xmlWriter->endElement(); // display-name
            $xmlWriter->endElement(); // channel
        }

        // 写入该频道的所有节目数据
        foreach ($programs as $programIndex => &$program) {
            $data = json_decode($program['epg_diyp'], true);
            $dataCount = count($data['epg_data']);
            $end_date = $program['date'];
        
            for ($index = 0; $index < $dataCount; $index++) {
                $item = $data['epg_data'][$index];
                $end_time = $item['end'];
        
                // 如果结束时间为 00:00，切换到第二天的日期
                if ($end_time == '00:00') {
                    $end_date = date('Ymd', strtotime($end_date . ' +1 day'));  // 切换日期
        
                    // 合并下一个节目
                    if (isset($programs[$programIndex + 1])) {
                        $nextData = json_decode($programs[$programIndex + 1]['epg_diyp'], true);
                        $nextItem = $nextData['epg_data'][0] ?? null;
    
                        if ($nextItem && $nextItem['title'] == $item['title']) {
                            $end_time = $nextItem['end'];
                            array_splice($nextData['epg_data'], 0, 1); // 删除下一个节目的第一个项目
                            $programs[$programIndex + 1]['epg_diyp'] = json_encode($nextData);
                        }
                    }
                }
        
                // 写入当前节目
                $xmlWriter->startElement('programme');
                $xmlWriter->writeAttribute('channel', htmlspecialchars($channelName, ENT_XML1, 'UTF-8'));
                $xmlWriter->writeAttribute('start', formatTime($program['date'], $item['start']));
                $xmlWriter->writeAttribute('stop', formatTime($end_date, $end_time));
                $xmlWriter->startElement('title');
                $xmlWriter->writeAttribute('lang', 'zh');
                $xmlWriter->text($item['title']);
                $xmlWriter->endElement(); // title
                if (!empty($item['sub-title'])) {
                    $xmlWriter->startElement('sub-title');
                    $xmlWriter->writeAttribute('lang', 'zh');
                    $xmlWriter->text($item['sub-title']);
                    $xmlWriter->endElement(); // sub-title
                }
                if (!empty($item['desc'])) {
                    $xmlWriter->startElement('desc');
                    $xmlWriter->writeAttribute('lang', 'zh');
                    $xmlWriter->text($item['desc']);
                    $xmlWriter->endElement(); // desc
                }
                $xmlWriter->endElement(); // programme
            }
        }
    }

    // 结束 XML 文档
    $xmlWriter->endElement(); // tv
    $xmlWriter->endDocument();
    $xmlWriter->flush();

    // 所有频道数据写入完成后，生成 t.xml.gz 文件
    compressXmlFile($xmlFilePath);

    logMessage($log_messages, "【预告文件】 已生成 t.xml、t.xml.gz");
}

// 生成 t.xml.gz 压缩文件
function compressXmlFile($xmlFilePath) {
    $gzFilePath = $xmlFilePath . '.gz';

    // 打开原文件和压缩文件
    $file = fopen($xmlFilePath, 'rb');
    $gzFile = gzopen($gzFilePath, 'wb9'); // 最高压缩等级

    // 将文件内容写入到压缩文件中
    while (!feof($file)) {
        gzwrite($gzFile, fread($file, 1024 * 512));
    }

    // 关闭文件
    fclose($file);
    gzclose($gzFile);
}

// 记录开始时间
$startTime = microtime(true);

// 统计节目条数
$getCount = function() use ($db) {
    $count = 0;
    foreach ($db->query("SELECT epg_diyp FROM epg_data") as $row) {
        $epg = json_decode($row['epg_diyp'], true);
        $count += count($epg['epg_data'] ?? []);
    }
    return $count;
};

// 统计更新前数据条数
$initialCount = $getCount();

// 删除过期数据
$thresholdDate = date('Y-m-d', strtotime("-{$Config['days_to_keep']} days +1 day"));
deleteOldData($db, $thresholdDate, $log_messages);

// 获取限定频道列表及映射关系
$gen_res = getGenList($db);
$gen_list = $gen_res['gen_list'];
$gen_list_mapping = $gen_res['gen_list_mapping'];

// 获取频道指定 EPG 关系
$channel_bind_epg = getChannelBindEPG();

// 全局变量，用于记录已处理的记录
$processedRecords = [];

if (!empty($target_time_zone)) {
    logMessage($log_messages, "【时区转换】 目标：UTC" . substr_replace($target_time_zone, ':', -2, 0));
} else {
    logMessage($log_messages, "【时区转换】 关闭");
}
echo "<br>";

// 更新数据
foreach ($Config['xml_urls'] as $xml_url) {
    // 去掉空白字符，忽略空行和以 # 开头的 URL
    $xml_url = trim($xml_url);
    if (empty($xml_url) || strpos($xml_url, '#') === 0) {
        continue;
    }

    // 匹配自定义数据源
    foreach ($sourceHandlers as $source => $info) {
        if (isset($info['match']) && is_callable($info['match']) && $info['match']($xml_url)) {
            scrapeSource($source, $xml_url, $db, $log_messages);
            continue 2; // 匹配到后跳出外层循环
        }
    }

    // 更新 XML 数据
    $xml_parts = explode('#', $xml_url);
    $cleaned_url = trim($xml_parts[0]);
    logMessage($log_messages, "【地址】 $cleaned_url");
    
    $userAgent = $time_offset = $replacePattern = '';
    $white_list = $black_list = [];

    foreach ($xml_parts as $part) {
        $part = trim($part);
        if (strpos($part, '=') === false) continue;
    
        [$key, $value] = explode('=', $part, 2);
        $key = strtolower(trim($key));
        $value = trim($value);
    
        switch ($key) {
            case 'ua':
            case 'useragent':
                $userAgent = $value;
                logMessage($log_messages, "【自定】 UA：$userAgent");
                break;
    
            case 'ft':
            case 'filter':
                $filter_raw = strtoupper(t2s($value));
                $list = array_map('trim', explode(',', ltrim($filter_raw, '!')));
                if ($filter_raw[0] === '!') {
                    $black_list = $list;
                    logMessage($log_messages, "【临时】 屏蔽频道：" . implode(", ", $black_list));
                } else {
                    $white_list = $list;
                    logMessage($log_messages, "【临时】 限定频道：" . implode(", ", $white_list));
                }
                break;
    
            case 'to':
            case 'timeoffset':
                $time_offset = $value;
                logMessage($log_messages, "【修正】 时间偏移：$time_offset");
                break;
    
            case 'rp':
            case 'replace':
                $replacePattern = $value;
                logMessage($log_messages, "【替换】 $replacePattern");
                break;
        }
    }
    
    downloadXmlData($cleaned_url, $userAgent, $db, $log_messages, $gen_list, $white_list, $black_list, $time_offset, $replacePattern);
}

// 更新 iconList.json 及生成 xmltv 文件
processIconListAndXmltv($db, $gen_list_mapping, $log_messages);

// 判断是否同步更新直播源
if ($syncMode = $Config['live_source_auto_sync'] ?? false) {
    $parseResult = $syncMode == 2 ? doParseSourceInfo(null, true) : doParseSourceInfo();
    $tip = $syncMode == 2 ? '（所有配置）' : '（当前配置）';
    if ($parseResult === true) {
        logMessage($log_messages, "【直播文件】 已同步更新{$tip}");
    } else {
        logMessage($log_messages, "【直播文件】 部分更新异常{$tip}：" . rtrim(str_replace('<br>', '、', $parseResult), '、'));
    }
}

// 统计更新后数据条数
$finalCount = $getCount();
$dif = $finalCount - $initialCount;
$msg = $dif != 0 ? ($dif > 0 ? " 增加 $dif 条。" : " 减少 " . abs($dif) . " 条。") : "";
$endTime = microtime(true); // 记录结束时间
$executionTime = round($endTime - $startTime, 1);
echo "<br>";
logMessage($log_messages, "【更新完成】 {$executionTime} 秒。节目数量：更新前 {$initialCount} 条，更新后 {$finalCount} 条。" . $msg);

// 将日志信息写入数据库
$log_message_str = implode("<br>", $log_messages);
$timestamp = date('Y-m-d H:i:s'); // 使用设定的时区时间
$stmt = $db->prepare('INSERT INTO update_log (timestamp, log_message) VALUES (:timestamp, :log_message)');
$stmt->bindValue(':timestamp', $timestamp, PDO::PARAM_STR);
$stmt->bindValue(':log_message', $log_message_str, PDO::PARAM_STR);
$stmt->execute();

?>