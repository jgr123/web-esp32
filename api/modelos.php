<?php
/**
 * /api/modelos.php — CRUD de Modelos de Dispositivos (Admin)
 *
 * GET ?marca_id=X → Filtra por marca
 * GET ?tipo_id=X  → Filtra por tipo de dispositivo
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

startSession();
requireLogin();

$method   = getMethod();
$id       = isset($_GET['id'])       ? (int)$_GET['id']       : null;
$marca_id = isset($_GET['marca_id']) ? (int)$_GET['marca_id'] : null;
$tipo_id  = isset($_GET['tipo_id'])  ? (int)$_GET['tipo_id']  : null;
$aux      = $_GET['aux']             ?? null;
$db       = getDB();

if ($method === 'GET' && $aux === 'tipos') {
    $rows = $db->query("SELECT * FROM tipos_dispositivo ORDER BY nome")->fetchAll();
    jsonResponse(['success' => true, 'data' => $rows]);
}

switch ($method) {
    case 'GET':
        if ($id) {
            $stmt = $db->prepare("
                SELECT mo.*, m.nome AS marca, td.nome AS tipo
                FROM modelos mo
                JOIN marcas m ON m.id = mo.marca_id
                JOIN tipos_dispositivo td ON td.id = mo.tipo_id
                WHERE mo.id = ?
            ");
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            if (!$row) jsonResponse(['success' => false, 'message' => 'Modelo não encontrado.'], 404);
            jsonResponse(['success' => true, 'data' => $row]);
        }

        $where  = [];
        $params = [];
        if ($marca_id) { $where[] = 'mo.marca_id = ?'; $params[] = $marca_id; }
        if ($tipo_id)  { $where[] = 'mo.tipo_id  = ?'; $params[] = $tipo_id; }
        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $stmt = $db->prepare("
            SELECT mo.id, mo.nome, mo.referencia, mo.marca_id, mo.tipo_id,
                   m.nome AS marca, td.nome AS tipo
            FROM modelos mo
            JOIN marcas m ON m.id = mo.marca_id
            JOIN tipos_dispositivo td ON td.id = mo.tipo_id
            {$whereClause}
            ORDER BY m.nome, mo.nome
        ");
        $stmt->execute($params);
        jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);
        break;

    case 'POST':
        requireAdmin();
        $body  = getRequestBody();
        $error = validateRequired($body, ['nome', 'marca_id', 'tipo_id']);
        if ($error) jsonResponse(['success' => false, 'message' => $error], 422);

        try {
            $stmt = $db->prepare("INSERT INTO modelos (marca_id, tipo_id, nome, referencia) VALUES (?, ?, ?, ?)");
            $stmt->execute([(int)$body['marca_id'], (int)$body['tipo_id'], trim($body['nome']), trim($body['referencia'] ?? '') ?: null]);
            jsonResponse(['success' => true, 'message' => 'Modelo criado.', 'id' => (int)$db->lastInsertId()], 201);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') jsonResponse(['success' => false, 'message' => 'Modelo já cadastrado para essa marca.'], 409);
            throw $e;
        }
        break;

    case 'PUT':
        requireAdmin();
        if (!$id) jsonResponse(['success' => false, 'message' => 'ID não informado.'], 400);
        $body  = getRequestBody();
        $error = validateRequired($body, ['nome', 'marca_id', 'tipo_id']);
        if ($error) jsonResponse(['success' => false, 'message' => $error], 422);

        $stmt = $db->prepare("UPDATE modelos SET marca_id=?, tipo_id=?, nome=?, referencia=? WHERE id=?");
        $stmt->execute([(int)$body['marca_id'], (int)$body['tipo_id'], trim($body['nome']), trim($body['referencia'] ?? '') ?: null, $id]);
        jsonResponse(['success' => true, 'message' => 'Modelo atualizado.']);
        break;

    case 'DELETE':
        requireAdmin();
        if (!$id) jsonResponse(['success' => false, 'message' => 'ID não informado.'], 400);
        $stmt = $db->prepare("DELETE FROM modelos WHERE id=?");
        $stmt->execute([$id]);
        jsonResponse(['success' => true, 'message' => 'Modelo removido.']);
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Método não suportado.'], 405);
}
