<?php
header('Content-Type: application/json; charset=utf-8');

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
$DATA_FILE = __DIR__ . '/sources.json';

function get_int_param($name, $default = 1) {
    if (isset($_REQUEST[$name]) && is_numeric($_REQUEST[$name])) {
        $v = intval($_REQUEST[$name]);
        return $v > 0 ? $v : $default;
    }
    return $default;
}

$page = get_int_param('page', 1);
$pageSize = get_int_param('pageSize', 200);

if ($action === 'get_sources') {
    $all = read_all_sources($DATA_FILE);
    $total = count($all);
    $offset = ($page - 1) * $pageSize;
    $items = array_slice($all, $offset, $pageSize);
    echo json_encode(['success' => true, 'totalItems' => $total, 'page' => $page, 'pageSize' => $pageSize, 'items' => $items]);
    exit;
} elseif ($action === 'save_sources') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data) || !isset($data['items']) || !is_array($data['items'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '请求体应为 JSON，包含 items 数组']);
        exit;
    }
    $items = $data['items'];
    $result = save_page_to_sources($DATA_FILE, $page, $pageSize, $items);
    if ($result['success']) {
        echo json_encode(['success' => true, 'message' => '保存成功', 'written' => $result['written']]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $result['message']]);
    }
    exit;
} else {
    echo json_encode(['success' => false, 'message' => '未知 action']);
    exit;
}

function read_all_sources($file) {
    if (!file_exists($file)) return [];
    $raw = file_get_contents($file);
    $arr = json_decode($raw, true);
    if (!is_array($arr)) return [];
    return $arr;
}

function save_page_to_sources($file, $page, $pageSize, $items) {
    $dir = dirname($file);
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true)) {
            return ['success'=>false, 'message'=>'无法创建目录'];
        }
    }

    $maxRetries = 3;
    $attempt = 0;
    while ($attempt < $maxRetries) {
        $attempt++;
        $fp = fopen($file, 'c+');
        if (!$fp) {
            return ['success'=>false, 'message'=>'无法打开数据文件'];
        }
        if (!flock($fp, LOCK_EX)) {
            fclose($fp);
            if ($attempt >= $maxRetries) return ['success'=>false, 'message'=>'获取文件锁失败'];
            usleep(100000);
            continue;
        }

        clearstatcache(true, $file);
        $stat = fstat($fp);
        $size = $stat['size'];
        $all = [];
        if ($size > 0) {
            rewind($fp);
            $raw = fread($fp, $size);
            $all = json_decode($raw, true);
            if (!is_array($all)) $all = [];
        }

        $offset = ($page - 1) * $pageSize;
        $needed = $offset + count($items);
        if (count($all) < $needed) {
            $all = array_pad($all, $needed, null);
        }

        for ($i = 0; $i < count($items); $i++) {
            $all[$offset + $i] = $items[$i];
        }

        rewind($fp);
        if (!ftruncate($fp, 0)) {
            flock($fp, LOCK_UN);
            fclose($fp);
            return ['success'=>false, 'message'=>'文件截断失败'];
        }
        $newRaw = json_encode($all, JSON_UNESCAPED_UNICODE);
        $writeBytes = fwrite($fp, $newRaw);
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);

        if ($writeBytes === false) {
            if ($attempt >= $maxRetries) {
                return ['success'=>false, 'message'=>'写入失败'];
            } else {
                usleep(100000);
                continue;
            }
        }

        return ['success'=>true, 'written'=>count($items)];
    }

    return ['success'=>false, 'message'=>'重试次数用尽'];
}
?>
