<?php
/**
 * @file proxy.php
 * @brief 认证入口：/epg/proxy.php?token=xxx&url=ENCODED_URL
 *
 * 说明：
 * - 实际流量由 Apache mod_proxy 转发；此文件仅在认证失败时返回 403
 * - 仍保留基础参数检查，便于直接访问调试
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

// 可选：对 url 做基本检查，便于直接访问调试
$targetUrl = trim($query_params['url'] ?? '');
if ($targetUrl === '') {
	send_response('参数错误：缺少 url。', 400);
}

$parts = @parse_url($targetUrl);
if (!$parts || empty($parts['scheme']) || !in_array(strtolower($parts['scheme']), ['http', 'https'])) {
	send_response('参数错误：只允许 http/https。', 400);
}

// 当通过认证且参数有效时，这里不再转发，由 Apache 完成
send_response('OK', 200, ['Content-Type' => 'text/plain']);