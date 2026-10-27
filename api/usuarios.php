<?php
/**
 * /api/usuarios.php — CRUD de Usuários (somente Admin)
 *
 * GET    ?id=X  → Busca um usuário
 * GET           → Lista todos os usuários
 * POST          → Cria novo usuário
 * PUT    ?id=X  → Atualiza usuário
 * DELETE ?id=X  → Remove usuário
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

startSession();
requireAdmin(); // Apenas administradores gerenciam usuários

$method = getMethod();
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;
$db     = getDB();

switch ($method) {
    // ─────────────────────────────────────────────────────────
    case 'GET':
        if ($id) {
            $stmt = $db->prepare("SELECT id, nome_real, username, email, perfil, ativo, criado_em FROM usuarios WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            if (!$row) jsonResponse(['success' => false, 'message' => 'Usuário não encontrado.'], 404);
            jsonResponse(['success' => true, 'data' => $row]);
        }

        $rows = $db->query("SELECT id, nome_real, username, email, perfil, ativo, criado_em FROM usuarios ORDER BY nome_real")->fetchAll();
        jsonResponse(['success' => true, 'data' => $rows]);
        break;

    // ─────────────────────────────────────────────────────────
    case 'POST':
        $body  = getRequestBody();
        $error = validateRequired($body, ['nome_real', 'username', 'senha', 'email', 'perfil']);
        if ($error) jsonResponse(['success' => false, 'message' => $error], 422);

        if (!filter_var($body['email'], FILTER_VALIDATE_EMAIL)) {
            jsonResponse(['success' => false, 'message' => 'E-mail inválido.'], 422);
        }

        if (strlen($body['senha']) < 6) {
            jsonResponse(['success' => false, 'message' => 'A senha deve ter ao menos 6 caracteres.'], 422);
        }

        $perfil = (int)$body['perfil'];
        if (!in_array($perfil, [1, 2])) {
            jsonResponse(['success' => false, 'message' => 'Perfil inválido. Use 1 (Admin) ou 2 (Padrão).'], 422);
        }

        try {
            $stmt = $db->prepare("INSERT INTO usuarios (nome_real, username, senha_hash, email, perfil, ativo) VALUES (?, ?, ?, ?, ?, 1)");
            $stmt->execute([
                trim($body['nome_real']),
                trim($body['username']),
                password_hash($body['senha'], PASSWORD_BCRYPT),
                strtolower(trim($body['email'])),
                $perfil,
            ]);
            jsonResponse(['success' => true, 'message' => 'Usuário criado com sucesso.', 'id' => (int)$db->lastInsertId()], 201);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                jsonResponse(['success' => false, 'message' => 'Username ou e-mail já cadastrado.'], 409);
            }
            throw $e;
        }
        break;

    // ─────────────────────────────────────────────────────────
    case 'PUT':
        if (!$id) jsonResponse(['success' => false, 'message' => 'ID não informado.'], 400);
        $body  = getRequestBody();
        $error = validateRequired($body, ['nome_real', 'username', 'email', 'perfil']);
        if ($error) jsonResponse(['success' => false, 'message' => $error], 422);

        if (!filter_var($body['email'], FILTER_VALIDATE_EMAIL)) {
            jsonResponse(['success' => false, 'message' => 'E-mail inválido.'], 422);
        }

        $perfil = (int)$body['perfil'];
        if (!in_array($perfil, [1, 2])) {
            jsonResponse(['success' => false, 'message' => 'Perfil inválido.'], 422);
        }

        // Atualiza senha apenas se fornecida
        if (!empty($body['senha'])) {
            if (strlen($body['senha']) < 6) {
                jsonResponse(['success' => false, 'message' => 'A senha deve ter ao menos 6 caracteres.'], 422);
            }
            $stmt = $db->prepare("UPDATE usuarios SET nome_real=?, username=?, senha_hash=?, email=?, perfil=?, ativo=? WHERE id=?");
            $stmt->execute([
                trim($body['nome_real']),
                trim($body['username']),
                password_hash($body['senha'], PASSWORD_BCRYPT),
                strtolower(trim($body['email'])),
                $perfil,
                (int)($body['ativo'] ?? 1),
                $id,
            ]);
        } else {
            $stmt = $db->prepare("UPDATE usuarios SET nome_real=?, username=?, email=?, perfil=?, ativo=? WHERE id=?");
            $stmt->execute([
                trim($body['nome_real']),
                trim($body['username']),
                strtolower(trim($body['email'])),
                $perfil,
                (int)($body['ativo'] ?? 1),
                $id,
            ]);
        }

        jsonResponse(['success' => true, 'message' => 'Usuário atualizado com sucesso.']);
        break;

    // ─────────────────────────────────────────────────────────
    case 'DELETE':
        if (!$id) jsonResponse(['success' => false, 'message' => 'ID não informado.'], 400);

        // Impedir que o admin remova a si mesmo
        $session = getSessionUser();
        if ((int)$session['id'] === $id) {
            jsonResponse(['success' => false, 'message' => 'Você não pode remover o próprio usuário.'], 403);
        }

        $stmt = $db->prepare("DELETE FROM usuarios WHERE id = ?");
        $stmt->execute([$id]);
        jsonResponse(['success' => true, 'message' => 'Usuário removido com sucesso.']);
        break;

    // ─────────────────────────────────────────────────────────
    default:
        jsonResponse(['success' => false, 'message' => 'Método não suportado.'], 405);
}
