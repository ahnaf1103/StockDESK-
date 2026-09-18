<?php
/**
 * Usage: php tools/import_inventory.php data/stockdesk-inventory.csv [--append]
 * Run from the StockDesk installation directory.
 */
require_once __DIR__ . '/../api/db.php';

$path = $argv[1] ?? (__DIR__ . '/../data/stockdesk-inventory.csv');
$append = in_array('--append', $argv, true);
if (!is_file($path)) { fwrite(STDERR, "CSV not found: {$path}\n"); exit(1); }
$handle = fopen($path, 'rb');
$headers = fgetcsv($handle);
$headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
$headers = array_map(fn($h) => strtolower(trim((string)$h)), $headers ?: []);
$required = ['model','config','brand','type','start','in','out','available','note'];
if (array_diff($required, $headers)) { fwrite(STDERR, "CSV must contain: " . implode(', ', $required) . "\n"); exit(1); }
$idx = array_flip($headers); $rows = [];
while (($values = fgetcsv($handle)) !== false) {
    if (count($values) === 1 && trim((string)$values[0]) === '') continue;
    $get = fn($key) => trim((string)($values[$idx[$key]] ?? ''));
    if ($get('model') === '' || $get('brand') === '') continue;
    $brand = strtoupper($get('brand')); if (!in_array($brand, ['HONOR','REDMI','ONEPLUS','OTHER'], true)) $brand = 'OTHER';
    $type = $get('type') ?: 'Regular'; if (!in_array($type, ['Regular','LMP','Repair'], true)) $type = 'Regular';
    $rows[] = [$get('model'), $get('config'), $brand, $type, max(0,(int)$get('start')), max(0,(int)$get('in')), max(0,(int)$get('out')), $get('note')];
}
fclose($handle);
if (!$append) DB::query('DELETE FROM inventory');
foreach ($rows as $r) DB::query('INSERT INTO inventory (id, model, config, brand, type, start_stock, stock_in, stock_out, note, date_added) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [bin2hex(random_bytes(16)), $r[0], $r[1], $r[2], $r[3], $r[4], $r[5], $r[6], $r[7], date('Y-m-d')]);
echo 'Imported ' . count($rows) . ' rows (' . ($append ? 'append' : 'replace') . ")\n";
