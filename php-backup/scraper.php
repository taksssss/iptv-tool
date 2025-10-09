<?php
/**
 * @file scraper.php
 * @brief 各数据源抓取与解析处理函数集合
 *
 * 本文件定义了多个数据源的匹配规则及对应的抓取处理函数。
 * 通过统一的 `$sourceHandlers` 数组，方便根据 URL 选择合适的数据抓取逻辑。
 * 
 * 数据源项结构说明：
 * - match (callable): 接收 URL，判断是否匹配当前数据源，返回布尔值。
 * - handler (callable): 实际抓取并解析数据的函数，返回格式化后的频道节目数据。
 *
 * handler 返回数据结构示例：
 * [
 *   'channel_id' => [
 *       'channel_name'   => '频道名称',
 *       'diyp_data'      => [
 *           'YYYY-MM-DD' => [
 *               ['start' => 'HH:mm', 'end' => 'HH:mm', 'title' => '节目名', 'desc' => '简介'],
 *               ...
 *           ],
 *           ...
 *       ],
 *       'process_count'  => 整数，处理的节目条数
 *   ],
 *   ...
 * ]
 * 
 * 作者: Tak
 * GitHub: https://github.com/taksssss/iptv-tool
 */

$sourceHandlers = [

    // 示例：tvmao, 湖南卫视
    'tvmao' => [
        'match' => function ($url) { return stripos($url, 'tvmao') === 0; },
        'handler' => function ($url) { return tvmaoHandler($url); }
    ],

    // 示例：cntv:2, CCTV4欧洲:cctveurope
    'cntv' => [
        'match' => function ($url) { return stripos($url, 'cntv') === 0; },
        'handler' => function ($url) { return cntvHandler($url); }
    ],
];

// 引入额外来源配置
$customHandlers = include __DIR__ . '/data/customSource.php';
if (is_array($customHandlers)) {
    $sourceHandlers = array_merge($sourceHandlers, $customHandlers);
}

/**
 * TVMao 数据处理
 */
function tvmaoHandler($url) {
    $tvmaostr = str_ireplace('tvmao,', '', $url);
    
    $channelProgrammes = [];
    foreach (explode(',', $tvmaostr) as $tvmao_info) {
        list($channelName, $channelId) = array_map('trim', explode(':', trim($tvmao_info)) + [null, $tvmao_info]);
        $channelProgrammes[$channelId]['channel_name'] = cleanChannelName($channelName);

        $json_url = "https://sp0.baidu.com/8aQDcjqpAAV3otqbppnN2DJv/api.php?query={$channelId}&resource_id=12520&format=json";
        $json_data = safe_get_contents($json_url);
        $json_data = mb_convert_encoding($json_data, 'UTF-8', 'GBK');
        $data = json_decode($json_data, true);
        if (empty($data['data'])) {
            $channelProgrammes[$channelId]['process_count'] = 0;
            continue;
        }
        $data = $data['data'][0]['data'];
        $skipTime = null;
        foreach ($data as $epg) {
            if ($time_str = $epg['times'] ?? '') {
                $starttime = DateTime::createFromFormat('Y/m/d H:i', $time_str);
                $date = $starttime->format('Y-m-d');
                // 如果第一条数据早于今天 02:00，则认为今天的数据是齐全的
                if (is_null($skipTime)) {
                    $skipTime = $starttime < new DateTime("today 02:00") ? 
                                new DateTime("today 00:00") : new DateTime("tomorrow 00:00");
                }
                if ($starttime < $skipTime) continue;
                $channelProgrammes[$channelId]['diyp_data'][$date][] = [
                    'start' => $starttime->format('H:i'),
                    'end' => '',  // 初始为空
                    'title' => trim($epg['title']),
                    'desc' => ''
                ];
            }
        }
        // 填充 'end' 字段
        foreach ($channelProgrammes[$channelId]['diyp_data'] as $date => &$programmes) {
            foreach ($programmes as $i => &$programme) {
                $nextStart = $programmes[$i + 1]['start'] ?? '00:00';  // 下一个节目开始时间或 00:00
                $programme['end'] = $nextStart;  // 填充下一个节目的 'start'
                if ($nextStart === '00:00') {
                    // 尝试获取第二天数据并补充
                    $nextDate = (new DateTime($date))->modify('+1 day')->format('Y-m-d');
                    $nextDayProgrammes = $channelProgrammes[$channelId]['diyp_data'][$nextDate] ?? [];
                    if (!empty($nextDayProgrammes) && $nextDayProgrammes[0]['start'] !== '00:00') {
                        array_unshift($channelProgrammes[$channelId]['diyp_data'][$nextDate], [
                            'start' => '00:00',
                            'end' => '',
                            'title' => $programme['title'],
                            'desc' => ''
                        ]);
                    }
                }
            }
        }
        $channelProgrammes[$channelId]['process_count'] = count($data);
    }
    return $channelProgrammes;
}

/**
 * CNTV 数据处理
 */
function cntvHandler($url) {
    $date_range = 1;
    if (preg_match('/^cntv:(\d+),\s*(.*)$/i', $url, $matches)) {
        $date_range = $matches[1]; // 提取日期范围
        $cntvstr = $matches[2]; // 提取频道字符串
    } else {
        $cntvstr = str_ireplace('cntv,', '', $url); // 没有日期范围时去除 'cntv,'
    }
    $need_dates = array_map(function($i) { return (new DateTime())->modify("+$i day")->format('Ymd'); }, range(0, $date_range - 1));

    $channelProgrammes = [];
    foreach (explode(',', $cntvstr) as $cntv_info) {
        list($channelName, $channelId) = array_map('trim', explode(':', trim($cntv_info)) + [null, $cntv_info]);
        $channelId = strtolower($channelId);
        $channelProgrammes[$channelId]['channel_name'] = cleanChannelName($channelName);

        $processCount = 0;
        foreach ($need_dates as $need_date) {
            $json_url = "https://api.cntv.cn/epg/getEpgInfoByChannelNew?c={$channelId}&serviceId=tvcctv&d={$need_date}";
            $json_data = safe_get_contents($json_url);
            $data = json_decode($json_data, true);
            if (!isset($data['data'][$channelId]['list'])) {
                continue;
            }
            $data = $data['data'][$channelId]['list'];
            foreach ($data as $epg) {
                $starttime = (new DateTime())->setTimestamp($epg['startTime']);
                $endtime = (new DateTime())->setTimestamp($epg['endTime']);
                $date = $starttime->format('Y-m-d');
                $channelProgrammes[$channelId]['diyp_data'][$date][] = [
                    'start' => $starttime->format('H:i'),
                    'end' => $endtime->format('H:i'),
                    'title' => trim($epg['title']),
                    'desc' => ''
                ];
            }
            $processCount += count($data);
        }
        $channelProgrammes[$channelId]['process_count'] = $processCount;
    }

    return $channelProgrammes;
}

?>
