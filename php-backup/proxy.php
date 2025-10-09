<?php
/**
 * @file proxy.php
 * @brief IPTV 流媒体代理脚本
 *
 * 作者: Tak
 * GitHub: https://github.com/taksssss/iptv-tool
 */

require_once 'public.php';

// 解析查询参数
$query = $_SERVER['QUERY_STRING'] ?? '';
parse_str(str_replace('?', '&', $query), $query_params);

// 获取加密后的 URL
$encUrl = $query_params['url'] ?? '';
if (!$encUrl) {
    http_response_code(403);
    exit('Forbidden: Missing URL');
}

// 解密 URL
$url = decryptUrl($encUrl, $Config['token']);
if ($url === false) {
    http_response_code(403);
    exit('Forbidden: Invalid URL');
}

// 检查 #NOPROXY 标记并移除
$nop = false;
if (substr($url, -8) === '#NOPROXY') {
    $nop = true;
    $url = substr($url, 0, -8);
}

// 去掉 url 参数，拼接其他参数
unset($query_params['url']);
if ($query_params) {
    $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($query_params);
}

// 如果是 NOPROXY，直接跳转
if ($nop) {
    header("Location: $url");
    exit;
}

// 清理缓冲
while (ob_get_level()) ob_end_clean();
set_time_limit(0);

// 收集客户端请求头（去掉 Host 和 Accept-Encoding）
$clientHeaders = [];
foreach (getallheaders() as $key => $value) {
    $lower = strtolower($key);
    if ($lower === 'host' || $lower === 'accept-encoding') continue;
    $clientHeaders[] = "$key: $value";
}

// 初始化请求
$ch = curl_init($url);
$isM3U8 = (stripos($url, '.m3u8') !== false);
$buffer = '';
$contentType = null;

curl_setopt_array($ch, [
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_HTTPHEADER     => $clientHeaders,
    CURLOPT_CONNECTTIMEOUT => 10, // 连接超时
    CURLOPT_TIMEOUT        => 0,  // 数据流不超时
]);

// 响应头处理，动态识别 m3u8
curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($ch, $header) use (&$isM3U8, &$contentType) {
    if (stripos($header, 'Content-Type:') === 0) {
        $contentType = trim(substr($header, 13));
        if (stripos($contentType, 'mpegurl') !== false) {
            $isM3U8 = true;
        }
    }
    // 保留源站响应头（跳过 Transfer-Encoding）
    $h = trim($header);
    if ($h && stripos($h, 'Transfer-Encoding') === false) {
        header($h, false);
    }
    return strlen($header);
});

// 响应体处理
curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($c, $data) use (&$buffer, &$isM3U8) {
    if ($isM3U8) {
        $buffer .= $data; // 累积 m3u8
    } else {
        echo $data; // TS 或其他流实时透传
        flush();
    }
    return strlen($data);
});

// 执行请求
$ok = curl_exec($ch);

if ($ok === false) {
    http_response_code(502);
    $err = curl_error($ch);
    curl_close($ch);
    exit("Bad Gateway: $err");
}

// 获取最终有效地址（考虑重定向）
$finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
curl_close($ch);

// 如果不是 m3u8，TS 已透传，结束
if (!$isM3U8) {
    exit;
}

// 处理 M3U8
header('Content-Type: application/vnd.apple.mpegurl');

// 计算基础路径
$base = preg_replace('/\/[^\/]*$/', '/', $finalUrl);

// 自动生成代理路径
$proxy = $_SERVER['PHP_SELF'] . '?url=';

// 替换 m3u8 中的资源地址
echo preg_replace_callback(
    '/^(?!#)(.+)$/mi',
    function ($m) use ($base, $proxy, $Config) {
        $link = trim($m[1]);
        if ($link === '') return $m[0];
        if (!preg_match('/^[a-zA-Z]+:\/\//', $link)) {
            $link = $base . $link;
        }
        $encLink = encryptUrl($link, $Config['token']);
        return $proxy . urlencode($encLink);
    },
    $buffer
);