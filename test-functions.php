<?php
// Standalone test - copy functions from manage.php to test independently

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

// Run tests
echo "=== Testing save_page_to_sources ===\n\n";

$testFile = '/tmp/test-sources.json';
if (file_exists($testFile)) unlink($testFile);

echo "Test 1: Save page 1 (3 items)\n";
$r = save_page_to_sources($testFile, 1, 3, ['A', 'B', 'C']);
echo "Result: " . json_encode($r) . "\n";
echo "Content: " . file_get_contents($testFile) . "\n\n";

echo "Test 2: Save page 2 (2 items)\n";
$r = save_page_to_sources($testFile, 2, 3, ['D', 'E']);
echo "Result: " . json_encode($r) . "\n";
echo "Content: " . file_get_contents($testFile) . "\n\n";

echo "Test 3: Read all sources\n";
$all = read_all_sources($testFile);
echo "Count: " . count($all) . "\n";
echo "Data: " . json_encode($all) . "\n\n";

echo "Test 4: Update page 1\n";
$r = save_page_to_sources($testFile, 1, 3, ['X', 'Y', 'Z']);
echo "Result: " . json_encode($r) . "\n";
echo "Content: " . file_get_contents($testFile) . "\n\n";

echo "Test 5: Mixed string and object data\n";
$mixed = [
    'String entry',
    ['channel' => 'CCTV1', 'url' => 'http://test.com'],
    'Another string'
];
$r = save_page_to_sources($testFile, 1, 3, $mixed);
echo "Result: " . json_encode($r) . "\n";
$content = file_get_contents($testFile);
echo "Content: " . $content . "\n";
echo "Parsed: " . json_encode(json_decode($content, true)) . "\n\n";

unlink($testFile);
echo "=== Tests Complete ===\n";
