<?php
require_once 'db.php';
require_once 'auth.php';

header('Content-Type: application/json');

$user = getCurrentUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

// Auto-purge items older than 30 days
DB::query("DELETE FROM inventory WHERE deleted_at < NOW() - INTERVAL 30 DAY");

$method = $_SERVER['REQUEST_METHOD'];
$id = $_GET['id'] ?? null;

switch ($method) {
    case 'GET':
        if ($id) {
            $item = DB::fetch("SELECT * FROM inventory WHERE id = ?", [$id]);
            if ($item) {
                $item['imeis'] = json_decode($item['imeis'] ?? '[]', true);
                $item['variations'] = json_decode($item['variations'] ?? '[]', true);
                echo json_encode(['ok' => true, 'data' => $item]);
            } else {
                http_response_code(404);
                echo json_encode(['ok' => false, 'error' => 'Item not found']);
            }
        } else {
            $trash = isset($_GET['trash']) && $_GET['trash'] == '1';
            $sql = "SELECT * FROM inventory WHERE " . ($trash ? "deleted_at IS NOT NULL" : "deleted_at IS NULL") . " ORDER BY created_at DESC";
            $items = DB::fetchAll($sql);
            foreach ($items as &$item) {
                $item['imeis'] = json_decode($item['imeis'] ?? '[]', true);
                $item['variations'] = json_decode($item['variations'] ?? '[]', true);
            }
            echo json_encode(['ok' => true, 'data' => $items]);
        }
        break;

    case 'POST':
        $action = $_GET['action'] ?? '';
        if ($action === 'restore' && $id) {
            DB::query("UPDATE inventory SET deleted_at = NULL, deleted_by = NULL WHERE id = ?", [$id]);
            echo json_encode(['ok' => true]);
        } elseif ($action === 'emptyTrash') {
            $data = json_decode(file_get_contents('php://input'), true);
            if (($data['pin'] ?? '') === '1234') {
                DB::query("DELETE FROM inventory WHERE deleted_at IS NOT NULL");
                echo json_encode(['ok' => true]);
            } else {
                http_response_code(403);
                echo json_encode(['ok' => false, 'error' => 'Invalid PIN']);
            }
        } else {
            $data = json_decode(file_get_contents('php://input'), true);
            // Basic validation
            if (empty($data['model']) || empty($data['brand']) || empty($data['type'])) {
                echo json_encode(['ok' => false, 'error' => 'Model, Brand, and Type are required']);
                exit;
            }
            $newId = $data['id'] ?? uuid();
            DB::query("INSERT INTO inventory (id, model, config, brand, type, color, house, source, price, start_stock, stock_in, stock_out, description, note, image_url, imeis, variations, created_by, date_added) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", [
                $newId, $data['model'], $data['config'] ?? '', $data['brand'], $data['type'], $data['color'] ?? '', 
                $data['house'] ?? '', $data['source'] ?? '', $data['price'] ?? 0, $data['start_stock'] ?? 0, 
                $data['stock_in'] ?? 0, $data['stock_out'] ?? 0, $data['description'] ?? '', $data['note'] ?? '', 
                $data['image_url'] ?? '', json_encode($data['imeis'] ?? []), json_encode($data['variations'] ?? []), 
                $user['id'], date('Y-m-d')
            ]);
            echo json_encode(['ok' => true, 'data' => ['id' => $newId]]);
        }
        break;

    case 'PUT':
        if (!$id) {
            echo json_encode(['ok' => false, 'error' => 'Missing ID']);
            exit;
        }
        $data = json_decode(file_get_contents('php://input'), true);
        $fields = [];
        $params = [];
        $allowed = ['model', 'config', 'brand', 'type', 'color', 'house', 'source', 'price', 'start_stock', 'stock_in', 'stock_out', 'description', 'note', 'image_url', 'imeis', 'variations'];
        foreach ($data as $key => $val) {
            if (in_array($key, $allowed)) {
                $fields[] = "$key = ?";
                $params[] = is_array($val) ? json_encode($val) : $val;
            }
        }
        if (empty($fields)) {
            echo json_encode(['ok' => false, 'error' => 'No fields to update']);
            exit;
        }
        $params[] = $id;
        DB::query("UPDATE inventory SET " . implode(', ', $fields) . " WHERE id = ?", $params);
        echo json_encode(['ok' => true]);
        break;

    case 'DELETE':
        if ($id) {
            DB::query("UPDATE inventory SET deleted_at = NOW(), deleted_by = ? WHERE id = ?", [$user['id'], $id]);
            echo json_encode(['ok' => true]);
        }
        break;
}
?>
