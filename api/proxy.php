<?php
require_once 'auth.php';

$user = getCurrentUser();
if (!$user) {
    http_response_code(401);
    exit('Unauthorized');
}

$url = $_GET['url'] ?? '';
if (!$url) {
    http_response_code(400);
    exit('Missing URL');
}

// Simple proxy
$content = file_get_contents($url);
if ($content === false) {
    http_response_code(500);
    exit('Failed to fetch URL');
}

// Try to pass through content type
$headers = $http_response_header ?? [];
foreach ($headers as $h) {
    if (stripos($h, 'Content-Type:') === 0) {
        header($h);
    }
}

echo $content;
?>
