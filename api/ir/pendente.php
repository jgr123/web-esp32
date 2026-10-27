<?php
/**
 * GET /api/ir/pendente.php?sala_id=X — Busca o próximo comando pendente para o ESP32
 *
 * Retorna o comando IR não confirmado mais antigo da sala especificada.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';

header('Content-Type: application/json; charset=utf-8');

$sala_id = isset($_GET['sala_id']) ? (int)$_GET['sala_id'] : null;
$db = getDB();

// Busca o comando pendente mais antigo
$sql = "
    SELECT l.id AS log_id, c.protocolo, c.codigo, c.frequencia,
           f.nome AS funcao, m.nome AS marca, mo.nome AS modelo,
           sd.sala_id
    FROM log_disparos l
    JOIN codigos_ir c ON c.id = l.codigo_ir_id
    JOIN funcoes_ir f ON f.id = c.funcao_id
    JOIN modelos mo ON mo.id = c.modelo_id
    JOIN marcas m ON m.id = mo.marca_id
    LEFT JOIN sala_dispositivos sd ON sd.dispositivo_id = l.dispositivo_id
    WHERE l.confirmado = 0
";

if ($sala_id) {
    $sql .= " AND (sd.sala_id = ? OR l.dispositivo_id IS NULL) ";
    $sql .= " ORDER BY l.id ASC LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->execute([$sala_id]);
} else {
    $sql .= " ORDER BY l.id ASC LIMIT 1";
    $stmt = $db->query($sql);
}

$cmd = $stmt->fetch();

if (!$cmd) {
    jsonResponse(['success' => true, 'has_command' => false, 'message' => 'Nenhum comando pendente.']);
}

jsonResponse([
    'success'     => true,
    'has_command' => true,
    'data'        => [
        'log_id'     => (int)$cmd['log_id'],
        'protocolo'  => $cmd['protocolo'] ?: 'NEC',
        'codigo'     => $cmd['codigo'],
        'frequencia' => (int)($cmd['frequencia'] ?: 38),
        'funcao'     => $cmd['funcao'],
        'marca'      => $cmd['marca'],
        'modelo'     => $cmd['modelo'],
    ]
]);
