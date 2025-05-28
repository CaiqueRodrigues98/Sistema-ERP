<?php
// webhook.php - Recebe notificações de status de pedido
require 'config.php';
header('Content-Type: application/json; charset=utf-8');

function json_response($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!isset($input['id']) || !isset($input['status'])) {
    json_response(['error' => 'ID e status obrigatórios'], 400);
}

$pedido_id = $input['id'];
$status = $input['status'];

if (strtolower($status) === 'cancelado') {
    $stmt = $pdo->prepare('DELETE FROM pedidos WHERE id=?');
    $stmt->execute([$pedido_id]);
    json_response(['ok' => true, 'action' => 'deleted']);
} else {
    // Busca status_id correspondente
    $stmt = $pdo->prepare('SELECT id FROM pedido_status WHERE nome=?');
    $stmt->execute([$status]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $status_id = $row['id'];
        $stmt = $pdo->prepare('UPDATE pedidos SET status_id=? WHERE id=?');
        $stmt->execute([$status_id, $pedido_id]);
        json_response(['ok' => true, 'action' => 'updated', 'pedido_id' => $pedido_id, 'status' => $status]);
    } else {
        json_response(['error' => 'Status inválido'], 400);
    }
}
