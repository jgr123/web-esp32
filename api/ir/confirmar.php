<?php
/**
 * POST /api/ir/confirmar.php — Confirma a execução de um comando IR pelo ESP32
 *
 * Body (JSON): { "log_id": 123 }
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';

header('Content-Type: application/json; charset=utf-8');

if (getMethod() !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Método não suportado. Utilize POST.'], 405);
}

$body   = getRequestBody();
$log_id = isset($body['log_id']) ? (int)$body['log_id'] : null;

if (!$log_id) {
    jsonResponse(['success' => false, 'message' => 'Parâmetro log_id é obrigatório.'], 422);
}

$db = getDB();
$stmt = $db->prepare("UPDATE log_disparos SET confirmado = 1 WHERE id = ?");
$stmt->execute([$log_id]);

jsonResponse([
    'success' => true,
    'message' => "Comando #{$log_id} confirmado com sucesso.",
]);
