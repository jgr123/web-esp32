<?php
/**
 * POST /api/auth/logout.php
 * Encerra a sessão do usuário autenticado.
 */

require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';

startSession();
$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}

session_destroy();

jsonResponse(['success' => true, 'message' => 'Logout realizado com sucesso.']);
