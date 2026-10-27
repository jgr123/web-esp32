<?php
/**
 * Módulo de autenticação e controle de sessão
 * Gerencia login, permissões e dados do usuário logado
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Inicia a sessão PHP com configurações seguras
 */
function startSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

/**
 * Verifica se há um usuário autenticado na sessão
 */
function isLoggedIn(): bool
{
    startSession();
    return !empty($_SESSION['usuario_id']);
}

/**
 * Verifica se o usuário logado é Administrador (perfil = 1)
 */
function isAdmin(): bool
{
    startSession();
    return isLoggedIn() && (int)($_SESSION['perfil'] ?? 0) === 1;
}

/**
 * Exige autenticação. Redireciona ou retorna 401 se não logado.
 */
function requireLogin(): void
{
    if (!isLoggedIn()) {
        if (isApiRequest()) {
            jsonResponse(['success' => false, 'message' => 'Não autenticado.'], 401);
        } else {
            header('Location: /esp32-ir/login.php');
            exit;
        }
    }
}

/**
 * Exige perfil de Administrador. Retorna 403 se insuficiente.
 */
function requireAdmin(): void
{
    requireLogin();
    if (!isAdmin()) {
        if (isApiRequest()) {
            jsonResponse(['success' => false, 'message' => 'Acesso negado. Requer perfil Administrador.'], 403);
        } else {
            header('Location: /esp32-ir/dashboard.php');
            exit;
        }
    }
}

/**
 * Retorna os dados do usuário logado armazenados na sessão
 */
function getSessionUser(): array
{
    startSession();
    return [
        'id'        => $_SESSION['usuario_id'] ?? null,
        'username'  => $_SESSION['username']   ?? null,
        'nome_real' => $_SESSION['nome_real']  ?? null,
        'perfil'    => (int)($_SESSION['perfil'] ?? 0),
    ];
}

/**
 * Detecta se a requisição atual é para a API (espera resposta JSON)
 */
function isApiRequest(): bool
{
    return str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/');
}
