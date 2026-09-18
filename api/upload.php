<?php
require_once 'auth.php';

header('Content-Type: application/json');

$user = getCurrentUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file'];
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'avif', 'gif'];
    
    if (!in_array(strtolower($ext), $allowed)) {
        echo json_encode(['ok' => false, 'error' => 'Invalid file type']);
        exit;
    }

    $newName = bin2hex(random_bytes(16)) . '.' . $ext;
    $uploadDir = '../uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    if (move_uploaded_file($file['tmp_tmp_name'] ?? $file['tmp_name'], $uploadDir . $newName)) {
        echo json_encode(['ok' => true, 'url' => 'uploads/' . $newName]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'Failed to move uploaded file']);
    }
} else {
    echo json_encode(['ok' => false, 'error' => 'No file uploaded']);
}
?>
