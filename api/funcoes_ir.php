<?php
/**
 * /api/funcoes_ir.php — CRUD de Funções IR (Admin)
 * Também expõe tabelas auxiliares: modos, temperaturas, velocidades
 *
 * GET ?aux=modos       → Retorna modos_climatizador
 * GET ?aux=temperaturas → Retorna temperaturas
 * GET ?aux=velocidades  → Retorna velocidades
 * GET                  → Lista funções IR com joins
 * GET ?id=X            → Detalhe de uma função
 * POST / PUT / DELETE  → CRUD (Admin)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

startSession();
requireLogin();

$method = getMethod();
$id     = isset($_GET['id'])  ? (int)$_GET['id']  : null;
$aux    = $_GET['aux']        ?? null;
$db     = getDB();

// ── Atalhos para tabelas auxiliares ──────────────────────────────────────────
if ($method === 'GET' && $aux) {
    $tables = ['modos' => 'modos_climatizador', 'temperaturas' => 'temperaturas', 'velocidades' => 'velocidades'];
    if (!isset($tables[$aux])) {
        jsonResponse(['success' => false, 'message' => 'Auxiliar inválido.'], 400);
    }
    $rows = $db->query("SELECT * FROM {$tables[$aux]} ORDER BY id")->fetchAll();
    jsonResponse(['success' => true, 'data' => $rows]);
}

switch ($method) {
    case 'GET':
        $sql = "
            SELECT f.id, f.nome, f.categoria,
                   mc.nome AS modo, mc.id AS modo_id,
                   t.valor_c AS temperatura, t.id AS temperatura_id,
                   v.nivel AS velocidade_nivel, v.descricao AS velocidade_desc, v.id AS velocidade_id
            FROM funcoes_ir f
            LEFT JOIN modos_climatizador mc ON mc.id = f.modo_id
            LEFT JOIN temperaturas        t  ON t.id  = f.temperatura_id
            LEFT JOIN velocidades         v  ON v.id  = f.velocidade_id
        ";

        if ($id) {
            $stmt = $db->prepare($sql . " WHERE f.id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            if (!$row) jsonResponse(['success' => false, 'message' => 'Função não encontrada.'], 404);
            jsonResponse(['success' => true, 'data' => $row]);
        }

        $rows = $db->query($sql . " ORDER BY f.categoria, f.nome")->fetchAll();
        jsonResponse(['success' => true, 'data' => $rows]);
        break;

    case 'POST':
        requireAdmin();
        $body  = getRequestBody();
        $error = validateRequired($body, ['nome', 'categoria']);
        if ($error) jsonResponse(['success' => false, 'message' => $error], 422);

        try {
            $stmt = $db->prepare("INSERT INTO funcoes_ir (nome, categoria, modo_id, temperatura_id, velocidade_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                trim($body['nome']),
                trim($body['categoria']),
                $body['modo_id']        ? (int)$body['modo_id']        : null,
                $body['temperatura_id'] ? (int)$body['temperatura_id'] : null,
                $body['velocidade_id']  ? (int)$body['velocidade_id']  : null,
            ]);
            jsonResponse(['success' => true, 'message' => 'Função IR criada.', 'id' => (int)$db->lastInsertId()], 201);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') jsonResponse(['success' => false, 'message' => 'Função com esse nome já existe.'], 409);
            throw $e;
        }
        break;

    case 'PUT':
        requireAdmin();
        if (!$id) jsonResponse(['success' => false, 'message' => 'ID não informado.'], 400);
        $body  = getRequestBody();
        $error = validateRequired($body, ['nome', 'categoria']);
        if ($error) jsonResponse(['success' => false, 'message' => $error], 422);

        $stmt = $db->prepare("UPDATE funcoes_ir SET nome=?, categoria=?, modo_id=?, temperatura_id=?, velocidade_id=? WHERE id=?");
        $stmt->execute([
            trim($body['nome']),
            trim($body['categoria']),
            $body['modo_id']        ? (int)$body['modo_id']        : null,
            $body['temperatura_id'] ? (int)$body['temperatura_id'] : null,
            $body['velocidade_id']  ? (int)$body['velocidade_id']  : null,
            $id,
        ]);
        jsonResponse(['success' => true, 'message' => 'Função IR atualizada.']);
        break;

    case 'DELETE':
        requireAdmin();
        if (!$id) jsonResponse(['success' => false, 'message' => 'ID não informado.'], 400);
        $stmt = $db->prepare("DELETE FROM funcoes_ir WHERE id=?");
        $stmt->execute([$id]);
        jsonResponse(['success' => true, 'message' => 'Função IR removida.']);
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Método não suportado.'], 405);
}
