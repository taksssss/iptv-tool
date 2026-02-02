<?php
/**
 * @file customSource.php
 * @brief 自定义数据源处理模块
 *
 * 本文件用于为主抓取器 `scraper.php` 提供扩展数据源的定义与处理逻辑。
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

return [

    // 添加更多数据源，示例：newsource, 频道名:频道ID
    // 'newsource' => [
    //     'match' => function ($url) {  return stripos($url, 'newsource') !== false; },
    //     'handler' => function ($url) { return newsourceHandler($url); }
    // ],
];

// 安全获取URL内容，支持请求间隔秒数控制，调用示例：safe_get_contents($url, 1.0);
function safe_get_contents(string $url, float $sleep_seconds = 0.0) {
    if ($sleep_seconds > 0) {
        usleep((int)($sleep_seconds * 1000000));
    }
    return @file_get_contents($url);
}

// /**
//  * 示例频道节目数据处理函数
//  */
// function newsourceHandler($url) {
//     // 示例：清除前缀（如 "prefix,"）
//     $cleanInput = str_ireplace('prefix,', '', $url);
//     $result = [];

//     // 拆分频道条目（格式：频道名:频道ID）
//     $channels = explode(',', $cleanInput);
//     foreach ($channels as $entry) {
//         list($name, $id) = array_map('trim', explode(':', trim($entry)) + [null, $entry]);

//         // 模拟节目页面URL构造
//         $pageUrl = "http://example.com/epg/" . $id . ".html";

//         // 模拟HTML抓取，每次间隔1.0秒
//         $html = safe_get_contents($pageUrl, 1.0);

//         if (!$html) continue;

//         // 模拟解析结果
//         $matches = []; // e.g. parsePrograms($html);

//         if (empty($matches)) continue;

//         $today = date("Y-m-d");
//         $programs = [];

//         // 示例节目处理逻辑
//         for ($i = 0; $i < count($matches); $i++) {
//             $start = "00:00";
//             $title = "示例节目";

//             if ($title === '结束') continue;

//             $end = "00:30"; // 可根据下一个节目时间设置
//             $programs[] = [
//                 'start' => $start,
//                 'end' => $end,
//                 'title' => $title,
//                 'desc' => '' // 示例无简介
//             ];
//         }

//         // 汇总结果
//         if (!empty($programs)) {
//             $result[$id] = [
//                 'channel_name' => $name,
//                 'programs' => [
//                     $today => $programs
//                 ],
//                 'count' => count($programs)
//             ];
//         }
//     }

//     return $result;
// }

?>