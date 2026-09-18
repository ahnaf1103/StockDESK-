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

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $inv_id = $_GET['inv_id'] ?? null;
        $action = $_GET['action'] ?? null;
        $sql = "SELECT * FROM history WHERE 1=1";
        $params = [];
        if ($inv_id) {
            $sql .= " AND inv_id = ?";
            $params[] = $inv_id;
        }
        if ($action) {
            $sql .= " AND action = ?";
            $params[] = $action;
        }
        $sql .= " ORDER BY created_at DESC";
        $history = DB::fetchAll($sql, $params);
        echo json_encode(['ok' => true, 'data' => $history]);
        break;

    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data['inv_id']) || empty($data['action']) || empty($data['qty'])) {
            echo json_encode(['ok' => false, 'error' => 'inv_id, action, and qty are required']);
            exit;
        }
        $id = uuid();
        DB::query("INSERT INTO history (id, inv_id, model, config, type, action, qty, note, created_by, date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", [
            $id, $data['inv_id'], $data['model'], $data['config'] ?? '', $data['type'] ?? '', $data['action'], $data['qty'], $data['note'] ?? '', $user['id'], date('Y-m-d')
        ]);
        echo json_encode(['ok' => true, 'data' => ['id' => $id]]);
        break;

    case 'DELETE':
        if ($user['role'] === 'Super Admin' || ($user['permissions']['clearHistory'] ?? false)) {
            DB::query("DELETE FROM history");
            echo json_encode(['ok' => true]);
        } else {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Permission denied']);
        }
        break;
}
?>
