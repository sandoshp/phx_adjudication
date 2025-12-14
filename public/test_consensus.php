<?php
/**
 * Diagnostic page to test consensus.php access
 */
echo "<!DOCTYPE html><html><head><title>Consensus Test</title></head><body>";
echo "<h1>Consensus.php Diagnostic</h1>";

echo "<h2>File System Check:</h2>";
$consensusPath = __DIR__ . '/consensus.php';
echo "<p>Consensus.php path: " . htmlspecialchars($consensusPath) . "</p>";
echo "<p>File exists: " . (file_exists($consensusPath) ? 'YES' : 'NO') . "</p>";
echo "<p>File readable: " . (is_readable($consensusPath) ? 'YES' : 'NO') . "</p>";

echo "<h2>URL Information:</h2>";
echo "<p>Current script: " . htmlspecialchars($_SERVER['PHP_SELF']) . "</p>";
echo "<p>Document root: " . htmlspecialchars($_SERVER['DOCUMENT_ROOT']) . "</p>";
echo "<p>Server name: " . htmlspecialchars($_SERVER['SERVER_NAME']) . "</p>";
echo "<p>Request URI: " . htmlspecialchars($_SERVER['REQUEST_URI']) . "</p>";

echo "<h2>Test Links:</h2>";
$baseUrl = dirname($_SERVER['PHP_SELF']);
echo "<p><a href='consensus.php?case_event_id=1'>Relative link: consensus.php?case_event_id=1</a></p>";
echo "<p><a href='{$baseUrl}/consensus.php?case_event_id=1'>Absolute path: {$baseUrl}/consensus.php?case_event_id=1</a></p>";

echo "<h2>Suggested URL:</h2>";
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$fullUrl = $protocol . '://' . $_SERVER['HTTP_HOST'] . $baseUrl . '/consensus.php?case_event_id=1';
echo "<p>Try accessing: <a href='" . htmlspecialchars($fullUrl) . "'>" . htmlspecialchars($fullUrl) . "</a></p>";

echo "</body></html>";
?>
