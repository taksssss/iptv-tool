<?php
/**
 * @file proxy.php
 * @brief 通用转发代理：/epg/proxy.php?token=xxx&url=ENCODED_URL
 *
 * 安全性：
 * - 校验 token，遵循 config token_range 规则
 * - 仅允许 http/https 协议
 * - 基础 SSRF 防护：拒绝解析到内网/本地地址
 * - 不自动跟随重定向
 */

require_once __DIR__ . '/public.php';

// 统一生成响应
function send_response($body, $status = 200, $headers = []) {
	$headers['Access-Control-Allow-Origin'] = '*';
	http_response_code($status);
	foreach ($headers as $key => $value) {
		header($key . ': ' . $value);
	}
	echo $body;
	exit;
}

// 与 index.php 一致的判定逻辑
function isAllowed($value, array $allowedList, int $range, bool $isLive): bool
{
	foreach ($allowedList as $allowed) {
		if (strpos($allowed, 'regex:') === 0) {
			if (@preg_match(substr($allowed, 6), $value)) return true;
		} elseif ($value === $allowed) {
			return true;
		}
	}
	return ($range === 2 && $isLive) || ($range === 1 && !$isLive);
}

// 获取真实 IP
function getClientIp() {
	if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
		return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
	} elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
		return $_SERVER['HTTP_CLIENT_IP'];
	}
	return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

// SSRF 辅助：判断是否为内网/本地地址
function is_private_ip(string $ip): bool {
	$inet = @inet_pton($ip);
	if ($inet === false) return false;
	$len = strlen($inet);
	if ($len === 4) {
		// IPv4
		$long = sprintf('%u', ip2long($ip));
		$long = (int)$long;
		$ranges = [
			['start' => ip2long('10.0.0.0'), 'end' => ip2long('10.255.255.255')],
			['start' => ip2long('127.0.0.0'), 'end' => ip2long('127.255.255.255')],
			['start' => ip2long('172.16.0.0'), 'end' => ip2long('172.31.255.255')],
			['start' => ip2long('192.168.0.0'), 'end' => ip2long('192.168.255.255')],
			['start' => ip2long('169.254.0.0'), 'end' => ip2long('169.254.255.255')], // link-local
		];
		foreach ($ranges as $r) {
			if ($long >= $r['start'] && $long <= $r['end']) return true;
		}
		return false;
	}
	// IPv6
	// ::1, fc00::/7 (unique local), fe80::/10 (link-local)
	if ($ip === '::1') return true;
	$hex = bin2hex($inet);
	$prefix = substr($hex, 0, 4);
	// fc00::/7 => fc00..fdff
	$first_byte = hexdec(substr($hex, 0, 2));
	if (($first_byte & 0xfe) === 0xfc) return true;
	// fe80::/10 => fe80..febf
	if (substr($hex, 0, 4) >= 'fe80' && substr($hex, 0, 4) <= 'febf') return true;
	return false;
}

function get_request_headers(): array {
	if (function_exists('getallheaders')) {
		$h = @getallheaders();
		return is_array($h) ? $h : [];
	}
	$headers = [];
	foreach ($_SERVER as $name => $value) {
		if (substr($name, 0, 5) == 'HTTP_') {
			$key = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
			$headers[$key] = $value;
		}
	}
	return $headers;
}

// 解析参数
$query = $_SERVER['QUERY_STRING'] ?? '';
$query = str_replace('?', '&', $query);
parse_str($query, $query_params);

// 校验 token（遵循 token_range）
$tokenRange = (int)($Config['token_range'] ?? 1);
$allowedTokens = array_map('trim', explode(PHP_EOL, $Config['token'] ?? ''));
$token = $query_params['token'] ?? '';
if ($tokenRange !== 0) {
	// 代理请求不视为直播
	if (!isAllowed($token, $allowedTokens, $tokenRange, false)) {
		send_response('访问被拒绝：无效Token。', 403);
	}
}

$targetUrl = trim($query_params['url'] ?? '');
if ($targetUrl === '') {
	send_response('参数错误：缺少 url。', 400);
}

// 仅允许 http/https
$parts = @parse_url($targetUrl);
if (!$parts || empty($parts['scheme']) || !in_array(strtolower($parts['scheme']), ['http', 'https'])) {
	send_response('参数错误：只允许 http/https。', 400);
}

// Host 合法性 & SSRF 粗略校验
$host = $parts['host'] ?? '';
if ($host === '' || strtolower($host) === 'localhost') {
	send_response('目标地址不允许（localhost）。', 400);
}

// 解析 A/AAAA 记录并拒绝内网/本地
$records = @dns_get_record($host, DNS_A + DNS_AAAA);
if (is_array($records) && !empty($records)) {
	foreach ($records as $rec) {
		$ip = $rec['type'] === 'A' ? ($rec['ip'] ?? '') : ($rec['ipv6'] ?? '');
		if ($ip !== '' && is_private_ip($ip)) {
			send_response('目标地址不允许（内网/本地）。', 400);
		}
	}
}

// 读取请求信息
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$body = file_get_contents('php://input');
$incomingHeaders = get_request_headers();

// 过滤/转换头
$forwardHeaders = [];
foreach ($incomingHeaders as $k => $v) {
	$lk = strtolower($k);
	if (in_array($lk, ['host', 'content-length'])) continue;
	// 如果上游启用了压缩，cURL 会自动处理；避免重复编码
	if ($lk === 'accept-encoding') continue;
	$forwardHeaders[] = $k . ': ' . $v;
}

// 构建 cURL 请求
$ch = curl_init($targetUrl);
curl_setopt_array($ch, [
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_HEADER => true,
	CURLOPT_FOLLOWLOCATION => false, // 避免跳转到内网
	CURLOPT_SSL_VERIFYPEER => false,
	CURLOPT_SSL_VERIFYHOST => false,
	CURLOPT_TIMEOUT => 60,
	CURLOPT_CONNECTTIMEOUT => 10,
]);

switch (strtoupper($method)) {
	case 'POST':
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
		break;
	case 'PUT':
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
		curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
		break;
	case 'PATCH':
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
		curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
		break;
	case 'DELETE':
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
		curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
		break;
	default:
		// GET/HEAD 等
		if (strtoupper($method) === 'HEAD') {
			curl_setopt($ch, CURLOPT_NOBODY, true);
		}
		break;
}

// 设置 UA：优先客户端，其次配置
if (!array_filter($forwardHeaders, function($h) { return stripos($h, 'User-Agent:') === 0; })) {
	$ua = $_SERVER['HTTP_USER_AGENT'] ?? ($Config['user_agent'] ?? 'Mozilla/5.0');
	$forwardHeaders[] = 'User-Agent: ' . (is_array($ua) ? reset($ua) : $ua);
}

if (!empty($forwardHeaders)) {
	curl_setopt($ch, CURLOPT_HTTPHEADER, $forwardHeaders);
}

$response = curl_exec($ch);
if ($response === false) {
	$err = curl_error($ch);
	curl_close($ch);
	send_response('转发失败：' . $err, 502);
}

$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$statusCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
$headerStr = substr($response, 0, $headerSize);
$respBody = substr($response, $headerSize);

curl_close($ch);

// 过滤并透传响应头
$respHeaders = [];
foreach (explode("\r\n", trim($headerStr)) as $line) {
	if ($line === '' || stripos($line, 'HTTP/') === 0) continue;
	[$hk, $hv] = array_map('trim', explode(':', $line, 2) + [1 => '']);
	$lk = strtolower($hk);
	if (in_array($lk, ['content-length', 'transfer-encoding', 'connection'])) continue;
	$respHeaders[$hk] = $hv;
}

// 记录访问日志（调试模式）
if (!empty($Config['debug_mode'])) {
	$time = date('Y-m-d H:i:s');
	$methodLog = $_SERVER['REQUEST_METHOD'] ?? 'GET';
	$userAgentLog = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
	$urlLog = rawurldecode($_SERVER['REQUEST_URI'] ?? 'unknown');
	$stmt = $db->prepare("INSERT INTO access_log (access_time, client_ip, method, url, user_agent, access_denied, deny_message) VALUES (?, ?, ?, ?, ?, ?, ?)");
	$stmt->execute([$time, getClientIp(), $methodLog, $urlLog, $userAgentLog, 0, null]);
}

send_response($respBody, $statusCode ?: 200, $respHeaders);
?>

