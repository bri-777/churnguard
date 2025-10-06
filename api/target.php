<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
$uid = require_login();

function j_ok(array $d = []) { json_ok($d); }
function j_err(string $m, int $c = 400, array $extra = []) { json_error($m, $c, $extra); }

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

// Create or Update Target
if ($action === 'save') {
    try {
        $raw = file_get_contents('php://input') ?: '';
        $data = json_decode($raw, true) ?: $_POST;
        
        $id = $data['id'] ?? null;
        $name = trim($data['target_name'] ?? '');
        $type = $data['target_type'] ?? 'sales';
        $value = (float)($data['target_value'] ?? 0);
        $startDate = $data['start_date'] ?? date('Y-m-d');
        $endDate = $data['end_date'] ?? date('Y-m-d');
        $store = trim($data['store'] ?? '');
        
        if (empty($name)) j_err('Target name is required', 422);
        if ($value <= 0) j_err('Target value must be greater than 0', 422);
        
        if ($id) {
            // Update
            $stmt = $pdo->prepare("
                UPDATE targets SET
                    target_name = ?, target_type = ?, target_value = ?,
                    start_date = ?, end_date = ?, store = ?,
                    updated_at = NOW()
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$name, $type, $value, $startDate, $endDate, $store, $id, $uid]);
        } else {
            // Insert
            $stmt = $pdo->prepare("
                INSERT INTO targets 
                (user_id, target_name, target_type, target_value, start_date, end_date, store)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$uid, $name, $type, $value, $startDate, $endDate, $store]);
            $id = $pdo->lastInsertId();
        }
        
        j_ok(['success' => true, 'id' => $id]);
    } catch (Throwable $e) {
        j_err('Save failed', 500, ['detail' => $e->getMessage()]);
    }
    exit;
}

// Get all targets
if ($action === 'list') {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM targets 
            WHERE user_id = ? 
            ORDER BY created_at DESC
        ");
        $stmt->execute([$uid]);
        $targets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate progress for each target
        foreach ($targets as &$target) {
            $current = 0;
            
            $dataStmt = $pdo->prepare("
                SELECT SUM(" . match($target['target_type']) {
                    'sales' => 'sales_volume',
                    'customers' => 'customer_traffic',
                    'transactions' => 'receipt_count',
                    default => 'sales_volume'
                } . ") as total
                FROM churn_data
                WHERE user_id = ? AND date BETWEEN ? AND ?
            ");
            $dataStmt->execute([$uid, $target['start_date'], $target['end_date']]);
            $result = $dataStmt->fetch(PDO::FETCH_ASSOC);
            $current = (float)($result['total'] ?? 0);
            
            if ($target['target_type'] === 'avg_transaction') {
                $avgStmt = $pdo->prepare("
                    SELECT AVG(sales_volume / NULLIF(receipt_count, 0)) as avg_val
                    FROM churn_data
                    WHERE user_id = ? AND date BETWEEN ? AND ? AND receipt_count > 0
                ");
                $avgStmt->execute([$uid, $target['start_date'], $target['end_date']]);
                $avgResult = $avgStmt->fetch(PDO::FETCH_ASSOC);
                $current = (float)($avgResult['avg_val'] ?? 0);
            }
            
            $target['current_value'] = $current;
            $target['progress'] = $target['target_value'] > 0 ? 
                round(($current / $target['target_value']) * 100, 2) : 0;
            
            // Determine status
            if ($target['progress'] >= 100) {
                $target['status'] = 'achieved';
            } elseif ($target['progress'] >= 75) {
                $target['status'] = 'near';
            } else {
                $target['status'] = 'below';
            }
        }
        
        j_ok(['targets' => $targets]);
    } catch (Throwable $e) {
        j_err('Failed to get targets', 500, ['detail' => $e->getMessage()]);
    }
    exit;
}

// Delete target
if ($action === 'delete') {
    try {
        $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
        
        $stmt = $pdo->prepare("DELETE FROM targets WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $uid]);
        
        j_ok(['success' => true]);
    } catch (Throwable $e) {
        j_err('Delete failed', 500, ['detail' => $e->getMessage()]);
    }
    exit;
}

j_err('Invalid action', 400);