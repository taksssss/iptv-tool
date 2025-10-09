<?php
/**
 * @file manage.php
 * @brief 管理页面部分
 *
 * 管理界面脚本，用于处理会话管理、密码更改、登录验证、配置更新、更新日志展示等功能。
 *
 * 作者: Tak
 * GitHub: https://github.com/taksssss/iptv-tool
 */

// 引入公共脚本，初始化数据库
require_once 'public.php';
initialDB();

session_start();

// 首次进入界面，检查 cron.php 是否运行正常
if ($Config['interval_time'] !== 0) {
    $output = [];
    exec("ps aux | grep '[c]ron.php'", $output);
    if(!$output) {
        exec('php cron.php > /dev/null 2>/dev/null &');
    }
}

// 简单随机字符串函数
function randStr($len = 10) {
    return substr(bin2hex(random_bytes($len)), 0, $len);
}

$needSave = false;

// 首次使用，提示修改密码
$forceChangePassword = empty($Config['manage_password']);

// 统一检查几个字段
foreach (['token', 'user_agent'] as $k) {
    if (empty($Config[$k])) {
        $Config[$k] = randStr();
        $needSave = true;
    }
}

if ($needSave) {
    file_put_contents(
        $configPath,
        json_encode($Config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );
}

// 处理密码更新请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $newPassword = md5($_POST['new_password']);

    // 如果不是强制设置密码，则验证原密码
    if (empty($forceChangePassword) && md5($_POST['old_password']) !== $Config['manage_password']) {
        $passwordChangeError = "原密码错误";
    } else {
        // 更新密码并写入配置
        $Config['manage_password'] = $newPassword;
        file_put_contents($configPath, json_encode($Config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $passwordChanged = true;
        $forceChangePassword = false;
    }
}

// 检查是否提交登录表单
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $password = md5($_POST['password']);

    // 验证密码
    if ($password === $Config['manage_password']) {
        // 密码正确，设置会话变量
        $_SESSION['loggedin'] = true;

        // 设置会话变量，表明用户可以访问 phpliteadmin.php 、 tinyfilemanager.php
        $_SESSION['can_access_phpliteadmin'] = true;
        $_SESSION['can_access_tinyfilemanager'] = true;
    } else {
        $error = "密码错误";
    }
}

// 处理密码更改成功后的提示
$passwordChangedMessage = isset($passwordChanged) ? "<p style='color:green;'>密码已更改</p>" : '';
$passwordChangeErrorMessage = isset($passwordChangeError) ? "<p style='color:red;'>$passwordChangeError</p>" : '';

// 检查是否已登录
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    // 显示登录表单
    include 'assets/html/login.html';
    exit;
}

// 更新配置
function updateConfigFields() {
    global $Config, $configPath;

    // 获取和过滤表单数据
    $config_keys = array_keys(array_filter($_POST, function($key) {
        return $key !== 'update_config';
    }, ARRAY_FILTER_USE_KEY));
    
    foreach ($config_keys as $key) {
        if ($key === 'target_time_zone') {
            ${$key} = ($_POST[$key] === '0') ? 0 : $_POST[$key];
        } else {
            ${$key} = is_numeric($_POST[$key]) ? intval($_POST[$key]) : $_POST[$key];
        }
    }

    // 处理 URL 列表和频道别名
    $xml_urls = array_values(array_map(function($url) {
        return preg_replace('/^#\s*(\S+)(\s*#.*)?$/', '# $1$2', trim(str_replace(["，", "：", "！"], [",", ":", "!"], $url)));
    }, explode("\n", $xml_urls)));
    
    $interval_time = $interval_hour * 3600 + $interval_minute * 60;
    $mysql = ["host" => $mysql_host, "dbname" => $mysql_dbname, "username" => $mysql_username, "password" => $mysql_password];

    // 解析频道别名
    $channel_mappings = [];
    if ($mappings = trim($_POST['channel_mappings'] ?? '')) {
        foreach (explode("\n", $mappings) as $line) {
            if ($line = trim($line)) {
                list($search, $replace) = preg_split('/=》|=>/', $line);
                $channel_mappings[trim($search)] = str_replace("，", ",", trim($replace));
            }
        }
    }

    // 解析频道 EPG 数据
    $channel_bind_epg = isset($_POST['channel_bind_epg']) ? array_filter(array_reduce(json_decode($_POST['channel_bind_epg'], true), function($result, $item) {
        $epgSrc = preg_replace('/^【已停用】/', '', $item['epg_src']);
        if (!empty($item['channels'])) $result[$epgSrc] = str_replace("，", ",", trim($item['channels']));
        return $result;
    }, [])) : $Config['channel_bind_epg'];

    // 更新 $Config
    $oldConfig = $Config;
    $config_keys_filtered = array_filter($config_keys, function($key) {
        return !preg_match('/^(mysql_|interval_)/', $key);
    });
    $config_keys_new = ['channel_bind_epg', 'interval_time', 'mysql'];
    $config_keys_save = array_merge($config_keys_filtered, $config_keys_new);

    foreach ($config_keys_save as $key) {
        if (isset($$key)) {
            $Config[$key] = $$key;
        }
    }

    // 检查 MySQL 有效性
    $db_type_set = true;
    if ($Config['db_type'] === 'mysql') {
        try {
            $dsn = "mysql:host={$Config['mysql']['host']};dbname={$Config['mysql']['dbname']};charset=utf8mb4";
            $db = new PDO($dsn, $Config['mysql']['username'] ?? null, $Config['mysql']['password'] ?? null);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            $Config['db_type'] = 'sqlite';
            $db_type_set = false;
        }
    }

    // 将新配置写回 config.json
    file_put_contents($configPath, json_encode($Config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    // 重新启动 cron.php ，设置新的定时任务
    if ($oldConfig['start_time'] !== $start_time || $oldConfig['end_time'] !== $end_time || $oldConfig['interval_time'] !== $interval_time) {
        exec('php cron.php > /dev/null 2>/dev/null &');
    }
    
    return ['db_type_set' => $db_type_set];
}

// 处理服务器请求
try {
    $requestMethod = $_SERVER['REQUEST_METHOD'];
    $dbResponse = null;

    if ($requestMethod === 'GET') {

        // 确定操作类型
        $action_map = [
            'get_config', 'get_env', 'get_update_logs', 'get_cron_logs', 'get_channel', 
            'get_epg_by_channel', 'get_icon', 'get_channel_bind_epg', 'get_channel_match', 'get_gen_list',
            'get_live_data', 'parse_source_info', 'download_source_data', 'delete_unused_icons', 
            'delete_source_config', 'delete_unused_live_data', 'get_version_log', 'get_readme_content', 
            'get_access_log', 'get_access_stats', 'clear_access_log', 'get_ip_list', 'test_redis'
        ];
        $action = key(array_intersect_key($_GET, array_flip($action_map))) ?: '';

        // 根据操作类型执行不同的逻辑
        switch ($action) {
            case 'get_config':
                // 获取配置信息
                $dbResponse = $Config;
                
                // 同时返回 MD5 token
                if (isset($dbResponse['token'])) {
                    $dbResponse['token_md5'] = substr(md5($dbResponse['token']), 0, 8);
                }
                break;

            case 'get_env':
                // 获取 serverUrl、redirect
                $redirect = false;
                $testUrl = 'http://127.0.0.1/tv.m3u';
                $context = stream_context_create(['http' => ['method' => 'HEAD']]);
                $headers = @get_headers($testUrl, 1, $context);
                if ($headers && strpos($headers[0], '404') === false) {
                    $redirect = true;
                }
                $dbResponse = ['server_url' => $serverUrl, 'redirect' => $redirect];
                break;

            case 'get_update_logs':
                // 获取更新日志
                $dbResponse = $db->query("SELECT * FROM update_log")->fetchAll(PDO::FETCH_ASSOC);
                break;

            case 'get_cron_logs':
                // 获取 cron 日志
                $dbResponse = $db->query("SELECT * FROM cron_log")->fetchAll(PDO::FETCH_ASSOC);
                break;

            case 'get_channel':
                // 获取频道
                $channels = $db->query("SELECT DISTINCT channel FROM epg_data ORDER BY channel ASC")->fetchAll(PDO::FETCH_COLUMN);

                // 将频道忽略字符插入到频道列表的开头
                $channel_ignore_chars = [
                    ['original' => '【频道忽略字符】', 'mapped' => $Config['channel_ignore_chars'] ?? "&nbsp, -"]
                ];

                $channelMappings = $Config['channel_mappings'];
                $mappedChannels = $channel_ignore_chars;
                foreach ($channelMappings as $mapped => $original) {
                    if (($index = array_search(strtoupper($mapped), $channels)) !== false) {
                        $mappedChannels[] = [
                            'original' => $mapped,
                            'mapped' => $original
                        ];
                        unset($channels[$index]); // 从剩余频道中移除
                    }
                }
                foreach ($channels as $channel) {
                    $mappedChannels[] = [
                        'original' => $channel,
                        'mapped' => ''
                    ];
                }
                $dbResponse = [
                    'channels' => $mappedChannels,
                    'count' => count($mappedChannels)
                ];
                break;

            case 'get_epg_by_channel':
                // 查询
                $channel = $_GET['channel'];
                $date = urldecode($_GET['date']);
                $stmt = $db->prepare("SELECT epg_diyp FROM epg_data WHERE channel = :channel AND date = :date");
                $stmt->execute([':channel' => $channel, ':date' => $date]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC); // 获取单条结果
                if ($result) {
                    $epgData = json_decode($result['epg_diyp'], true);
                    $epgSource = $epgData['source'] ?? '';
                    $epgOutput = "";
                    foreach ($epgData['epg_data'] as $epgItem) {
                        $epgOutput .= "{$epgItem['start']} {$epgItem['title']}\n";
                    }            
                    $dbResponse = ['channel' => $channel, 'source' => $epgSource, 'date' => $date, 'epg' => trim($epgOutput)];
                } else {
                    $dbResponse = ['channel' => $channel, 'source' => '', 'date' => $date, 'epg' => '无节目信息'];
                }
                break;

            case 'get_icon':
                // 是否显示无节目单的内置台标
                if(isset($_GET['get_all_icon'])) {
                    $iconList = $iconListMerged;
                }
                
                // 获取并合并数据库中的频道和 $iconList 中的频道，去重后按字母排序
                $allChannels = array_unique(array_merge(
                    $db->query("SELECT DISTINCT channel FROM epg_data ORDER BY channel ASC")->fetchAll(PDO::FETCH_COLUMN),
                    array_keys($iconList)
                ));
                sort($allChannels);

                // 将默认台标插入到频道列表的开头
                $defaultIcon = [
                    ['channel' => '【默认台标】', 'icon' => $Config['default_icon'] ?? '']
                ];

                $channelsInfo = array_map(function($channel) use ($iconList) {
                    return ['channel' => $channel, 'icon' => $iconList[$channel] ?? ''];
                }, $allChannels);
                $withIcons = array_filter($channelsInfo, function($c) { return !empty($c['icon']);});
                $withoutIcons = array_filter($channelsInfo, function($c) { return empty($c['icon']);});

                $dbResponse = [
                    'channels' => array_merge($defaultIcon, $withIcons, $withoutIcons),
                    'count' => count($allChannels)
                ];
                break;

            case 'get_channel_bind_epg':
                // 获取频道绑定的 EPG
                $channels = $db->query("SELECT DISTINCT channel FROM epg_data ORDER BY channel ASC")->fetchAll(PDO::FETCH_COLUMN);
                $channelBindEpg = $Config['channel_bind_epg'] ?? [];
                $xmlUrls = $Config['xml_urls'];
                $dbResponse = array_map(function($epgSrc) use ($channelBindEpg) {
                    $cleanEpgSrc = trim(explode('#', strpos($epgSrc, '=>') !== false ? explode('=>', $epgSrc)[1] : ltrim($epgSrc, '# '))[0]);
                    $isInactive = strpos(trim($epgSrc), '#') === 0;
                    return [
                        'epg_src' => ($isInactive ? '【已停用】' : '') . $cleanEpgSrc,
                        'channels' => $channelBindEpg[$cleanEpgSrc] ?? ''
                    ];
                }, array_filter($xmlUrls, function($epgSrc) {
                    // 去除空行和包含 tvmao、cntv 的行
                    return !empty(ltrim($epgSrc, '# ')) && strpos($epgSrc, 'tvmao') === false && strpos($epgSrc, 'cntv') === false;
                }));
                $dbResponse = array_merge(
                    array_filter($dbResponse, function($item) { return strpos($item['epg_src'], '【已停用】') === false; }),
                    array_filter($dbResponse, function($item) { return strpos($item['epg_src'], '【已停用】') !== false; })
                );
                break;

            case 'get_channel_match':
                // 获取频道匹配
                $channels = $db->query("SELECT channel FROM gen_list")->fetchAll(PDO::FETCH_COLUMN);
                if (empty($channels)) {
                    echo json_encode(['ori_channels' => [], 'clean_channels' => [], 'match' => [], 'type' => []]);
                    exit;
                }
                $lines = implode("\n", array_map('cleanChannelName', $channels));
                $cleanChannels = explode("\n", ($Config['cht_to_chs'] ?? false) ? t2s($lines) : $lines);
                $epgData = $db->query("SELECT channel FROM epg_data")->fetchAll(PDO::FETCH_COLUMN);
                $channelMap = array_combine($cleanChannels, $channels);
                $matches = [];
                foreach ($cleanChannels as $cleanChannel) {
                    $originalChannel = $channelMap[$cleanChannel];
                    $matchResult = null;
                    $matchType = '未匹配';
                    if (in_array($cleanChannel, $epgData)) {
                        $matchResult = $cleanChannel;
                        $matchType = '精确匹配';
                        if ($cleanChannel !== $originalChannel) {
                            $matchType = '繁简/别名/忽略';
                        }
                    } else {
                        foreach ($epgData as $epgChannel) {
                            if (stripos($epgChannel, $cleanChannel) !== false) {
                                if (!isset($matchResult) || mb_strlen($epgChannel) < mb_strlen($matchResult)) {
                                    $matchResult = $epgChannel;
                                    $matchType = '正向模糊';
                                }
                            } elseif (stripos($cleanChannel, $epgChannel) !== false) {
                                if (!isset($matchResult) || mb_strlen($epgChannel) > mb_strlen($matchResult)) {
                                    $matchResult = $epgChannel;
                                    $matchType = '反向模糊';
                                }
                            }
                        }
                    }
                    $matches[$cleanChannel] = [
                        'ori_channel' => $originalChannel,
                        'clean_channel' => $cleanChannel,
                        'match' => $matchResult,
                        'type' => $matchType
                    ];
                }
                $dbResponse = $matches;
                break;

            case 'get_gen_list':
                // 获取生成列表
                $dbResponse = $db->query("SELECT channel FROM gen_list")->fetchAll(PDO::FETCH_COLUMN);
                break;
            
            case 'get_live_data':
                // 读取直播源文件内容
                if (isset($_GET['live_source_config'])) {
                    $Config['live_source_config'] = $_GET['live_source_config'];
                    file_put_contents($configPath, json_encode($Config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                }
                
                $sourceJsonPath = $liveDir . 'source.json';
                $templateJsonPath = $liveDir . 'template.json';
                
                if (!file_exists($sourceJsonPath)) {
                    $sourceTxtPath = $liveDir . 'source.txt';
                    $default = file_exists($sourceTxtPath)
                        ? array_values(array_filter(array_map('trim', file($sourceTxtPath))))
                        : [];
                
                    file_put_contents($sourceJsonPath, json_encode(['default' => $default], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                
                    if (file_exists($sourceTxtPath)) {
                        @unlink($sourceTxtPath);
                    }
                }
                
                if (!file_exists($templateJsonPath) && file_exists($templateTxtPath = $liveDir . 'template.txt')) {
                    file_put_contents($templateJsonPath, json_encode([
                        'default' => array_values(array_filter(array_map('trim', file($templateTxtPath))))
                    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                    @unlink($templateTxtPath);
                }
                
                $sourceJson = json_decode(@file_get_contents($sourceJsonPath), true) ?: [];
                $templateJson = json_decode(@file_get_contents($templateJsonPath), true) ?: [];
                $liveSourceConfig = $Config['live_source_config'] ?? 'default';
                $liveSourceConfig = isset($sourceJson[$liveSourceConfig]) ? $liveSourceConfig : 'default';
                $sourceContent = implode("\n", $sourceJson[$liveSourceConfig] ?? []);
                $templateContent = implode("\n", $templateJson[$liveSourceConfig] ?? []);

                // 生成配置下拉 HTML
                $configOptionsHtml = '';
                foreach ($sourceJson as $key => $_) {
                    $selected = ($key == $liveSourceConfig) ? 'selected' : '';
                    $label = htmlspecialchars($key);
                    $display = ($key === 'default') ? '默认' : $label;
                    $configOptionsHtml .= "<option value=\"$label\" $selected>$display</option>\n";
                }

                // 读取频道数据，并合并测速信息
                $stmt = $db->prepare("
                    SELECT 
                        c.*, 
                        REPLACE(ci.resolution, 'x', '<br>x<br>') AS resolution,
                        CASE WHEN " . ($is_sqlite ? "ci.speed GLOB '[0-9]*'" : "ci.speed REGEXP '^[0-9]+$'") . " 
                            THEN " . ($is_sqlite ? "ci.speed || '<br>ms'" : "CONCAT(ci.speed, '<br>ms')") . " 
                            ELSE ci.speed 
                        END AS speed
                    FROM channels c
                    LEFT JOIN channels_info ci ON c.streamUrl = ci.streamUrl
                    WHERE c.config = ?
                ");
                $stmt->execute([$liveSourceConfig]);
                $channelsData = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $dbResponse = [
                    'source_content' => $sourceContent,
                    'template_content' => $templateContent,
                    'channels' => $channelsData,
                    'config_options_html' => $configOptionsHtml,
                ];
                break;

            case 'parse_source_info':
                // 解析直播源
                $parseResult = doParseSourceInfo();
                if ($parseResult !== true) {
                    $dbResponse = ['success' => 'part', 'message' => $parseResult];
                } else {
                    $dbResponse = ['success' => 'full'];
                }
                break;

            case 'download_source_data':
                // 下载直播源数据
                $url = filter_var(($_GET['url']), FILTER_VALIDATE_URL);
                if ($url) {
                    $data = downloadData($url, '', 5);
                    if ($data !== false) {
                        $dbResponse = ['success' => true, 'data' => $data];
                    } else {
                        $dbResponse = ['success' => false, 'message' => '无法获取URL内容'];
                    }
                } else {
                    $dbResponse = ['success' => false, 'message' => '无效的URL'];
                }
                break;

            case 'delete_unused_icons':
                // 清理未在使用的台标
                $iconUrls = array_merge($iconList, [$Config["default_icon"]]);
                $iconPath = __DIR__ . '/data/icon';
                $deletedCount = 0;
                foreach (array_diff(scandir($iconPath), ['.', '..']) as $file) {
                    $iconRltPath = "/data/icon/$file";
                    if (!in_array($iconRltPath, $iconUrls) && @unlink("$iconPath/$file")) {
                        $deletedCount++;
                    }
                }
                $dbResponse = ['success' => true, 'message' => "共清理了 $deletedCount 个台标"];
                break;

            case 'delete_source_config':
                // 删除直播源配置
                $config = $_GET['live_source_config'];
                $db->prepare("DELETE FROM channels WHERE config = ?")->execute([$config]);
                foreach (['source', 'template'] as $file) {
                    $filePath = $liveDir . "{$file}.json";
                    if (file_exists($filePath)) {
                        $json = json_decode(file_get_contents($filePath), true);
                        if (isset($json[$config])) {
                            unset($json[$config]);
                            file_put_contents($filePath, json_encode($json, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                        }
                    }
                }
                $id = md5(urlencode($config));
                foreach (['m3u', 'txt'] as $ext) {
                    @unlink("$liveFileDir{$id}.{$ext}");
                }
                exit;

            case 'delete_unused_live_data':
                // 清理未在使用的直播源缓存、未出现在频道列表中的修改记录
                $sourceFilePath = $liveDir . 'source.json';
                $sourceJson = json_decode(@file_get_contents($sourceFilePath), true);
                $urls = [];
                foreach ((array)$sourceJson as $key => $list) {
                    if (is_array($list)) {
                        $urls = array_merge($urls, $list);
                    }
                    $urls[] = $key;
                }
            
                // 处理直播源 URL，去掉注释并清理格式
                $cleanUrls = array_map(function($url) {
                    return trim(explode('#', ltrim($url, '# '))[0]);
                }, $urls);
            
                // 删除未被使用的 /file 缓存文件
                $parentRltPath = '/' . basename(__DIR__) . '/data/live/file/';
                $deletedFileCount = 0;
                foreach (scandir($liveFileDir) as $file) {
                    if ($file === '.' || $file === '..') continue;
            
                    $fileRltPath = $parentRltPath . $file;
                    $matched = false;
                    foreach ($cleanUrls as $url) {
                        if (!$url) continue;
                        $urlMd5 = md5(urlencode($url));
                        if (stripos($fileRltPath, $url) !== false || stripos($fileRltPath, $urlMd5) !== false) {
                            $matched = true;
                            break;
                        }
                    }
                    if (!$matched && @unlink($liveFileDir . $file)) {
                        $deletedFileCount++;
                    }
                }
                @unlink($liveDir . 'tv.m3u');
                @unlink($liveDir . 'tv.txt');
            
                // 清除数据库中所有 channels.modified = 1 的记录（不分配置）
                $stmt = $db->prepare("UPDATE channels SET modified = 0 WHERE modified = 1");
                $stmt->execute();
            
                // 返回清理结果
                $dbResponse = [
                    'success' => true,
                    'message' => "共清理了 $deletedFileCount 个缓存文件。<br>已清除所有修改标记。<br>正在重新解析..."
                ];
                break;

            case 'get_version_log':
                // 获取更新日志
                $checkUpdateEnable = !isset($Config['check_update']) || $Config['check_update'] == 1;
                $checkUpdate = isset($_GET['do_check_update']) && $_GET['do_check_update'] === 'true';
                if (!$checkUpdateEnable && $checkUpdate) {
                    echo json_encode(['success' => true, 'is_updated' => false]);
                    return;
                }

                $localFile = 'data/CHANGELOG.md';
                $url = 'https://gitee.com/taksssss/iptv-tool/raw/main/CHANGELOG.md';
                $isUpdated = false;
                $updateMessage = '';
                if ($checkUpdate) {
                    $remoteContent = @file_get_contents($url);
                    if ($remoteContent === false) {
                        echo json_encode(['success' => false, 'message' => '无法获取远程版本日志']);
                        return;
                    }
                    $localContent = file_exists($localFile) ? file_get_contents($localFile) : '';
                    if (strtok($localContent, "\n") !== strtok($remoteContent, "\n")) {
                        file_put_contents($localFile, $remoteContent);
                        $isUpdated = !empty($localContent) ? true : false;
                        $updateMessage = '<h3 style="color: red;">🔔 检测到新版本，请自行更新。（该提醒仅显示一次）</h3>';
                    }
                }

                $markdownContent = file_exists($localFile) ? file_get_contents($localFile) : false;
                if ($markdownContent === false) {
                    echo json_encode(['success' => false, 'message' => '无法读取版本日志']);
                    return;
                }

                require_once 'assets/Parsedown.php';
                $htmlContent = (new Parsedown())->text($markdownContent);
                $dbResponse = ['success' => true, 'content' => $updateMessage . $htmlContent, 'is_updated' => $isUpdated];
                break;

            case 'get_readme_content':
                $readmeFile = 'assets/html/readme.md';
                $readmeContent = file_exists($readmeFile) ? file_get_contents($readmeFile) : '';
                require_once 'assets/Parsedown.php';
                $htmlContent = (new Parsedown())->text($readmeContent);
                $dbResponse = ['success' => true, 'content' => $htmlContent];
                break;

            case 'get_access_log':
                $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
            
                $stmt = $db->prepare("SELECT * FROM access_log WHERE id > ? ORDER BY id ASC");
                $stmt->execute([$offset]);
            
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                if (!$rows) {
                    $dbResponse = ['success' => true, 'changed' => false, 'offset' => $offset];
                    break;
                }
            
                $content = '';
                $lastId = $offset;
                foreach ($rows as $row) {
                    $content .= "[{$row['access_time']}] [{$row['client_ip']}] "
                        . ($row['access_denied'] ? "{$row['deny_message']} " : '')
                        . "[{$row['method']}] {$row['url']} | UA: {$row['user_agent']}\n";
                    $lastId = max($lastId, $row['id']);
                }
            
                $dbResponse = [ 'success' => true, 'changed' => true, 'content' => $content, 'offset' => $lastId ];
                break;

            case 'get_access_stats':
                $stmt = $db->query("
                    SELECT client_ip, DATE(access_time) AS date,
                            COUNT(*) AS total, SUM(access_denied) AS deny
                    FROM access_log
                    GROUP BY client_ip, date
                ");
                $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            
                $ipData = [];
                $dates = [];
            
                foreach ($rows as $r) {
                    $ip = $r['client_ip'];
                    $date = $r['date'];
                    $dates[$date] = true;
            
                    if (!isset($ipData[$ip])) {
                        $ipData[$ip] = ['ip' => $ip, 'counts' => [], 'total' => 0, 'deny' => 0];
                    }
            
                    $ipData[$ip]['counts'][$date] = (int)$r['total'];
                    $ipData[$ip]['total'] += (int)$r['total'];
                    $ipData[$ip]['deny'] += (int)$r['deny'];
                }
            
                $dates = array_keys($dates);
                sort($dates);
            
                foreach ($ipData as &$row) {
                    $counts = [];
                    foreach ($dates as $d) {
                        $counts[] = isset($row['counts'][$d]) ? $row['counts'][$d] : 0;
                    }
                    $row['counts'] = $counts;
                }
            
                $dbResponse = ['success' => true, 'ipData' => array_values($ipData), 'dates' => $dates];
                break;
                
            case 'clear_access_log':
                $res = $db->exec("DELETE FROM access_log") !== false;
                $dbResponse = ['success' => $res];
                break;

            case 'get_ip_list':
                $filename = basename($_GET['file'] ?? 'ipBlackList.txt'); // 只允许基本文件名
                $file_path = __DIR__ . "/data/{$filename}";
            
                if (file_exists($file_path)) {
                    $content = file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                    $dbResponse = ['success' => true, 'list' => $content];
                } else {
                    $dbResponse = ['success' => true, 'list' => []];
                }
                break;

            case 'test_redis':
                $redisConfig = $Config['redis'] ?? [];
                try {
                    $redis = new Redis();
                    $redis->connect($redisConfig['host'] ?: '127.0.0.1', $redisConfig['port'] ? (int)$redisConfig['port'] : 6379);
                    if (!empty($redisConfig['password'])) {
                        $redis->auth($redisConfig['password']);
                    }
                    if ($redis->ping()) {
                        $Config['cached_type'] = 'redis';
                        file_put_contents($configPath, json_encode($Config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                        $dbResponse = ['success' => true];
                    } else {
                        $dbResponse = ['success' => false];
                    }
                } catch (Exception $e) {
                    $dbResponse = ['success' => false];
                }
                break;

            default:
                $dbResponse = null;
                break;
        }

        if ($dbResponse !== null) {
            header('Content-Type: application/json; charset=utf-8');
            $json = json_encode($dbResponse);
            if ($json === false) { // 如果失败，尝试修复编码再输出
                $dbResponse = mb_convert_encoding($dbResponse, 'UTF-8', 'UTF-8');
                $json = json_encode($dbResponse);
            }
            echo $json;
            exit;
        }
    }

    // 处理 POST 请求
    if ($requestMethod === 'POST') {
        // 定义操作类型和对应的条件
        $actions = [
            'update_config' => isset($_POST['update_config']),
            'set_gen_list' => isset($_GET['set_gen_list']),
            'import_config' => isset($_POST['importExport']) && !empty($_FILES['importFile']['tmp_name']),
            'export_config' => isset($_POST['importExport']) && empty($_FILES['importFile']['tmp_name']),
            'upload_icon' => isset($_FILES['iconFile']),
            'update_icon_list' => isset($_POST['update_icon_list']),
            'upload_source_file' => isset($_FILES['liveSourceFile']),
            'save_content_to_file' => isset($_POST['save_content_to_file']),
            'save_source_info' => isset($_POST['save_source_info']),
            'update_config_field' => isset($_POST['update_config_field']),
            'create_source_config' => isset($_POST['create_source_config']),
        ];

        // 确定操作类型
        $action = '';
        foreach ($actions as $key => $condition) {
            if ($condition) { $action = $key; break; }
        }

        switch ($action) {
            case 'update_config':
                // 更新配置
                ['db_type_set' => $db_type_set] = updateConfigFields();
                echo json_encode([
                    'db_type_set' => $db_type_set,
                    'interval_time' => $Config['interval_time'],
                    'start_time' => $Config['start_time'],
                    'end_time' => $Config['end_time']
                ]);
                exit;

            case 'set_gen_list':
                // 设置生成列表
                $data = json_decode(file_get_contents("php://input"), true)['data'] ?? '';
                try {
                    $db->beginTransaction();
                    $db->exec("DELETE FROM gen_list");
                    $lines = array_filter(array_map('trim', explode("\n", $data)));
                    foreach ($lines as $line) {
                        $stmt = $db->prepare("INSERT INTO gen_list (channel) VALUES (:channel)");
                        $stmt->bindValue(':channel', $line, PDO::PARAM_STR);
                        $stmt->execute();
                    }
                    $db->commit();
                    echo 'success';
                } catch (PDOException $e) {
                    $db->rollBack();
                    echo "数据库操作失败: " . $e->getMessage();
                }
                exit;

            case 'import_config':
                // 导入配置
                $zip = new ZipArchive();
                $importFile = $_FILES['importFile']['tmp_name'];
                $successFlag = false;
                $message = "";
                if ($zip->open($importFile) === TRUE) {
                    if ($zip->extractTo('.')) {
                        $successFlag = true;
                        $message = "导入成功！<br>3秒后自动刷新页面……";
                    } else {
                        $message = "导入失败！解压过程中发生问题。";
                    }
                    $zip->close();
                } else {
                    $message = "导入失败！无法打开压缩文件。";
                }
                echo json_encode(['success' => $successFlag, 'message' => $message]);
                exit;

            case 'export_config':
                $zip = new ZipArchive();
                $zipFileName = 't.gz';
                if ($zip->open($zipFileName, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
                    $dataDir = __DIR__ . '/data';
                    $files = new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator($dataDir),
                        RecursiveIteratorIterator::LEAVES_ONLY
                    );
                    foreach ($files as $file) {
                        if (!$file->isDir()) {
                            $filePath = $file->getRealPath();
                            $relativePath = 'data/' . substr($filePath, strlen($dataDir) + 1);
                            $zip->addFile($filePath, $relativePath);
                        }
                    }
                    $zip->close();
                    header('Content-Type: application/zip');
                    header('Content-Disposition: attachment; filename=' . $zipFileName);
                    readfile($zipFileName);
                    unlink($zipFileName);
                }
                exit;

            case 'upload_icon':
                // 上传图标
                $file = $_FILES['iconFile'];
                $fileName = $file['name'];
                $uploadFile = $iconDir . $fileName;
                if ($file['type'] === 'image/png' && move_uploaded_file($file['tmp_name'], $uploadFile)) {
                    $iconUrl = '/data/icon/' . basename($fileName);
                    echo json_encode(['success' => true, 'iconUrl' => $iconUrl]);
                } else {
                    echo json_encode(['success' => false, 'message' => '文件上传失败']);
                }
                exit;

            case 'update_icon_list':
                // 更新图标
                $iconList = [];
                $updatedIcons = json_decode($_POST['updatedIcons'], true);
                
                // 遍历更新数据
                foreach ($updatedIcons as $channelData) {
                    $channelName = strtoupper(trim($channelData['channel']));
                    if ($channelName === '【默认台标】') {
                        // 保存默认台标到 config.json
                        $Config['default_icon'] = $channelData['icon'] ?? '';
                        file_put_contents($configPath, json_encode($Config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    } else {
                        // 处理普通台标数据
                        $iconList[$channelName] = $channelData['icon'];
                    }
                }

                // 过滤掉图标值为空和频道名为空的条目
                $iconList = array_filter($iconList, function($icon, $channel) {
                    return !empty($icon) && !empty($channel);
                }, ARRAY_FILTER_USE_BOTH);

                if (file_put_contents($iconListPath, json_encode($iconList, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) === false) {
                    echo json_encode(['success' => false, 'message' => '更新 iconList.json 时发生错误']);
                } else {
                    echo json_encode(['success' => true]);
                }

                // 清理缓存数据
                $cached_type = $Config['cached_type'] ?? 'memcached';
                if ($cached_type === 'memcached' && class_exists('Memcached') && ($memcached = new Memcached())->addServer('127.0.0.1', 11211)) {
                    $memcached->flush();
                } elseif ($cached_type === 'redis' && class_exists('Redis') && ($redis = new Redis()) && $redis->connect($Config['redis']['host'], $Config['redis']['port']) 
                    && (empty($Config['redis']['password']) || $redis->auth($Config['redis']['password'])) && $redis->ping()) {
                    $redis->flushAll();
                }
                exit;

            case 'upload_source_file':
                // 上传直播源文件
                $file = $_FILES['liveSourceFile'];
                $uploadFile = $liveFileDir . $file['name'];
            
                if (move_uploaded_file($file['tmp_name'], $uploadFile)) {
                    $liveSourceUrl = '/data/live/file/' . basename($file['name']);
                    $sourceFilePath = $liveDir . 'source.json';
            
                    $data = [];
                    if (file_exists($sourceFilePath)) {
                        $data = json_decode(file_get_contents($sourceFilePath), true) ?: [];
                    }
                    
                    $liveSourceConfig = $Config['live_source_config'] ?? 'default';
                    if (!isset($data[$liveSourceConfig])) $data[$liveSourceConfig] = [];
                    if (!in_array($liveSourceUrl, $data[$liveSourceConfig])) {
                        $data[$liveSourceConfig][] = $liveSourceUrl;
                    }
            
                    $ok = file_put_contents($sourceFilePath, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                    echo json_encode(['success' => $ok !== false]);
                } else {
                    echo json_encode(['success' => false, 'message' => '文件上传失败']);
                }
                exit;

            case 'save_content_to_file':
                // 保存内容到文件
                $filePath = __DIR__ . ($_POST['file_path'] ?? '');
                $content = $_POST['content'] ?? '';
            
                if (substr($filePath, -5) === '.json') {
                    $newData = json_decode($content, true);
                    if (!is_array($newData)) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'msg' => 'JSON格式错误']);
                        exit;
                    }
                    $oldData = file_exists($filePath) ? json_decode(file_get_contents($filePath), true) : [];
                    $oldData = is_array($oldData) ? $oldData : [];
                    $merged = array_replace($oldData, $newData);
                    $ok = file_put_contents($filePath, json_encode($merged, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                } else {
                    $ok = file_put_contents($filePath, str_replace('，', ',', $content));
                }
            
                echo json_encode(['success' => $ok !== false]);
                exit;
                
            case 'save_source_info':
                // 更新配置文件
                $Config['live_source_config'] = $_POST['live_source_config'];
                $Config['live_tvg_logo_enable'] = (int)$_POST['live_tvg_logo_enable'];
                $Config['live_tvg_id_enable'] = (int)$_POST['live_tvg_id_enable'];
                $Config['live_tvg_name_enable'] = (int)$_POST['live_tvg_name_enable'];
            
                if (file_put_contents($configPath, json_encode($Config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => '保存配置文件失败']);
                    exit;
                }
            
                // 保存直播源信息
                $content = json_decode($_POST['content'], true);
                generateLiveFiles($content, 'tv', $saveOnly = true); // 重新生成 M3U 和 TXT 文件
                echo json_encode(['success' => true]);
                exit;

            case 'update_config_field':
                // 更新单个字段
                foreach ($_POST as $key => $value) {
                    // 排除 update_config_field 字段
                    if ($key !== 'update_config_field') {
                        $Config[$key] = is_numeric($value) ? intval($value) : $value;
                    }
                }
                if (file_put_contents($configPath, json_encode($Config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false) {
                    echo json_encode(['success' => $Config]);
                } else {
                    http_response_code(500);
                    echo '保存失败';
                }
                exit;

            case 'create_source_config':
                // 新直播源配置
                $new = $_POST['new_source_config'];
                $old = $_POST['old_source_config'] ?? '';
                $isNew = $_POST['is_new'] === 'true';
                $paths = [
                    'source' => $liveDir . 'source.json',
                    'template' => $liveDir . 'template.json'
                ];
                foreach ($paths as $key => $path) {
                    $data = is_file($path) ? json_decode(file_get_contents($path), true) : [];
                    $data[$new] = $isNew ? [] : ($data[$old] ?? []);
                    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                }
                if (!$isNew) {
                    $stmt = $db->prepare("SELECT * FROM channels WHERE config = ?");
                    $stmt->execute([$old]);
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
                    if ($rows) {
                        $db->beginTransaction();
                        $insert = $db->prepare("INSERT INTO channels (
                            groupTitle, channelName, chsChannelName, streamUrl,
                            iconUrl, tvgId, tvgName, disable, modified,
                            source, tag, config
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        foreach ($rows as $r) {
                            $r['config'] = $new;
                            $insert->execute(array_values($r));
                        }
                        $db->commit();
                    }

                    $oldId = md5(urlencode($old));
                    $newId = md5(urlencode($new));
                    foreach (['m3u', 'txt'] as $ext) {
                        $src = "{$liveFileDir}{$oldId}.{$ext}";
                        $dst = "{$liveFileDir}{$newId}.{$ext}";
                        if (is_file($src)) {
                            copy($src, $dst);
                        }
                    }
                }
                $Config['live_source_config'] = $new;
                file_put_contents($configPath, json_encode($Config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                exit;            
        }
    }
} catch (Exception $e) {
    // 处理数据库连接错误
}

// 生成配置管理表单
include 'assets/html/manage.html';
?>