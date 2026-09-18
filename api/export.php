<?php
require_once 'db.php';
require_once 'auth.php';

$user = getCurrentUser();
if (!$user) {
    http_response_code(401);
    exit('Unauthorized');
}

$format = $_GET['format'] ?? 'csv';
if ($format === 'json' || $format === 'backup') {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="stockdesk_backup_' . date('Y-m-d') . '.json"');
    $inventory = DB::fetchAll('SELECT * FROM inventory WHERE deleted_at IS NULL ORDER BY created_at ASC');
    $history = DB::fetchAll('SELECT * FROM history ORDER BY created_at ASC');
    foreach ($inventory as &$item) {
        $item['imeis'] = json_decode($item['imeis'] ?? '[]', true);
        $item['variations'] = json_decode($item['variations'] ?? '[]', true);
    }
    echo json_encode(['inventory' => $inventory, 'history' => $history, 'exported_at' => date('c')], JSON_PRETTY_PRINT);
    exit;
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="stockdesk-inventory_' . date('Y-m-d') . '.csv"');
$out = fopen('php://output', 'w');
fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
fputcsv($out, ['model', 'config', 'brand', 'type', 'start', 'in', 'out', 'available', 'note']);
$inventory = DB::fetchAll('SELECT model, config, brand, type, start_stock, stock_in, stock_out, note FROM inventory WHERE deleted_at IS NULL ORDER BY created_at ASC');
foreach ($inventory as $item) {
    $start = (int)$item['start_stock'];
    $incoming = (int)$item['stock_in'];
    $outgoing = (int)$item['stock_out'];
    fputcsv($out, [$item['model'], $item['config'], $item['brand'], $item['type'], $start, $incoming, $outgoing, $start + $incoming - $outgoing, $item['note']]);
}
fclose($out);
?>
