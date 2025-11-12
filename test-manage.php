<?php
// Test script for manage.php

echo "=== Testing manage.php ===\n\n";

// Test 1: GET sources (page 1)
echo "Test 1: GET sources (page 1, pageSize 3)\n";
$_SERVER['REQUEST_METHOD'] = 'GET';
$_REQUEST['action'] = 'get_sources';
$_REQUEST['page'] = 1;
$_REQUEST['pageSize'] = 3;
ob_start();
include 'manage.php';
$output = ob_get_clean();
echo $output . "\n\n";

// Test 2: GET sources (page 2)
echo "Test 2: GET sources (page 2, pageSize 3)\n";
$_REQUEST['page'] = 2;
ob_start();
include 'manage.php';
$output = ob_get_clean();
echo $output . "\n\n";

// Test 3: Save sources (simulating POST)
echo "Test 3: Save sources (page 1, pageSize 2)\n";
$_SERVER['REQUEST_METHOD'] = 'POST';
$_REQUEST['action'] = 'save_sources';
$_REQUEST['page'] = 1;
$_REQUEST['pageSize'] = 2;

// Create a temporary file to simulate php://input
$testData = json_encode(['items' => ['新频道1,http://test.com/1', '新频道2,http://test.com/2']]);
$tempFile = tempnam(sys_get_temp_dir(), 'php_input_');
file_put_contents($tempFile, $testData);

// Override file_get_contents for php://input
function file_get_contents_override($filename) {
    global $tempFile;
    if ($filename === 'php://input') {
        return file_get_contents($tempFile);
    }
    return file_get_contents($filename);
}

// Manually execute the save logic
$raw = file_get_contents($tempFile);
$data = json_decode($raw, true);
echo "Input data: " . json_encode($data) . "\n";

if (!is_array($data) || !isset($data['items']) || !is_array($data['items'])) {
    echo json_encode(['success' => false, 'message' => '请求体应为 JSON，包含 items 数组']) . "\n\n";
} else {
    // Call the save function directly
    require_once 'manage.php';
    $result = save_page_to_sources(__DIR__ . '/sources.json', 1, 2, $data['items']);
    echo "Result: " . json_encode($result) . "\n\n";
}

unlink($tempFile);

// Test 4: Verify the save by getting page 1 again
echo "Test 4: GET sources after save (page 1, pageSize 3)\n";
$_SERVER['REQUEST_METHOD'] = 'GET';
$_REQUEST['action'] = 'get_sources';
$_REQUEST['page'] = 1;
$_REQUEST['pageSize'] = 3;
ob_start();
include 'manage.php';
$output = ob_get_clean();
echo $output . "\n\n";

echo "=== Tests completed ===\n";
