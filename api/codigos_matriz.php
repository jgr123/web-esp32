<?php
/**
 * /api/codigos_matriz.php — Importação e Processamento de Códigos IR em Formato de Matriz
 *
 * Suporta POST com:
 * {
 *   "texto": "tipo, marca, modelo, protocolo, modo, temp, vel, codigo\n...",
 *   "dry_run": true | false (padrão false)
 * }
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

startSession();
requireLogin();
requireAdmin();

if (getMethod() !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Método não suportado. Utilize POST.'], 405);
}

$body    = getRequestBody();
$rawText = trim($body['texto'] ?? '');
$dryRun  = !empty($body['dry_run']);

if ($rawText === '' && empty($body['linhas'])) {
    jsonResponse(['success' => false, 'message' => 'Nenhum dado informado na matriz.'], 422);
}

// Normalização da velocidade do ventilador
function parseVelocidade(?string $raw): ?array {
    if (!$raw) return null;
    $v = mb_strtolower(trim($raw), 'UTF-8');
    if ($v === '-' || $v === '--' || $v === '' || $v === 'null' || $v === 'none') return null;

    if (in_array($v, ['auto', 'automático', 'automatico', '0'])) {
        return ['nivel' => 0, 'desc' => 'Auto'];
    }
    if (in_array($v, ['mínimo', 'minimo', 'mínima', 'minima', '1', 'min'])) {
        return ['nivel' => 1, 'desc' => 'Mínima'];
    }
    if (in_array($v, ['baixo', 'baixa', '2', 'low'])) {
        return ['nivel' => 2, 'desc' => 'Baixa'];
    }
    if (in_array($v, ['médio', 'medio', 'média', 'media', '3', 'med'])) {
        return ['nivel' => 3, 'desc' => 'Média'];
    }
    if (in_array($v, ['alto', 'alta', '4', 'high'])) {
        return ['nivel' => 4, 'desc' => 'Alta'];
    }
    if (in_array($v, ['máximo', 'maximo', 'máxima', 'maxima', '5', 'max'])) {
        return ['nivel' => 5, 'desc' => 'Máxima'];
    }
    return ['nivel' => null, 'desc' => trim($raw)];
}

// Normalização do modo ou ação de operação
function parseModoOperacao(?string $raw): array {
    if (!$raw) return ['tipo' => 'none', 'nome' => null, 'is_modo' => false];
    $m = trim($raw);
    if ($m === '-' || $m === '--' || $m === '' || $m === 'null') {
        return ['tipo' => 'none', 'nome' => null, 'is_modo' => false];
    }

    $lower = mb_strtolower($m, 'UTF-8');

    // Correções de digitação comuns e modos clássicos
    if ($lower === 'automárico' || $lower === 'automatico' || $lower === 'automático') {
        return ['tipo' => 'modo', 'nome' => 'Automático', 'is_modo' => true];
    }
    if ($lower === 'frio' || $lower === 'cool') {
        return ['tipo' => 'modo', 'nome' => 'Frio', 'is_modo' => true];
    }
    if ($lower === 'quente' || $lower === 'heat') {
        return ['tipo' => 'modo', 'nome' => 'Quente', 'is_modo' => true];
    }
    if ($lower === 'ventilar' || $lower === 'fan') {
        return ['tipo' => 'modo', 'nome' => 'Ventilar', 'is_modo' => true];
    }
    if ($lower === 'desumidificar' || $lower === 'dry' || $lower === 'seco') {
        return ['tipo' => 'modo', 'nome' => 'Desumidificar', 'is_modo' => true];
    }

    // Ações diretas de energia e controle
    if ($lower === 'ligar' || $lower === 'on' || $lower === 'power on') {
        return ['tipo' => 'acao', 'nome' => 'Ligar', 'is_modo' => false, 'categoria' => 'Energia'];
    }
    if ($lower === 'desligar' || $lower === 'off' || $lower === 'power off') {
        return ['tipo' => 'acao', 'nome' => 'Desligar', 'is_modo' => false, 'categoria' => 'Energia'];
    }
    if (strpos($lower, 'swing') !== false || strpos($lower, 'oscil') !== false) {
        return ['tipo' => 'acao', 'nome' => $m, 'is_modo' => false, 'categoria' => 'Ventilação'];
    }

    return ['tipo' => 'acao', 'nome' => $m, 'is_modo' => false, 'categoria' => 'Controle'];
}

// Normalização do código HEX ou RAW
function parseCodigoIR(string $raw): string {
    $c = trim($raw);
    if (preg_match('/^\[.*\]$/', $c)) {
        return $c; // Formato RAW JSON
    }
    // Remove aspas ou espaços extras
    $c = trim($c, "\"' \t\n\r\0\x0B");
    $c = preg_replace('/^0x/i', '', $c);
    return '0x' . strtoupper($c);
}

// Quebra em linhas e processa
$rawLines = $rawText !== '' ? explode("\n", $rawText) : (array)$body['linhas'];
$items    = [];
$errors   = [];

foreach ($rawLines as $lineIdx => $lineStr) {
    $lineNum = $lineIdx + 1;
    $lineStr = trim($lineStr);
    if ($lineStr === '') continue;

    // Detecta delimitador (, ; ou \t)
    $cols = str_getcsv($lineStr, ',');
    if (count($cols) < 8) {
        $cols = str_getcsv($lineStr, ';');
    }
    if (count($cols) < 8) {
        $cols = explode("\t", $lineStr);
    }
    $cols = array_map(function($val) {
        return trim(trim($val), "\"'");
    }, $cols);

    // Se for cabeçalho, pula
    $firstCol = mb_strtolower($cols[0] ?? '', 'UTF-8');
    if (in_array($firstCol, ['tipo', 'tipo_dispositivo', 'tipo de dispositivo'])) {
        continue;
    }

    if (count($cols) < 8) {
        $errors[] = "Linha {$lineNum}: esperadas 8 colunas, encontradas " . count($cols) . " ('" . htmlspecialchars($lineStr) . "')";
        continue;
    }

    $tipoNome   = $cols[0];
    $marcaNome  = $cols[1];
    $modeloNome = $cols[2];
    $protocolo  = $cols[3];
    $rawModo    = $cols[4];
    $rawTemp    = $cols[5];
    $rawVel     = $cols[6];
    $rawHex     = $cols[7];

    if (!$tipoNome || !$marcaNome || !$modeloNome || !$rawHex) {
        $errors[] = "Linha {$lineNum}: Tipo, Marca, Modelo e Código HEX são obrigatórios.";
        continue;
    }

    // Normalizações individuais
    $modoInfo = parseModoOperacao($rawModo);
    $velInfo  = parseVelocidade($rawVel);

    // Temperatura
    $tempVal = null;
    if ($rawTemp !== '-' && $rawTemp !== '--' && $rawTemp !== '' && $rawTemp !== 'null') {
        $tempClean = (int)filter_var($rawTemp, FILTER_SANITIZE_NUMBER_INT);
        if ($tempClean >= 16 && $tempClean <= 30) {
            $tempVal = $tempClean;
        } elseif ($tempClean > 0) {
            $tempVal = $tempClean; // Fora do intervalo padrão, mas numérico
        }
    }

    $codigoFinal = parseCodigoIR($rawHex);

    // Determina o nome e categoria sugerida para a função IR
    $nomeFuncao = '';
    $categoria  = 'Geral';

    if ($modoInfo['is_modo']) {
        $partes = ['Modo ' . $modoInfo['nome']];
        if ($tempVal) $partes[] = "{$tempVal}°C";
        if ($velInfo && $velInfo['desc']) $partes[] = "Vel. " . $velInfo['desc'];

        $nomeFuncao = implode(' | ', $partes);
        $categoria  = $tempVal ? 'Temperatura' : ($modoInfo['nome'] === 'Ventilar' ? 'Ventilação' : 'Modo');
    } else {
        // Ação direta (ex: Desligar, Swing Toggle)
        $nomeFuncao = $modoInfo['nome'] ?: 'Comando IR';
        $categoria  = $modoInfo['categoria'] ?? 'Controle';
    }

    $items[] = [
        'linha'          => $lineNum,
        'tipo'           => $tipoNome,
        'marca'          => $marcaNome,
        'modelo'         => $modeloNome,
        'protocolo'      => $protocolo ?: null,
        'modo_nome'      => $modoInfo['nome'],
        'is_modo'        => $modoInfo['is_modo'],
        'temperatura'    => $tempVal,
        'velocidade'     => $velInfo ? $velInfo['desc'] : null,
        'vel_nivel'      => $velInfo ? $velInfo['nivel'] : null,
        'codigo'         => $codigoFinal,
        'funcao_sugerida'=> $nomeFuncao,
        'categoria'      => $categoria,
    ];
}

if (!empty($errors) && empty($items)) {
    jsonResponse([
        'success' => false,
        'message' => 'Erros encontrados na matriz.',
        'erros'   => $errors
    ], 422);
}

// Se for modo Dry Run (apenas pré-visualização)
if ($dryRun) {
    jsonResponse([
        'success'     => true,
        'dry_run'     => true,
        'total'       => count($items),
        'itens'       => $items,
        'erros'       => $errors,
        'message'     => count($items) . ' linhas interpretadas com sucesso para pré-visualização.'
    ]);
}

// Execução no banco de dados com Transaction
$db = getDB();
$db->beginTransaction();

try {
    // Caches em memória durante a importação
    $cacheTipos     = [];
    $cacheMarcas    = [];
    $cacheModelos   = [];
    $cacheModos     = [];
    $cacheTemps     = [];
    $cacheVels      = [];
    $cacheFuncoes   = [];

    // Prepared statements para reutilização
    $stmtTipoFind  = $db->prepare("SELECT id FROM tipos_dispositivo WHERE LOWER(nome) = LOWER(?) LIMIT 1");
    $stmtTipoIns   = $db->prepare("INSERT INTO tipos_dispositivo (nome) VALUES (?)");

    $stmtMarcaFind = $db->prepare("SELECT id FROM marcas WHERE LOWER(nome) = LOWER(?) LIMIT 1");
    $stmtMarcaIns  = $db->prepare("INSERT INTO marcas (nome) VALUES (?)");

    $stmtModFind   = $db->prepare("SELECT id FROM modelos WHERE marca_id = ? AND LOWER(nome) = LOWER(?) LIMIT 1");
    $stmtModIns    = $db->prepare("INSERT INTO modelos (marca_id, tipo_id, nome) VALUES (?, ?, ?)");

    $stmtModoFind  = $db->prepare("SELECT id FROM modos_climatizador WHERE LOWER(nome) = LOWER(?) LIMIT 1");
    $stmtModoIns   = $db->prepare("INSERT INTO modos_climatizador (nome) VALUES (?)");

    $stmtTempFind  = $db->prepare("SELECT id FROM temperaturas WHERE valor_c = ? LIMIT 1");

    $stmtVelFind   = $db->prepare("SELECT id FROM velocidades WHERE nivel = ? OR LOWER(descricao) = LOWER(?) LIMIT 1");

    $stmtFunCombo  = $db->prepare("SELECT id FROM funcoes_ir WHERE modo_id <=> ? AND temperatura_id <=> ? AND velocidade_id <=> ? LIMIT 1");
    $stmtFunNome   = $db->prepare("SELECT id FROM funcoes_ir WHERE LOWER(nome) = LOWER(?) LIMIT 1");
    $stmtFunIns    = $db->prepare("INSERT INTO funcoes_ir (nome, categoria, modo_id, temperatura_id, velocidade_id) VALUES (?, ?, ?, ?, ?)");

    $stmtCodUpsert = $db->prepare("
        INSERT INTO codigos_ir (modelo_id, funcao_id, protocolo, codigo, frequencia)
        VALUES (?, ?, ?, ?, 38)
        ON DUPLICATE KEY UPDATE
            protocolo  = VALUES(protocolo),
            codigo     = VALUES(codigo),
            frequencia = VALUES(frequencia)
    ");

    $totalCodigos = 0;
    $novosModelos = 0;
    $novasFuncoes = 0;

    foreach ($items as &$item) {
        // 1. Tipo
        $tKey = mb_strtolower($item['tipo'], 'UTF-8');
        if (!isset($cacheTipos[$tKey])) {
            $stmtTipoFind->execute([$item['tipo']]);
            $tId = $stmtTipoFind->fetchColumn();
            if (!$tId) {
                $stmtTipoIns->execute([$item['tipo']]);
                $tId = (int)$db->lastInsertId();
            }
            $cacheTipos[$tKey] = (int)$tId;
        }
        $tipoId = $cacheTipos[$tKey];

        // 2. Marca
        $mKey = mb_strtolower($item['marca'], 'UTF-8');
        if (!isset($cacheMarcas[$mKey])) {
            $stmtMarcaFind->execute([$item['marca']]);
            $mId = $stmtMarcaFind->fetchColumn();
            if (!$mId) {
                $stmtMarcaIns->execute([$item['marca']]);
                $mId = (int)$db->lastInsertId();
            }
            $cacheMarcas[$mKey] = (int)$mId;
        }
        $marcaId = $cacheMarcas[$mKey];

        // 3. Modelo
        $modKey = $marcaId . '_' . mb_strtolower($item['modelo'], 'UTF-8');
        if (!isset($cacheModelos[$modKey])) {
            $stmtModFind->execute([$marcaId, $item['modelo']]);
            $modId = $stmtModFind->fetchColumn();
            if (!$modId) {
                $stmtModIns->execute([$marcaId, $tipoId, $item['modelo']]);
                $modId = (int)$db->lastInsertId();
                $novosModelos++;
            }
            $cacheModelos[$modKey] = (int)$modId;
        }
        $modeloId = $cacheModelos[$modKey];

        // 4. Modo Climatizador (apenas se for modo de operação)
        $modoId = null;
        if ($item['is_modo'] && $item['modo_nome']) {
            $modoKey = mb_strtolower($item['modo_nome'], 'UTF-8');
            if (!isset($cacheModos[$modoKey])) {
                $stmtModoFind->execute([$item['modo_nome']]);
                $moId = $stmtModoFind->fetchColumn();
                if (!$moId) {
                    $stmtModoIns->execute([$item['modo_nome']]);
                    $moId = (int)$db->lastInsertId();
                }
                $cacheModos[$modoKey] = (int)$moId;
            }
            $modoId = $cacheModos[$modoKey];
        }

        // 5. Temperatura ID
        $tempId = null;
        if ($item['temperatura']) {
            $tempVal = (int)$item['temperatura'];
            if (!isset($cacheTemps[$tempVal])) {
                $stmtTempFind->execute([$tempVal]);
                $cacheTemps[$tempVal] = (int)($stmtTempFind->fetchColumn() ?: null);
            }
            $tempId = $cacheTemps[$tempVal] ?: null;
        }

        // 6. Velocidade ID
        $velId = null;
        if ($item['velocidade'] !== null) {
            $velKey = ($item['vel_nivel'] !== null ? 'N_' . $item['vel_nivel'] : 'D_' . mb_strtolower($item['velocidade'], 'UTF-8'));
            if (!isset($cacheVels[$velKey])) {
                $stmtVelFind->execute([$item['vel_nivel'] ?? -1, $item['velocidade']]);
                $cacheVels[$velKey] = (int)($stmtVelFind->fetchColumn() ?: null);
            }
            $velId = $cacheVels[$velKey] ?: null;
        }

        // 7. Função IR
        $funcaoId = null;

        // Se for um modo com temperatura e/ou velocidade combinados:
        if ($modoId !== null || $tempId !== null || $velId !== null) {
            $stmtFunCombo->execute([$modoId, $tempId, $velId]);
            $funcaoId = (int)($stmtFunCombo->fetchColumn() ?: 0);
        }

        // Se não achou pela combinação ou é uma ação direta (Desligar, Swing Toggle, etc.):
        if (!$funcaoId) {
            $stmtFunNome->execute([$item['funcao_sugerida']]);
            $funcaoId = (int)($stmtFunNome->fetchColumn() ?: 0);
        }

        // Se ainda não existir no banco, cria a função IR
        if (!$funcaoId) {
            $stmtFunIns->execute([
                $item['funcao_sugerida'],
                $item['categoria'],
                $modoId,
                $tempId,
                $velId,
            ]);
            $funcaoId = (int)$db->lastInsertId();
            $novasFuncoes++;
        }

        // 8. Grava / Atualiza o código IR (upsert)
        $stmtCodUpsert->execute([
            $modeloId,
            $funcaoId,
            $item['protocolo'],
            $item['codigo'],
        ]);
        $totalCodigos++;

        $item['modelo_id'] = $modeloId;
        $item['funcao_id'] = $funcaoId;
    }

    $db->commit();

    jsonResponse([
        'success'       => true,
        'message'       => "Matriz processada com sucesso! {$totalCodigos} código(s) IR gravados/atualizados.",
        'total_codigos' => $totalCodigos,
        'novos_modelos' => $novosModelos,
        'novas_funcoes' => $novasFuncoes,
        'erros'         => $errors,
    ]);
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    jsonResponse([
        'success' => false,
        'message' => 'Erro ao processar matriz no banco de dados: ' . $e->getMessage()
    ], 500);
}
