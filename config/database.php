<?php
/**
 * Configuração da conexão com o banco de dados MySQL
 * Utiliza PDO com padrão Singleton para eficiência
 */

define('DB_HOST',    'localhost');
define('DB_NAME',    'ir_control');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_CHARSET', 'utf8mb4');

/**
 * Retorna a instância PDO (Singleton).
 * Em caso de falha, retorna JSON de erro e encerra a execução.
 */
function getDB(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            die(json_encode(['success' => false, 'message' => 'Erro de conexão com o banco de dados.']));
        }
    }

    return $pdo;
}
