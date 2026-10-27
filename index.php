<?php
/**
 * Ponto de entrada do sistema IR Control
 * Redireciona para dashboard.php se autenticado ou login.php se não autenticado.
 */

require_once __DIR__ . '/includes/auth.php';

startSession();

if (isLoggedIn()) {
    header('Location: /esp32-ir/dashboard.php');
} else {
    header('Location: /esp32-ir/login.php');
}
exit;
