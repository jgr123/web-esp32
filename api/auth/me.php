<?php
/**
 * GET  /api/auth/me.php — Retorna dados do usuário logado
 */
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';

requireLogin();
jsonResponse(['success' => true, 'data' => getSessionUser()]);
