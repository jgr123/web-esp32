<?php
/**
 * POST /api/auth/login.php
 * Autentica o usuário e cria a sessão.
 *
 * Body (JSON): { "username": "...", "senha": "..." }
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';

// Permitir apenas POST
if (getMethod() !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Método não suportado.'], 405);
}

$body = getRequestBody();
$error = validateRequired($body, ['username', 'senha']);
if ($error) {
    jsonResponse(['success' => false, 'message' => $error], 422);
}

$db = getDB();

// Buscar usuário pelo username
$stmt = $db->prepare("SELECT id, nome_real, username, senha_hash, perfil, ativo FROM usuarios WHERE username = ? LIMIT 1");
$stmt->execute([trim($body['username'])]);
$usuario = $stmt->fetch();

// Verificar existência, status ativo e senha
if (!$usuario) {
    jsonResponse(['success' => false, 'message' => 'Usuário ou senha inválidos.'], 401);
}

if (!(bool)$usuario['ativo']) {
    jsonResponse(['success' => false, 'message' => 'Conta desativada. Contate o administrador.'], 403);
}

if (!password_verify($body['senha'], $usuario['senha_hash'])) {
    jsonResponse(['success' => false, 'message' => 'Usuário ou senha inválidos.'], 401);
}

// Criar sessão segura
startSession();
session_regenerate_id(true); // Previne session fixation

$_SESSION['usuario_id'] = $usuario['id'];
$_SESSION['username']   = $usuario['username'];
$_SESSION['nome_real']  = $usuario['nome_real'];
$_SESSION['perfil']     = (int)$usuario['perfil'];

jsonResponse([
    'success' => true,
    'message' => 'Login realizado com sucesso.',
    'usuario' => [
        'id'        => $usuario['id'],
        'username'  => $usuario['username'],
        'nome_real' => $usuario['nome_real'],
        'perfil'    => (int)$usuario['perfil'],
    ],
]);
