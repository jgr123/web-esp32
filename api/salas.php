<?php
/**
 * /api/salas.php — CRUD de Salas
 *
 * GET    → Lista salas com contagem de dispositivos (todos os usuários)
 * GET ?id=X → Detalhe da sala com dispositivos instalados
 * POST   → Cria sala (Admin)
 * PUT ?id=X → Atualiza sala (Admin)
 * DELETE ?id=X → Remove sala (Admin, cascata nos dispositivos)
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
    // ─────────────────────────────────────────────────────────
    case 'GET':
        if ($id) {
            // Detalhe da sala + lista de dispositivos instalados
            $stmtSala = $db->prepare("SELECT * FROM salas WHERE id = ?");
            $stmtSala->execute([$id]);
            $sala = $stmtSala->fetch();
            if (!$sala) jsonResponse(['success' => false, 'message' => 'Sala não encontrada.'], 404);

            $stmtDisp = $db->prepare("SELECT * FROM vw_sala_dispositivos WHERE sala_id = ? ORDER BY slot");
            $stmtDisp->execute([$id]);
            $sala['dispositivos'] = $stmtDisp->fetchAll();

            jsonResponse(['success' => true, 'data' => $sala]);
        }

        // Lista todas as salas com contagem de dispositivos
        $rows = $db->query("
            SELECT s.id, s.nome, s.descricao, s.ativo, s.criado_em,
                   COUNT(sd.id) AS total_dispositivos,
                   (4 - COUNT(sd.id)) AS slots_livres
            FROM salas s
            LEFT JOIN sala_dispositivos sd ON sd.sala_id = s.id AND sd.ativo = 1
            GROUP BY s.id, s.nome, s.descricao, s.ativo, s.criado_em
            ORDER BY s.nome
        ")->fetchAll();
        jsonResponse(['success' => true, 'data' => $rows]);
        break;

    // ─────────────────────────────────────────────────────────
    case 'POST':
        requireAdmin();
        $body  = getRequestBody();
        $error = validateRequired($body, ['nome']);
        if ($error) jsonResponse(['success' => false, 'message' => $error], 422);

        try {
            $stmt = $db->prepare("INSERT INTO salas (nome, descricao) VALUES (?, ?)");
            $stmt->execute([trim($body['nome']), trim($body['descricao'] ?? '')  ?: null]);
            jsonResponse(['success' => true, 'message' => 'Sala criada com sucesso.', 'id' => (int)$db->lastInsertId()], 201);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                jsonResponse(['success' => false, 'message' => 'Já existe uma sala com esse nome.'], 409);
            }
            throw $e;
        }
        break;

    // ─────────────────────────────────────────────────────────
    case 'PUT':
        requireAdmin();
        if (!$id) jsonResponse(['success' => false, 'message' => 'ID não informado.'], 400);
        $body  = getRequestBody();
        $error = validateRequired($body, ['nome']);
        if ($error) jsonResponse(['success' => false, 'message' => $error], 422);

        $stmt = $db->prepare("UPDATE salas SET nome=?, descricao=?, ativo=? WHERE id=?");
        $stmt->execute([trim($body['nome']), trim($body['descricao'] ?? '') ?: null, (int)($body['ativo'] ?? 1), $id]);
        jsonResponse(['success' => true, 'message' => 'Sala atualizada com sucesso.']);
        break;

    // ─────────────────────────────────────────────────────────
    case 'DELETE':
        requireAdmin();
        if (!$id) jsonResponse(['success' => false, 'message' => 'ID não informado.'], 400);
        $stmt = $db->prepare("DELETE FROM salas WHERE id = ?");
        $stmt->execute([$id]);
        jsonResponse(['success' => true, 'message' => 'Sala removida com sucesso.']);
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Método não suportado.'], 405);
}
