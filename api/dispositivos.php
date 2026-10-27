<?php
/**
 * /api/dispositivos.php — CRUD de Dispositivos e alocação em Salas
 *
 * GET              → Lista todos os dispositivos com sala e slot
 * GET ?sala_id=X   → Lista dispositivos de uma sala específica
 * GET ?id=X        → Detalhe de um dispositivo com comandos IR disponíveis
 * POST             → Cria dispositivo E o aloca em uma sala/slot (Admin)
 * PUT  ?id=X       → Atualiza dados do dispositivo (Admin)
 * DELETE ?id=X     → Remove dispositivo e sua alocação (Admin)
 *
 * ─── Regra de negócio: máx. 4 dispositivos por sala ───────────────────────
 * Antes do INSERT em sala_dispositivos, o sistema conta os registros ativos
 * daquela sala. Se já houver 4, rejeita com HTTP 422.
 * O banco reforça isso via UNIQUE(sala_id, slot) e CHECK(slot BETWEEN 1 AND 4).
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

startSession();
requireLogin();

$method  = getMethod();
$id      = isset($_GET['id'])      ? (int)$_GET['id']      : null;
$sala_id = isset($_GET['sala_id']) ? (int)$_GET['sala_id'] : null;
$db      = getDB();

switch ($method) {
    // ─────────────────────────────────────────────────────────
    case 'GET':
        if ($id) {
            // Detalhe + comandos IR disponíveis para este dispositivo
            $stmt = $db->prepare("SELECT * FROM vw_sala_dispositivos WHERE dispositivo_id = ?");
            $stmt->execute([$id]);
            $disp = $stmt->fetch();
            if (!$disp) jsonResponse(['success' => false, 'message' => 'Dispositivo não encontrado.'], 404);

            // Busca todos os códigos IR do modelo deste dispositivo
            $stmtCmds = $db->prepare("
                SELECT c.id AS codigo_ir_id, f.nome AS funcao, f.categoria,
                       mc.nome AS modo, t.valor_c AS temperatura, v.descricao AS velocidade,
                       c.protocolo, c.codigo, c.frequencia
                FROM codigos_ir c
                JOIN funcoes_ir f ON f.id = c.funcao_id
                LEFT JOIN modos_climatizador mc ON mc.id = f.modo_id
                LEFT JOIN temperaturas        t  ON t.id  = f.temperatura_id
                LEFT JOIN velocidades         v  ON v.id  = f.velocidade_id
                JOIN dispositivos d ON d.modelo_id = c.modelo_id
                WHERE d.id = ?
                ORDER BY f.categoria, f.nome
            ");
            $stmtCmds->execute([$id]);
            $disp['comandos'] = $stmtCmds->fetchAll();

            jsonResponse(['success' => true, 'data' => $disp]);
        }

        if ($sala_id) {
            $stmt = $db->prepare("SELECT * FROM vw_sala_dispositivos WHERE sala_id = ? ORDER BY slot");
            $stmt->execute([$sala_id]);
            jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);
        }

        // Lista geral enriquecida
        $rows = $db->query("
            SELECT
                s.id          AS sala_id,
                s.nome        AS sala,
                sd.id         AS sala_dispositivo_id,
                sd.slot,
                d.id          AS dispositivo_id,
                d.modelo_id,
                d.numero_serie,
                d.descricao,
                td.nome       AS tipo,
                m.nome        AS marca,
                mo.nome       AS modelo,
                mo.referencia AS ref_fabricante,
                sd.ativo
            FROM sala_dispositivos sd
            JOIN salas             s   ON s.id  = sd.sala_id
            JOIN dispositivos      d   ON d.id  = sd.dispositivo_id
            JOIN modelos           mo  ON mo.id = d.modelo_id
            JOIN marcas            m   ON m.id  = mo.marca_id
            JOIN tipos_dispositivo td  ON td.id = mo.tipo_id
            ORDER BY s.nome, sd.slot
        ")->fetchAll();
        jsonResponse(['success' => true, 'data' => $rows]);
        break;

    // ─────────────────────────────────────────────────────────
    case 'POST':
        requireAdmin();
        $body  = getRequestBody();
        $error = validateRequired($body, ['modelo_id', 'sala_id', 'slot']);
        if ($error) jsonResponse(['success' => false, 'message' => $error], 422);

        $sala_id  = (int)$body['sala_id'];
        $slot     = (int)$body['slot'];
        $modelo_id = (int)$body['modelo_id'];

        // Validar faixa de slot
        if ($slot < 1 || $slot > 4) {
            jsonResponse(['success' => false, 'message' => 'Slot inválido. Use valores entre 1 e 4.'], 422);
        }

        // ── Regra de negócio: Verificar limite de 4 dispositivos por sala ──
        $stmtCount = $db->prepare("SELECT COUNT(*) FROM sala_dispositivos WHERE sala_id = ? AND ativo = 1");
        $stmtCount->execute([$sala_id]);
        $totalAtual = (int)$stmtCount->fetchColumn();

        if ($totalAtual >= 4) {
            jsonResponse([
                'success' => false,
                'message' => 'Esta sala já atingiu o limite de 4 dispositivos.'
            ], 422);
        }

        // Verificar se o slot já está ocupado nessa sala
        $stmtSlot = $db->prepare("SELECT id FROM sala_dispositivos WHERE sala_id = ? AND slot = ?");
        $stmtSlot->execute([$sala_id, $slot]);
        if ($stmtSlot->fetch()) {
            jsonResponse(['success' => false, 'message' => "O slot {$slot} já está ocupado nesta sala."], 409);
        }

        try {
            $db->beginTransaction();

            // 1. Cria o dispositivo físico
            $stmtDisp = $db->prepare("INSERT INTO dispositivos (modelo_id, numero_serie, descricao) VALUES (?, ?, ?)");
            $stmtDisp->execute([
                $modelo_id,
                trim($body['numero_serie'] ?? '') ?: null,
                trim($body['descricao']    ?? '') ?: null,
            ]);
            $dispositivo_id = (int)$db->lastInsertId();

            // 2. Aloca o dispositivo na sala/slot
            $stmtAloc = $db->prepare("INSERT INTO sala_dispositivos (sala_id, dispositivo_id, slot) VALUES (?, ?, ?)");
            $stmtAloc->execute([$sala_id, $dispositivo_id, $slot]);

            $db->commit();
            jsonResponse([
                'success' => true,
                'message' => "Dispositivo adicionado à sala no slot {$slot}.",
                'id'      => $dispositivo_id,
            ], 201);
        } catch (PDOException $e) {
            $db->rollBack();
            if ($e->getCode() === '23000') {
                jsonResponse(['success' => false, 'message' => 'Conflito de slot ou dispositivo duplicado.'], 409);
            }
            throw $e;
        }
        break;

    // ─────────────────────────────────────────────────────────
    case 'PUT':
        requireAdmin();
        if (!$id) jsonResponse(['success' => false, 'message' => 'ID não informado.'], 400);
        $body = getRequestBody();

        $stmt = $db->prepare("UPDATE dispositivos SET numero_serie=?, descricao=? WHERE id=?");
        $stmt->execute([
            trim($body['numero_serie'] ?? '') ?: null,
            trim($body['descricao']    ?? '') ?: null,
            $id,
        ]);

        // Atualizar status ativo na alocação
        if (isset($body['ativo'])) {
            $stmtA = $db->prepare("UPDATE sala_dispositivos SET ativo=? WHERE dispositivo_id=?");
            $stmtA->execute([(int)$body['ativo'], $id]);
        }

        jsonResponse(['success' => true, 'message' => 'Dispositivo atualizado com sucesso.']);
        break;

    // ─────────────────────────────────────────────────────────
    case 'DELETE':
        requireAdmin();
        if (!$id) jsonResponse(['success' => false, 'message' => 'ID não informado.'], 400);

        // ON DELETE CASCADE na sala_dispositivos garante a remoção da alocação
        $stmt = $db->prepare("DELETE FROM dispositivos WHERE id = ?");
        $stmt->execute([$id]);
        jsonResponse(['success' => true, 'message' => 'Dispositivo removido com sucesso.']);
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Método não suportado.'], 405);
}
