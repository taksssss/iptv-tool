<?php
/**
 * @file cron.php
 * @brief 定时任务脚本
 *
 * 该脚本用于在特定时间间隔内执行 update.php，以实现定时任务功能。
 *
 * 作者: Tak
 * GitHub: https://github.com/taksssss/EPG-Server
 */

// 引入公共脚本，初始化数据库
require_once 'public.php';
initialDB();

// 取消时间限制
set_time_limit(0);

// 设置脚本只能通过CLI运行
if (php_sapi_name() !== 'cli') {
    die("此脚本只能通过 CLI 运行");
}

// 日志记录函数
function logCronMessage($message) {
    global $db;
    try {
        $timestamp = date('Y-m-d H:i:s'); // 使用设定的时区时间
        $stmt = $db->prepare("INSERT INTO cron_log (timestamp, log_message) VALUES (:timestamp, :message)");
        $stmt->bindValue(':timestamp', $timestamp, PDO::PARAM_STR);
        $stmt->bindParam(':message', $message);
        $stmt->execute();
    } catch (PDOException $e) {
        die("日志记录失败: " . $e->getMessage());
    }
}

$currentPid = posix_getpid(); // 获取当前进程ID
$processName = 'cron.php';
$oldPids = [];
exec("pgrep -f '{$processName}'", $oldPids);

foreach ($oldPids as $pid) {
    if ($pid != $currentPid && posix_kill($pid, 0)) {
        if (posix_kill($pid, 9)) {
            logCronMessage("【终止旧进程】 {$pid}");
        } else {
            logCronMessage("无法终止旧的进程 {$pid}");
        }
    }
}

// 从配置中获取间隔时间，如果不存在则默认为0
$interval_time = $Config['interval_time'] ?? 0;

// 如果间隔时间为0，则不执行
if ($interval_time == 0) {
    logCronMessage("【取消定时任务】间隔时间设置为0。");
    exit;
}

// 检查配置中是否存在首次执行时间和结束时间
if (!isset($Config['start_time']) || !isset($Config['end_time'])) {
    logCronMessage("不存在首次执行时间或结束时间。退出...");
    exit;
}

// 启动事务
$db->beginTransaction();

// 从配置中获取首次执行时间和结束时间
$start_time = $Config['start_time'];
$end_time = $Config['end_time'];

list($first_run_hour, $first_run_minute) = explode(':', $start_time);
list($end_hour, $end_minute) = explode(':', $end_time);

// 获取当前时间
$current_time = time();

// 计算今天的首次执行时间
$first_run_today = strtotime(date('Y-m-d') . " $first_run_hour:$first_run_minute:00");

// 计算明天的结束时间或今天的结束时间
if ($end_hour > $first_run_hour || ($end_hour == $first_run_hour && $end_minute > $first_run_minute)) {
    // 结束时间在今天
    $end_time_today = strtotime(date('Y-m-d') . " $end_hour:$end_minute:00");
} else {
    // 结束时间在明天
    $end_time_today = strtotime(date('Y-m-d', strtotime('+1 day')) . " $end_hour:$end_minute:00");
}

// 如果当前时间已经超过结束时间，则下次执行时间为明天的首次执行时间
if ($current_time >= $end_time_today) {
    $next_execution_time = $first_run_today + 24 * 3600; // 加一天
} else {
    // 计算从今天首次执行时间开始的下一个执行时间
    $next_execution_time = $first_run_today + ceil(($current_time - $first_run_today) / $interval_time) * $interval_time;
}

// 如果跨越了结束时间，则设置为明天的首次执行时间
if ($next_execution_time >= $end_time_today) {
    $first_run_today += 24 * 3600; // 更新明天的开始时间
    $end_time_today += 24 * 3600; // 更新明天的结束时间
    $next_execution_time = $first_run_today; // 重置为明天首次执行时间
}

// 计算距离下一个执行时间的秒数
$initial_sleep = $next_execution_time - $current_time;

// 汇总所有日志信息
logCronMessage("【开始时间】 " . date('H:i', $first_run_today));
logCronMessage("【结束时间】 " . date('H:i', $end_time_today));
$logContent = "【间隔时间】 " . gmdate('H小时i分钟', $interval_time) . "\n";
$logContent .= "\t\t\t\t-------运行时间表-------\n";

// 循环输出每次执行的时间
$current_execution_time = $first_run_today;
while ($current_execution_time < $end_time_today) {
    $logContent .= "\t\t\t\t\t      " . date('H:i', $current_execution_time) . "\n";
    $current_execution_time += $interval_time;
}
$logContent .= "\t\t\t\t--------------------------";
logCronMessage($logContent);

logCronMessage("【下次执行】 " . date('m/d H:i', $next_execution_time));
logCronMessage("【等待时间】 " . gmdate('H小时i分钟', $initial_sleep));

// 提交事务
$db->commit();

// 首先等待到下一个执行时间
sleep($initial_sleep);

// 初始化计数器
$check_counter = 0;

// 无限循环，可以使用实际需求中的退出条件
while (true) {
    // 执行update.php
    exec('php ' . __DIR__ . '/update.php &');
    logCronMessage("【成功执行】 update.php (" . ++$check_counter . ")");

    // 判断是否同步测速校验
    $Config = json_decode(@file_get_contents(__DIR__ . '/data/config.json'), true);
    $check_interval_factor = $Config['check_speed_interval_factor'] ?? 1;
    if (($Config['check_speed_auto_sync'] ?? false) && ($check_counter % $check_interval_factor === 0)) {
        exec('php ' . __DIR__ . '/check.php backgroundMode=1 > /dev/null 2>/dev/null &');
        logCronMessage("【测速校验】 已在后台运行 (" . ($check_counter / $check_interval_factor) . ")");
    }

    // 计算下一个执行时间
    $current_time = time();
    $next_execution_time += $interval_time;

    // 如果跨越了结束时间，则设置为明天的首次执行时间
    if ($next_execution_time >= $end_time_today) {
        $first_run_today += 24 * 3600; // 更新明天的开始时间
        $end_time_today += 24 * 3600; // 更新明天的结束时间
        $next_execution_time = $first_run_today; // 重置为明天首次执行时间
    }

    // 计算等待时间
    $sleep_time = $next_execution_time - $current_time;

    // 记录下次执行时间
    logCronMessage("【下次执行】 " . date('m/d H:i', $next_execution_time));

    // 等待到下一个执行时间
    sleep($sleep_time);
}
?>
