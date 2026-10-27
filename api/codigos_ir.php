<?php
/**
 * /api/codigos_ir.php — CRUD de Códigos IR (Admin)
 *
 * GET ?modelo_id=X → Lista todos os códigos de um modelo
 * GET ?id=X        → Detalhe de um código
 * POST             → Cria código IR
 * PUT ?id=X        → Atualiza código IR
 * DELETE ?id=X     → Remove código IR
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

startSession();
requireLogin();

$method    = getMethod();
$id        = isset($_GET['id'])        ? (int)$_GET['id']        : null;
$modelo_id = isset($_GET['modelo_id']) ? (int)$_GET['modelo_id'] : null;
$db        = getDB();

switch ($method) {
    case 'GET':
        $sql = "
            SELECT c.id, c.modelo_id, c.funcao_id, c.protocolo, c.codigo, c.frequencia, c.criado_em,
                   f.nome AS funcao, f.categoria,
                   mc.nome AS modo,
                   t.valor_c AS temperatura,
                   v.descricao AS velocidade,
                   m.nome AS marca, mo.nome AS modelo
            FROM codigos_ir c
            JOIN funcoes_ir f ON f.id = c.funcao_id
            JOIN modelos mo ON mo.id = c.modelo_id
            JOIN marcas m ON m.id = mo.marca_id
            LEFT JOIN modos_climatizador mc ON mc.id = f.modo_id
            LEFT JOIN temperaturas        t  ON t.id  = f.temperatura_id
            LEFT JOIN velocidades         v  ON v.id  = f.velocidade_id
        ";

        if ($id) {
            $stmt = $db->prepare($sql . " WHERE c.id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            if (!$row) jsonResponse(['success' => false, 'message' => 'Código IR não encontrado.'], 404);
            jsonResponse(['success' => true, 'data' => $row]);
        }

        if ($modelo_id) {
            $stmt = $db->prepare($sql . " WHERE c.modelo_id = ? ORDER BY f.categoria, f.nome");
            $stmt->execute([$modelo_id]);
            jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);
        }

        $rows = $db->query($sql . " ORDER BY m.nome, mo.nome, f.categoria, f.nome")->fetchAll();
        jsonResponse(['success' => true, 'data' => $rows]);
        break;

    case 'POST':
        requireAdmin();
        $body  = getRequestBody();
        $error = validateRequired($body, ['modelo_id', 'funcao_id', 'codigo']);
        if ($error) jsonResponse(['success' => false, 'message' => $error], 422);

        try {
            $stmt = $db->prepare("
                INSERT INTO codigos_ir (modelo_id, funcao_id, protocolo, codigo, frequencia)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                (int)$body['modelo_id'],
                (int)$body['funcao_id'],
                trim($body['protocolo'] ?? '') ?: null,
                trim($body['codigo']),
                isset($body['frequencia']) ? (int)$body['frequencia'] : 38,
            ]);
            jsonResponse(['success' => true, 'message' => 'Código IR cadastrado.', 'id' => (int)$db->lastInsertId()], 201);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                jsonResponse(['success' => false, 'message' => 'Já existe um código IR para este modelo e função.'], 409);
            }
            throw $e;
        }
        break;

    case 'PUT':
        requireAdmin();
        if (!$id) jsonResponse(['success' => false, 'message' => 'ID não informado.'], 400);
        $body  = getRequestBody();
        $error = validateRequired($body, ['codigo']);
        if ($error) jsonResponse(['success' => false, 'message' => $error], 422);

        $stmt = $db->prepare("UPDATE codigos_ir SET protocolo=?, codigo=?, frequencia=? WHERE id=?");
        $stmt->execute([
            trim($body['protocolo'] ?? '') ?: null,
            trim($body['codigo']),
            isset($body['frequencia']) ? (int)$body['frequencia'] : 38,
            $id,
        ]);
        jsonResponse(['success' => true, 'message' => 'Código IR atualizado.']);
        break;

    case 'DELETE':
        requireAdmin();
        if (!$id) jsonResponse(['success' => false, 'message' => 'ID não informado.'], 400);
        $stmt = $db->prepare("DELETE FROM codigos_ir WHERE id=?");
        $stmt->execute([$id]);
        jsonResponse(['success' => true, 'message' => 'Código IR removido.']);
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Método não suportado.'], 405);
}
