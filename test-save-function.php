<?php
// Simple unit test for save_page_to_sources function

require_once 'manage.php';

echo "=== Testing save_page_to_sources function ===\n\n";

$testFile = __DIR__ . '/test-sources.json';

// Clean up any existing test file
if (file_exists($testFile)) {
    unlink($testFile);
}

// Test 1: Create new file with initial data
echo "Test 1: Save page 1 with 3 items\n";
$items1 = ['Item 1', 'Item 2', 'Item 3'];
$result1 = save_page_to_sources($testFile, 1, 3, $items1);
echo "Result: " . json_encode($result1) . "\n";
echo "File contents: " . file_get_contents($testFile) . "\n\n";

// Test 2: Save page 2 with 2 items
echo "Test 2: Save page 2 with 2 items\n";
$items2 = ['Item 4', 'Item 5'];
$result2 = save_page_to_sources($testFile, 2, 3, $items2);
echo "Result: " . json_encode($result2) . "\n";
echo "File contents: " . file_get_contents($testFile) . "\n\n";

// Test 3: Update page 1 with new data
echo "Test 3: Update page 1 with modified items\n";
$items3 = ['Modified 1', 'Modified 2', 'Modified 3'];
$result3 = save_page_to_sources($testFile, 1, 3, $items3);
echo "Result: " . json_encode($result3) . "\n";
echo "File contents: " . file_get_contents($testFile) . "\n\n";

// Test 4: Save page 4 (with gaps)
echo "Test 4: Save page 4 with 1 item (should create nulls for gaps)\n";
$items4 = ['Item at page 4'];
$result4 = save_page_to_sources($testFile, 4, 3, $items4);
echo "Result: " . json_encode($result4) . "\n";
echo "File contents: " . file_get_contents($testFile) . "\n\n";

// Test 5: Read all sources
echo "Test 5: Read all sources\n";
$all = read_all_sources($testFile);
echo "Total items: " . count($all) . "\n";
echo "All items: " . json_encode($all, JSON_UNESCAPED_UNICODE) . "\n\n";

// Clean up
unlink($testFile);

echo "=== Tests completed ===\n";
