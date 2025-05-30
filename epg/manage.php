<?php
/**
 * @file manage.php
 * @brief 管理页面部分
 *
 * 管理界面脚本，用于处理会话管理、密码更改、登录验证、配置更新、更新日志展示等功能。
 *
 * 作者: Tak
 * GitHub: https://github.com/taksssss/EPG-Server
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

// 过渡到新的 md5 密码并生成默认 token、user_agent （如果不存在或为空）
if (!preg_match('/^[a-f0-9]{32}$/i', $Config['manage_password']) || empty($Config['token']) || empty($Config['user_agent'])) {
    if (!preg_match('/^[a-f0-9]{32}$/i', $Config['manage_password'])) {
        $Config['manage_password'] = md5($Config['manage_password']);
    }
    if (empty($Config['token'])) {
        $Config['token'] = substr(bin2hex(random_bytes(5)), 0, 10);  // 生成 10 位随机字符串
    }
    if (empty($Config['user_agent'])) {
        $Config['user_agent'] = substr(bin2hex(random_bytes(5)), 0, 10);  // 生成 10 位随机字符串
    }
    file_put_contents($configPath, json_encode($Config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// 处理密码更新请求
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $oldPassword = md5($_POST['old_password']);
    $newPassword = md5($_POST['new_password']);

    // 验证原密码是否正确
    if ($oldPassword === $Config['manage_password']) {
        // 原密码正确，更新配置中的密码
        $Config['manage_password'] = $newPassword;

        // 将新配置写回 config.json
        file_put_contents($configPath, json_encode($Config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // 设置密码更改成功的标志变量
        $passwordChanged = true;
    } else {
        $passwordChangeError = "原密码错误";
    }
}

// 检查是否提交登录表单
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
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
        ${$key} = is_numeric($_POST[$key]) ? intval($_POST[$key]) : $_POST[$key];
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
                $channel_mappings[trim($search)] = trim(str_replace("，", ",", trim($replace)), '[]');
            }
        }
    }

    // 解析频道 EPG 数据
    $channel_bind_epg = isset($_POST['channel_bind_epg']) ? array_filter(array_reduce(json_decode($_POST['channel_bind_epg'], true), function($result, $item) {
        $epgSrc = preg_replace('/^【已停用】/', '', $item['epg_src']);
        if (!empty($item['channels'])) $result[$epgSrc] = trim(str_replace("，", ",", trim($item['channels'])), '[]');
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

    if ($requestMethod == 'GET') {

        // 确定操作类型
        $action_map = [
            'get_update_logs', 'get_cron_logs', 'get_channel', 'get_epg_by_channel',
            'get_icon', 'get_channel_bind_epg', 'get_channel_match', 'get_gen_list',
            'get_live_data', 'parse_source_info', 'download_data', 'delete_unused_icons', 
            'delete_unused_live_data', 'get_version_log', 'get_readme_content', 'get_access_log',
            'clear_access_log'
        ];
        $action = key(array_intersect_key($_GET, array_flip($action_map))) ?: '';

        // 根据操作类型执行不同的逻辑
        switch ($action) {
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
                $channelMappings = $Config['channel_mappings'];
                $mappedChannels = [];
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
                $cleanChannels = explode("\n", t2s(implode("\n", array_map('cleanChannelName', $channels))));
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
                            $matchType = '别名/忽略';
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
                // 读取文件内容
                function readFileContent($filePath) {
                    return file_exists($filePath) ? file_get_contents($filePath) : '';
                }

                $sourceContent = readFileContent($liveDir . 'source.txt');
                $templateContent = readFileContent($liveDir . 'template.txt');

                // 读取 CSV 文件并返回关联数组
                function readCsvFile($filePath, $key = null) {
                    if (!file_exists($filePath)) return [];

                    $data = [];
                    if (($file = fopen($filePath, 'r')) !== false) {
                        $header = fgetcsv($file);
                        while (($row = fgetcsv($file)) !== false) {
                            if (empty(array_filter($row)) || count($row) !== count($header)) continue;

                            $rowData = array_combine($header, $row);
                            if ($key && isset($rowData[$key])) {
                                $data[$rowData[$key]] = $rowData; // 使用指定键映射
                            } else {
                                $data[] = $rowData;
                            }
                        }
                        fclose($file);
                    }
                    return $data;
                }

                $channelsInfo = readCsvFile($liveDir . 'channels_info.csv', 'tag');
                $channelsData = readCsvFile($liveDir . 'channels.csv');

                // 更新 channelsData 中的 resolution 和 speed
                foreach ($channelsData as &$row) {
                    if (isset($channelsInfo[$row['tag']])) {
                        $row['resolution'] = str_replace("x", "<br>x<br>", $channelsInfo[$row['tag']]['resolution']);
                        $row['speed'] = $channelsInfo[$row['tag']]['speed'];
                        if (is_numeric($row['speed'])) { $row['speed'] .= '<br>ms';}
                    }
                }
                
                $dbResponse = ['source_content' => $sourceContent, 'template_content' => $templateContent, 'channels' => $channelsData,];
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

            case 'download_data':
                // 下载数据
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

            case 'delete_unused_live_data':
                // 清理未在使用的直播源缓存、未出现在频道列表中的修改记录
                $sourceFilePath = $liveDir . 'source.txt';
                $sourceContent = file_exists($sourceFilePath) ? file_get_contents($sourceFilePath) : '';
                $urls = array_map('trim', explode("\n", $sourceContent));

                // 遍历 live/file 目录，删除未使用的文件
                $parentRltPath = '/' . basename(__DIR__) . '/data/live/file/'; // 相对路径
                $deletedFileCount = 0;
                foreach (scandir($liveFileDir) as $file) {
                    if ($file === '.' || $file === '..') continue;
                    $fileRltPath = $parentRltPath . $file;
                    if (!array_filter($urls, function($url) use ($fileRltPath) {
                        $url = trim(explode('#', ltrim($url, '# '))[0]); // 处理注释
                        $urlmd5 = md5(urlencode($url)); // 计算 md5
                        return $url && (stripos($fileRltPath, $url) !== false || stripos($fileRltPath, $urlmd5) !== false);
                    })) {
                        if (@unlink($liveFileDir . $file)) { // 如果没有匹配的 URL，删除文件
                            $deletedFileCount++;
                        }
                    }
                }

                // 删除 modifications.csv 文件
                @unlink($liveDir . 'modifications.csv');
                
                $dbResponse = ['success' => true, 'message' => "共清理了 $deletedFileCount 个缓存文件。<br>已删除所有修改记录，请重新解析。"];
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
                $url = 'https://gitee.com/taksssss/EPG-Server/raw/main/CHANGELOG.md';
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
                $accesslogFile = 'data/access.log';
                $accesslogContent = file_exists($accesslogFile) ? file_get_contents($accesslogFile) : '';
                $dbResponse = ['success' => true, 'content' => $accesslogContent];
                break;
                
            case 'clear_access_log':
                $file = 'data/access.log';
                $res = file_exists($file) && is_writable($file) && file_put_contents($file, '') !== false;
                $dbResponse = ['success' => $res];
                break;

            default:
                $dbResponse = null;
                break;
        }

        if ($dbResponse !== null) {
            header('Content-Type: application/json');
            echo json_encode($dbResponse);
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

                // 清理 memcached 数据，避免缓存
                if (class_exists('Memcached') && ($memcached = new Memcached())->addServer('localhost', 11211)) {
                    $memcached->flush();
                }
                exit;

            case 'upload_source_file':
                // 上传直播源文件
                $file = $_FILES['liveSourceFile'];
                $fileName = $file['name'];
                $uploadFile = $liveFileDir . $fileName;
                if (move_uploaded_file($file['tmp_name'], $uploadFile)) {
                    $liveSourceUrl = '/data/live/file/' . basename($fileName);
                    $sourceFilePath = $liveDir . 'source.txt';
                    $currentContent = file_get_contents($sourceFilePath);
                    if (!file_exists($sourceFilePath) || strpos($currentContent, $liveSourceUrl) === false) {
                        // 如果文件不存在或文件中没有该 URL，将其追加到文件末尾
                        $contentToAppend = trim($currentContent) ? PHP_EOL . $liveSourceUrl : $liveSourceUrl;
                        file_put_contents($sourceFilePath, $contentToAppend, FILE_APPEND);
                    }
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'message' => '文件上传失败']);
                }
                exit;

            case 'save_content_to_file':
                // 保存内容到文件
                $filePath = __DIR__ . $_POST['file_path'] ?? '';
                $content = $_POST['content'] ?? '';
                if (file_put_contents($filePath, str_replace("，", ",", $content)) !== false) {
                    echo json_encode(['success' => true]);
                } else {
                    http_response_code(500);
                    echo json_encode(['success' => false]);
                }
                exit;
                
            case 'save_source_info':
                // 更新配置文件
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
                if (empty($content)) {
                    echo json_encode(['success' => false, 'message' => '无效的数据']);
                    exit;
                }
            
                $fileName = $_POST['file_path'] ? 'file/' . md5(urlencode($_POST['file_path'])) : 'tv';
                generateLiveFiles($content, $fileName, $saveOnly = true); // 重新生成 M3U 和 TXT 文件
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
        }
    }
} catch (Exception $e) {
    // 处理数据库连接错误
}

// 生成配置管理表单
include 'assets/html/manage.html';
?>