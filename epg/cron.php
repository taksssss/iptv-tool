<?php
/**
 * @file cron.php
 * @brief 定时任务脚本
 *
 * 该脚本用于在特定时间间隔内执行 update.php，以实现定时任务功能。
 *
 * 作者: Tak
 * GitHub: https://github.com/taksssss/iptv-tool
 */

require_once 'public.php';
initialDB();
set_time_limit(0);

if (php_sapi_name() !== 'cli') die("此脚本只能通过 CLI 运行");

function logCronMessage($message) {
    global $db;
    try {
        $stmt = $db->prepare("INSERT INTO cron_log (timestamp, log_message) VALUES (:timestamp, :message)");
        $stmt->bindValue(':timestamp', date('Y-m-d H:i:s'));
        $stmt->bindParam(':message', $message);
        $stmt->execute();
    } catch (PDOException $e) {
        die("日志记录失败: " . $e->getMessage());
    }
}

// cron 字段匹配（支持 *、数字、范围、步长、列表）
function matchCronField($field, $value, $min, $max) {
    if ($field === '*') return true;
    foreach (explode(',', $field) as $part) {
        $part = trim($part);
        $step = 1;
        if (strpos($part, '/') !== false) {
            [$range, $stepStr] = explode('/', $part, 2);
            $step = max(1, intval($stepStr));
            $part = $range;
        }
        if ($part === '*') {
            for ($v = $min; $v <= $max; $v += $step) {
                if ($v === $value) return true;
            }
            continue;
        }
        if (strpos($part, '-') !== false) {
            [$from, $to] = explode('-', $part, 2);
            for ($v = intval($from); $v <= intval($to); $v += $step) {
                if ($v === $value) return true;
            }
            continue;
        }
        $num = intval($part);
        if ($step === 1) {
            if ($num === $value) return true;
            continue;
        }
        for ($v = $num; $v <= $max; $v += $step) {
            if ($v === $value) return true;
        }
    }
    return false;
}

// cron 表达式匹配（5字段：分 时 日 月 周）
function matchCronExpression($expr, $min, $hour, $dom, $mon, $dow) {
    $parts = preg_split('/\s+/', trim($expr));
    if (count($parts) !== 5) return false;
    [$mF, $hF, $domF, $monF, $dowF] = $parts;
    return matchCronField($mF, $min, 0, 59) &&
           matchCronField($hF, $hour, 0, 23) &&
           matchCronField($domF, $dom, 1, 31) &&
           matchCronField($monF, $mon, 1, 12) &&
           matchCronField($dowF, $dow % 7, 0, 6);
}

// 防止多进程同时运行
$currentPid = posix_getpid();
$processName = 'cron.php';
$oldPids = [];
exec("pgrep -f '{$processName}'", $oldPids);
foreach ($oldPids as $pid) {
    if ($pid != $currentPid && posix_kill($pid, 0)) {
        if (posix_kill($pid, 9)) {
            logCronMessage("【终止旧进程】 {$pid}");
        } else {
            logCronMessage("【无法终止旧进程】 {$pid}");
        }
    }
}

// 加载配置
$interval_time  = $Config['interval_time'] ?? 0;
$start_time     = $Config['start_time'] ?? null;
$end_time       = $Config['end_time'] ?? null;
$cron_task_type = $Config['cron_task_type'] ?? 0;
$cron_exprs_str = $Config['cron_expressions'] ?? '';

if ($cron_task_type === 1) {
    // === cron 模式 ===
    $cron_exprs = array_values(array_filter(array_map('trim', explode('|', $cron_exprs_str))));
    if (empty($cron_exprs)) {
        logCronMessage("【取消定时任务】cron 模式：cron 表达式为空。");
        exit;
    }

    $logContent = "【cron 模式】\n\t\t\t\t-------cron 表达式-------\n";
    foreach ($cron_exprs as $expr) $logContent .= "\t\t\t\t\t      " . $expr . "\n";
    $logContent .= "\t\t\t\t--------------------------";
    logCronMessage($logContent);

    $check_counter = 0;
    while (true) {
        $now_min  = intval(date('i'));
        $now_hour = intval(date('G'));
        $now_dom  = intval(date('j'));
        $now_mon  = intval(date('n'));
        $now_dow  = intval(date('w')); // 0=Sunday

        foreach ($cron_exprs as $expr) {
            if (matchCronExpression($expr, $now_min, $now_hour, $now_dom, $now_mon, $now_dow)) {
                exec('php ' . __DIR__ . '/update.php &');
                logCronMessage("【成功执行】 update.php (" . ++$check_counter . ") [cron: $expr]");

                // 同步测速校验
                $Config = json_decode(@file_get_contents(__DIR__ . '/data/config.json'), true);
                $check_interval_factor = $Config['check_speed_interval_factor'] ?? 1;
                if (($Config['check_speed_auto_sync'] ?? false) && ($check_counter % $check_interval_factor === 0)) {
                    exec('php ' . __DIR__ . '/check.php backgroundMode=1 > /dev/null 2>/dev/null &');
                    logCronMessage("【测速校验】 已在后台运行 (" . ($check_counter / $check_interval_factor) . ")");
                }

                break; // 防止同一分钟多个表达式重复执行
            }
        }

        sleep(60 - date('s')); // 每分钟整点检查
    }
}

// === 默认模式 ===
if ($interval_time == 0 || !$start_time || !$end_time) {
    logCronMessage("【取消定时任务】配置不完整或间隔时间为0。");
    exit;
}

list($start_hour, $start_minute) = explode(':', $start_time);
list($end_hour, $end_minute)     = explode(':', $end_time);

// 生成执行时间表，只存小时和分钟
$execution_times = [];
$start_sec = $start_hour*3600 + $start_minute*60;
$end_sec   = $end_hour*3600 + $end_minute*60;
if ($end_sec <= $start_sec) $end_sec += 24*3600; // 跨天

for ($t = $start_sec; $t <= $end_sec; $t += $interval_time) {
    $execution_times[] = [
        'h' => floor(($t/3600)%24),
        'm' => floor(($t%3600)/60)
    ];
}

// 输出执行时间表日志
logCronMessage("【开始时间】 " . $start_time);
logCronMessage("【结束时间】 " . $end_time);
$logContent = "【间隔时间】 " . gmdate('H小时i分钟', $interval_time) . "\n";
$logContent .= "\t\t\t\t-------运行时间表-------\n";
foreach ($execution_times as $t) $logContent .= "\t\t\t\t\t      " . sprintf('%02d:%02d', $t['h'], $t['m']) . "\n";
$logContent .= "\t\t\t\t--------------------------";
logCronMessage($logContent);

// 先排序 execution_times（按秒数）
usort($execution_times, function($a, $b) {
    return ($a['h']*3600 + $a['m']*60) - ($b['h']*3600 + $b['m']*60);
});

// 计算下次执行时间
$now_sec = date('G')*3600 + date('i')*60;
foreach ($execution_times as $t) {
    if ($t['h']*3600 + $t['m']*60 > $now_sec) {
        $next_execution_time = $t;
        $next_date = date('m/d'); // 今天
        break;
    }
}
if (!isset($next_execution_time)) { // 没找到就用第一个（明天执行）
    $next_execution_time = $execution_times[0];
    $next_date = date('m/d', strtotime('+1 day'));
}
logCronMessage("【下次执行】 " . $next_date . ' ' . sprintf('%02d:%02d', $next_execution_time['h'], $next_execution_time['m']));

// 无限循环，每分钟检查
while (true) {
    $now_sec = date('G')*3600 + date('i')*60;

    foreach ($execution_times as $idx => $t) {
        $t_sec = $t['h']*3600 + $t['m']*60;
        if ($now_sec >= $t_sec && $now_sec < $t_sec + 60) { // 踩点
            exec('php ' . __DIR__ . '/update.php &');
            static $check_counter = 0;
            logCronMessage("【成功执行】 update.php (" . ++$check_counter . ")");

            // 下次执行时间
            $next_idx = ($idx + 1) % count($execution_times);
            $next_execution_time = $execution_times[$next_idx];
            $next_date = ($next_idx <= $idx) ? date('m/d', strtotime('+1 day')) : date('m/d');
            logCronMessage("【下次执行】 " . $next_date . ' ' . sprintf('%02d:%02d', $next_execution_time['h'], $next_execution_time['m']));

            // 同步测速校验
            $Config = json_decode(@file_get_contents(__DIR__ . '/data/config.json'), true);
            $check_interval_factor = $Config['check_speed_interval_factor'] ?? 1;
            if (($Config['check_speed_auto_sync'] ?? false) && ($check_counter % $check_interval_factor === 0)) {
                exec('php ' . __DIR__ . '/check.php backgroundMode=1 > /dev/null 2>/dev/null &');
                logCronMessage("【测速校验】 已在后台运行 (" . ($check_counter / $check_interval_factor) . ")");
            }

            break; // 防止同一分钟重复执行
        }
    }

    sleep(60 - date('s')); // 每分钟整点检查
}
?>