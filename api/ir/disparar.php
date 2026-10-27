<?php
/**
 * POST /api/ir/disparar.php — Dispara um comando IR
 *
 * Recebe o codigo_ir_id, registra o disparo e retorna o payload
 * que o ESP32 deve usar para transmitir o sinal IR.
 *
 * Body (JSON): { "codigo_ir_id": 3, "dispositivo_id": 1 }
 *
 * ── Integração com ESP32 ──────────────────────────────────────────────────
 * Opção A (Polling): O ESP32 faz GET /api/ir/pendente.php?sala_id=X a cada
 *   500ms e executa o código retornado. Esta implementação armazena o
 *   comando em uma tabela de pendências.
 *
 * Opção B (HTTP Direto): Se ESP32_IP estiver definido, o servidor faz
 *   um request HTTP diretamente para o ESP32 com o payload IR.
 *   Descomente a seção correspondente abaixo.
 *
 * Opção C (MQTT): Publique o payload em um tópico MQTT usando uma biblioteca
 *   PHP como phpMQTT. Substituir a seção de disparo abaixo.
 * ─────────────────────────────────────────────────────────────────────────
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';

startSession();
requireLogin();

if (getMethod() !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Método não suportado.'], 405);
}

$body = getRequestBody();
$db   = getDB();

$codigo_ir_id   = isset($body['codigo_ir_id']) ? (int)$body['codigo_ir_id'] : null;
$dispositivo_id = isset($body['dispositivo_id']) ? (int)$body['dispositivo_id'] : null;
$temperatura    = isset($body['temperatura']) ? (int)$body['temperatura'] : null;
$modo           = isset($body['modo']) ? trim((string)$body['modo']) : null;

// Se não foi passado codigo_ir_id direto, busca dinamicamente pelo dispositivo, temperatura e/ou modo
if (!$codigo_ir_id && $dispositivo_id && ($temperatura || $modo)) {
    $stmtMod = $db->prepare("SELECT modelo_id FROM dispositivos WHERE id = ?");
    $stmtMod->execute([$dispositivo_id]);
    $modelo_id = (int)$stmtMod->fetchColumn();

    if ($modelo_id) {
        // 1. Tenta buscar código com temperatura e modo combinados
        if ($temperatura && $modo) {
            $stmtC = $db->prepare("
                SELECT c.id FROM codigos_ir c
                JOIN funcoes_ir f ON f.id = c.funcao_id
                LEFT JOIN temperaturas t ON t.id = f.temperatura_id
                LEFT JOIN modos_climatizador mc ON mc.id = f.modo_id
                WHERE c.modelo_id = ? AND t.valor_c = ? AND mc.nome = ?
                LIMIT 1
            ");
            $stmtC->execute([$modelo_id, $temperatura, $modo]);
            $codigo_ir_id = (int)$stmtC->fetchColumn();
        }

        // 2. Se não encontrou ou é apenas temperatura: busca código de temperatura
        if (!$codigo_ir_id && $temperatura) {
            $stmtC = $db->prepare("
                SELECT c.id FROM codigos_ir c
                JOIN funcoes_ir f ON f.id = c.funcao_id
                LEFT JOIN temperaturas t ON t.id = f.temperatura_id
                WHERE c.modelo_id = ? AND (t.valor_c = ? OR f.nome LIKE ?)
                ORDER BY (t.valor_c = ?) DESC
                LIMIT 1
            ");
            $stmtC->execute([$modelo_id, $temperatura, "%{$temperatura}°C%", $temperatura]);
            $codigo_ir_id = (int)$stmtC->fetchColumn();
        }

        // 3. Se não encontrou ou é apenas modo: busca código do modo
        if (!$codigo_ir_id && $modo) {
            $stmtC = $db->prepare("
                SELECT c.id FROM codigos_ir c
                JOIN funcoes_ir f ON f.id = c.funcao_id
                LEFT JOIN modos_climatizador mc ON mc.id = f.modo_id
                WHERE c.modelo_id = ? AND (mc.nome = ? OR f.nome LIKE ?)
                ORDER BY (mc.nome = ?) DESC
                LIMIT 1
            ");
            $stmtC->execute([$modelo_id, $modo, "%Modo {$modo}%", $modo]);
            $codigo_ir_id = (int)$stmtC->fetchColumn();
        }
    }
}

if (!$codigo_ir_id) {
    if ($temperatura || $modo) {
        $desc = ($modo ? "Modo '$modo' " : '') . ($temperatura ? "Temperatura {$temperatura}°C" : '');
        jsonResponse([
            'success' => false,
            'message' => "Código IR não encontrado para $desc neste modelo de aparelho. Cadastre no Painel Admin."
        ], 404);
    }
    jsonResponse(['success' => false, 'message' => "Parâmetros insuficientes. Informe 'codigo_ir_id' ou ('dispositivo_id' e 'temperatura'/'modo')."], 422);
}

// Buscar o código IR com contexto completo
$stmt = $db->prepare("
    SELECT c.id, c.protocolo, c.codigo, c.frequencia,
           f.nome AS funcao, f.categoria,
           mc.nome AS modo, t.valor_c AS temperatura, v.descricao AS velocidade,
           m.nome AS marca, mo.nome AS modelo
    FROM codigos_ir c
    JOIN funcoes_ir f ON f.id = c.funcao_id
    JOIN modelos mo ON mo.id = c.modelo_id
    JOIN marcas m ON m.id = mo.marca_id
    LEFT JOIN modos_climatizador mc ON mc.id = f.modo_id
    LEFT JOIN temperaturas        t  ON t.id  = f.temperatura_id
    LEFT JOIN velocidades         v  ON v.id  = f.velocidade_id
    WHERE c.id = ?
");
$stmt->execute([$codigo_ir_id]);
$codigo = $stmt->fetch();

if (!$codigo) {
    jsonResponse(['success' => false, 'message' => 'Código IR não encontrado.'], 404);
}

$usuario = getSessionUser();

// ── Registrar o disparo na tabela de log ─────────────────────────────────
// (Requer a tabela log_disparos — criada automaticamente se não existir)
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS log_disparos (
            id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
            usuario_id     INT UNSIGNED NOT NULL,
            codigo_ir_id   INT UNSIGNED NOT NULL,
            dispositivo_id INT UNSIGNED NULL,
            enviado_em     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            confirmado     TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            INDEX idx_enviado_em (enviado_em),
            INDEX idx_dispositivo (dispositivo_id)
        ) ENGINE=InnoDB COMMENT='Histórico de disparos IR'
    ");

    $stmtLog = $db->prepare("INSERT INTO log_disparos (usuario_id, codigo_ir_id, dispositivo_id) VALUES (?, ?, ?)");
    $stmtLog->execute([$usuario['id'], $codigo_ir_id, $dispositivo_id]);
    $log_id = (int)$db->lastInsertId();
} catch (PDOException $e) {
    $log_id = null; // Log falhou, mas não deve bloquear o disparo
}

// ── Montar payload para o ESP32 ───────────────────────────────────────────
$payload = [
    'protocolo'  => $codigo['protocolo'],
    'codigo'     => $codigo['codigo'],
    'frequencia' => (int)$codigo['frequencia'],
    'log_id'     => $log_id,
];

// ── Disparo HTTP direto para o ESP32 (se ESP32_IP estiver configurado) ────
$esp32_ip   = defined('ESP32_IP') && ESP32_IP !== '' ? ESP32_IP : null;
$esp_status = null;

if ($esp32_ip) {
    $url = "http://{$esp32_ip}/ir/send";
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 2,
    ]);
    $esp_response = curl_exec($ch);
    $esp_status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($esp_status === 200 && $log_id) {
        try {
            $db->prepare("UPDATE log_disparos SET confirmado = 1 WHERE id = ?")->execute([$log_id]);
        } catch (Exception $e) {}
    }
}

jsonResponse([
    'success'   => true,
    'message'   => "Comando '{$codigo['funcao']}' enviado para {$codigo['marca']} {$codigo['modelo']}.",
    'payload'   => $payload,
    'contexto'  => [
        'funcao'      => $codigo['funcao'],
        'categoria'   => $codigo['categoria'],
        'modo'        => $codigo['modo'],
        'temperatura' => $codigo['temperatura'] ? $codigo['temperatura'] . '°C' : null,
        'velocidade'  => $codigo['velocidade'],
        'marca'       => $codigo['marca'],
        'modelo'      => $codigo['modelo'],
    ],
]);
