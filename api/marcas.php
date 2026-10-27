<?php
/**
 * /api/marcas.php — CRUD de Marcas (Admin)
 * /api/modelos.php já trata modelos; este arquivo cuida só das marcas.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

startSession();
requireLogin();

$method = getMethod();
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;
$db     = getDB();

switch ($method) {
    case 'GET':
        if ($id) {
            $stmt = $db->prepare("SELECT * FROM marcas WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            if (!$row) jsonResponse(['success' => false, 'message' => 'Marca não encontrada.'], 404);
            jsonResponse(['success' => true, 'data' => $row]);
        }
        $rows = $db->query("SELECT * FROM marcas ORDER BY nome")->fetchAll();
        jsonResponse(['success' => true, 'data' => $rows]);
        break;

    case 'POST':
        requireAdmin();
        $body  = getRequestBody();
        $error = validateRequired($body, ['nome']);
        if ($error) jsonResponse(['success' => false, 'message' => $error], 422);

        try {
            $stmt = $db->prepare("INSERT INTO marcas (nome) VALUES (?)");
            $stmt->execute([trim($body['nome'])]);
            jsonResponse(['success' => true, 'message' => 'Marca criada.', 'id' => (int)$db->lastInsertId()], 201);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') jsonResponse(['success' => false, 'message' => 'Marca já cadastrada.'], 409);
            throw $e;
        }
        break;

    case 'PUT':
        requireAdmin();
        if (!$id) jsonResponse(['success' => false, 'message' => 'ID não informado.'], 400);
        $body  = getRequestBody();
        $error = validateRequired($body, ['nome']);
        if ($error) jsonResponse(['success' => false, 'message' => $error], 422);

        $stmt = $db->prepare("UPDATE marcas SET nome=? WHERE id=?");
        $stmt->execute([trim($body['nome']), $id]);
        jsonResponse(['success' => true, 'message' => 'Marca atualizada.']);
        break;

    case 'DELETE':
        requireAdmin();
        if (!$id) jsonResponse(['success' => false, 'message' => 'ID não informado.'], 400);
        $stmt = $db->prepare("DELETE FROM marcas WHERE id=?");
        $stmt->execute([$id]);
        jsonResponse(['success' => true, 'message' => 'Marca removida.']);
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Método não suportado.'], 405);
}
