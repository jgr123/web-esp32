<?php
/**
 * Funções utilitárias globais do sistema IR Control
 */

/**
 * Envia uma resposta JSON padronizada e encerra a execução.
 *
 * @param array $data  Payload a serializar
 * @param int   $code  HTTP status code (padrão 200)
 */
function jsonResponse(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Lê e decodifica o corpo da requisição como JSON.
 * Retorna array vazio se o corpo estiver ausente ou mal-formado.
 */
function getRequestBody(): array
{
    $raw = file_get_contents('php://input');
    return json_decode($raw, true) ?? [];
}

/**
 * Retorna o método HTTP da requisição em maiúsculas.
 */
function getMethod(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD']);
}

/**
 * Sanitiza uma string para exibição segura no HTML.
 */
function sanitize(string $str): string
{
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}

/**
 * Valida se os campos obrigatórios estão presentes e não-vazios.
 *
 * @param array  $data    Array de dados recebidos
 * @param array  $fields  Lista de chaves obrigatórias
 * @return string|null    Mensagem de erro ou null se tudo válido
 */
function validateRequired(array $data, array $fields): ?string
{
    foreach ($fields as $field) {
        if (!isset($data[$field]) || trim((string)$data[$field]) === '') {
            return "O campo '{$field}' é obrigatório.";
        }
    }
    return null;
}
