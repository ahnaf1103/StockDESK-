<?php
require_once 'db.php';
require_once 'auth.php';

header('Content-Type: application/json; charset=utf-8');
$user = getCurrentUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['file'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Upload a CSV file in the file field.']);
    exit;
}

$file = $_FILES['file'];
if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'CSV upload failed.']);
    exit;
}

$handle = fopen($file['tmp_name'], 'rb');
if (!$handle) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Unable to read CSV.']);
    exit;
}
$headers = fgetcsv($handle);
if (!$headers) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'CSV is empty.']);
    exit;
}
$headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
$headers = array_map(function ($h) { return strtolower(trim((string)$h)); }, $headers);
$required = ['model', 'config', 'brand', 'type', 'start', 'in', 'out', 'available', 'note'];
foreach ($required as $column) {
    if (!in_array($column, $headers, true)) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Missing required column: ' . $column]);
        exit;
    }
}
$index = array_flip($headers);
$rows = [];
$line = 1;
$skipped = 0;
while (($values = fgetcsv($handle)) !== false) {
    $line++;
    if (count($values) === 1 && trim((string)$values[0]) === '') continue;
    $get = function ($key) use ($values, $index) { return trim((string)($values[$index[$key]] ?? '')); };
    $model = $get('model');
    $brand = strtoupper($get('brand'));
    $type = $get('type') ?: 'Regular';
    if ($model === '' || $brand === '') { $skipped++; continue; }
    if (!in_array($brand, ['HONOR', 'REDMI', 'ONEPLUS', 'OTHER'], true)) $brand = 'OTHER';
    if (!in_array($type, ['Regular', 'LMP', 'Repair'], true)) $type = 'Regular';
    $rows[] = [
        'model' => $model, 'config' => $get('config'), 'brand' => $brand, 'type' => $type,
        'start_stock' => max(0, (int)$get('start')), 'stock_in' => max(0, (int)$get('in')),
        'stock_out' => max(0, (int)$get('out')), 'note' => $get('note')
    ];
}
fclose($handle);

$replace = ($_GET['replace'] ?? '1') !== '0';
if ($replace) DB::query('DELETE FROM inventory');
foreach ($rows as $row) {
    $id = uuid();
    DB::query('INSERT INTO inventory (id, model, config, brand, type, start_stock, stock_in, stock_out, note, created_by, date_added) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
        $id, $row['model'], $row['config'], $row['brand'], $row['type'], $row['start_stock'], $row['stock_in'], $row['stock_out'], $row['note'], $user['id'], date('Y-m-d')
    ]);
}
echo json_encode(['ok' => true, 'mode' => $replace ? 'replace' : 'append', 'imported' => count($rows), 'skipped' => $skipped]);
?>
