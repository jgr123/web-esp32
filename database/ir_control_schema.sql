-- ============================================================
-- SISTEMA DE CONTROLE REMOTO VIA INFRAVERMELHO (IR)
-- ESP32 IR Controller — Banco de Dados MySQL
-- Versão: 1.0 | Normalização: 3FN (Terceira Forma Normal)
-- ============================================================

CREATE DATABASE IF NOT EXISTS ir_control
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE ir_control;

-- ============================================================
-- MODELO ENTIDADE-RELACIONAMENTO (MER) — RESUMO TEXTUAL
-- ============================================================
--
--  [usuarios]
--      |
--      | (sem ligação direta — acesso via sistema/sessão)
--      |
--  [salas] ─────────────────────────────────────────────────────────────┐
--      |                                                                  |
--      | 1:N                                                              |
--  [sala_dispositivos] (slot 1..4 por sala) ◄────────────────────────────┘
--      | N:1
--  [dispositivos]
--      | N:1
--  [modelos] ── N:1 ── [marcas]
--      |          └─── N:1 ── [tipos_dispositivo]
--      |
--      | 1:N
--  [codigos_ir]
--      | N:1
--  [funcoes_ir] ── N:1 (opt) ── [modos_climatizador]   (Quente/Frio/Ventilar/Desumidificar)
--               ── N:1 (opt) ── [temperaturas]          (16°C … 30°C)
--               ── N:1 (opt) ── [velocidades]           (0 = Auto … 5 = Máxima)
--
-- Regras de negócio:
--  • Cada sala pode ter até 4 dispositivos (slots 1-4)
--  • UNIQUE(sala_id, slot) garante slots únicos por sala
--  • Um aparelho físico só pode estar em uma sala por vez
--  • Códigos IR são vinculados ao modelo do aparelho
--  • Funções IR possuem modo, temperatura e velocidade opcionais
--    (NULL para funções simples como "Ligar" e "Desligar")
-- ============================================================


-- ------------------------------------------------------------
-- 1. USUÁRIOS — Autenticação e controle de acesso
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS usuarios (
    id         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    nome_real  VARCHAR(120)  NOT NULL,
    username   VARCHAR(60)   NOT NULL,
    senha_hash VARCHAR(255)  NOT NULL COMMENT 'Hash bcrypt/argon2 — nunca texto simples',
    email      VARCHAR(150)  NOT NULL,
    ativo      TINYINT(1)    NOT NULL DEFAULT 1,
    perfil     INT UNSIGNED  NOT NULL COMMENT '1 para administrador e 2 para usuário padrão',
    criado_em  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_username (username),
    UNIQUE KEY uq_email    (email),
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB
  COMMENT='Usuários do sistema com autenticação segura';


-- ------------------------------------------------------------
-- 2. SALAS — Infraestrutura física
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS salas (
    id        INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    nome      VARCHAR(80)   NOT NULL COMMENT 'Ex: Sala 101, Auditório A',
    descricao VARCHAR(255)  NULL,
    ativo     TINYINT(1)    NOT NULL DEFAULT 1,
    criado_em DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sala_nome (nome),
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB
  COMMENT='Salas e ambientes físicos do sistema';


-- ------------------------------------------------------------
-- 3. MARCAS — Fabricantes dos aparelhos
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS marcas (
    id   INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    nome VARCHAR(80)   NOT NULL COMMENT 'Ex: LG, Samsung, Epson, Midea',
    PRIMARY KEY (id),
    UNIQUE KEY uq_marca_nome (nome)
) ENGINE=InnoDB
  COMMENT='Marcas/fabricantes dos dispositivos';


-- ------------------------------------------------------------
-- 4. TIPOS DE DISPOSITIVO — Climatizador, Projetor, TV...
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tipos_dispositivo (
    id   INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    nome VARCHAR(60)   NOT NULL COMMENT 'Ex: Climatizador, Projetor, TV',
    PRIMARY KEY (id),
    UNIQUE KEY uq_tipo_nome (nome)
) ENGINE=InnoDB
  COMMENT='Categorias/tipos de dispositivos suportados';


-- ------------------------------------------------------------
-- 5. MODELOS — Modelo específico de cada aparelho
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS modelos (
    id         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    marca_id   INT UNSIGNED  NOT NULL,
    tipo_id    INT UNSIGNED  NOT NULL,
    nome       VARCHAR(100)  NOT NULL COMMENT 'Ex: Split Dual Inverter 9000 BTU',
    referencia VARCHAR(60)   NULL     COMMENT 'Código/SKU do fabricante',
    PRIMARY KEY (id),
    UNIQUE KEY uq_modelo_marca (marca_id, nome),
    CONSTRAINT fk_modelos_marca FOREIGN KEY (marca_id)
        REFERENCES marcas (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_modelos_tipo FOREIGN KEY (tipo_id)
        REFERENCES tipos_dispositivo (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX idx_tipo (tipo_id)
) ENGINE=InnoDB
  COMMENT='Modelos específicos de dispositivos por marca e tipo';


-- ------------------------------------------------------------
-- 6. DISPOSITIVOS — Aparelhos físicos cadastrados
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS dispositivos (
    id           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    modelo_id    INT UNSIGNED  NOT NULL,
    numero_serie VARCHAR(80)   NULL     COMMENT 'Nº de série físico (opcional)',
    descricao    VARCHAR(200)  NULL,
    criado_em    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_dispositivos_modelo FOREIGN KEY (modelo_id)
        REFERENCES modelos (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX idx_modelo (modelo_id)
) ENGINE=InnoDB
  COMMENT='Instâncias físicas dos aparelhos cadastrados';


-- ------------------------------------------------------------
-- 7. SALA_DISPOSITIVOS — Associação Sala × Dispositivo × Slot
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sala_dispositivos (
    id             INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    sala_id        INT UNSIGNED      NOT NULL,
    dispositivo_id INT UNSIGNED      NOT NULL,
    slot           TINYINT UNSIGNED  NOT NULL COMMENT 'Posição física: 1 a 4',
    ativo          TINYINT(1)        NOT NULL DEFAULT 1,
    instalado_em   DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    -- Um slot não pode ser duplicado dentro da mesma sala
    UNIQUE KEY uq_sala_slot         (sala_id, slot),
    -- Um aparelho físico só pode estar em uma sala por vez
    UNIQUE KEY uq_dispositivo_unico (dispositivo_id),
    CONSTRAINT fk_sd_sala FOREIGN KEY (sala_id)
        REFERENCES salas (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_sd_dispositivo FOREIGN KEY (dispositivo_id)
        REFERENCES dispositivos (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT chk_slot_range CHECK (slot BETWEEN 1 AND 4),
    INDEX idx_sala (sala_id),
    INDEX idx_slot (slot)
) ENGINE=InnoDB
  COMMENT='Dispositivos instalados por sala e slot (máx. 4 por sala)';


-- ------------------------------------------------------------
-- 8a. MODOS_CLIMATIZADOR — Modos de operação do climatizador
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS modos_climatizador (
    id   INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    nome VARCHAR(40)   NOT NULL COMMENT 'Ex: Quente, Frio, Ventilar, Desumidificar',
    PRIMARY KEY (id),
    UNIQUE KEY uq_modo_nome (nome)
) ENGINE=InnoDB
  COMMENT='Modos de operação disponíveis nos climatizadores';


-- ------------------------------------------------------------
-- 8b. TEMPERATURAS — Faixa de temperatura suportada (16°C–30°C)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS temperaturas (
    id        INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    valor_c   TINYINT       NOT NULL COMMENT 'Temperatura em graus Celsius',
    PRIMARY KEY (id),
    UNIQUE KEY uq_temperatura_valor (valor_c),
    CONSTRAINT chk_temp_range CHECK (valor_c BETWEEN 16 AND 30)
) ENGINE=InnoDB
  COMMENT='Faixa de temperaturas suportadas (16°C a 30°C)';


-- ------------------------------------------------------------
-- 8c. VELOCIDADES — Níveis de velocidade do ventilador
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS velocidades (
    id       INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    nivel    TINYINT       NOT NULL COMMENT '0 = Auto, 1 = Mínima … 5 = Máxima',
    descricao VARCHAR(40)  NULL     COMMENT 'Ex: Auto, Mínima, Baixa, Média, Alta, Máxima',
    PRIMARY KEY (id),
    UNIQUE KEY uq_velocidade_nivel (nivel),
    CONSTRAINT chk_vel_range CHECK (nivel BETWEEN 0 AND 5)
) ENGINE=InnoDB
  COMMENT='Níveis de velocidade do ventilador (0=Auto a 5=Máxima)';


-- ------------------------------------------------------------
-- 8d. FUNCOES_IR — Funções que os códigos IR executam
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS funcoes_ir (
    id             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    nome           VARCHAR(100)  NOT NULL COMMENT 'Rótulo descritivo da função',
    categoria      VARCHAR(60)   NULL     COMMENT 'Ex: Energia, Temperatura, Ventilação',
    -- Atributos opcionais — NULL para funções simples (Ligar/Desligar)
    modo_id        INT UNSIGNED  NULL     COMMENT 'FK → modos_climatizador (somente climatizadores)',
    temperatura_id INT UNSIGNED  NULL     COMMENT 'FK → temperaturas (quando aplicável)',
    velocidade_id  INT UNSIGNED  NULL     COMMENT 'FK → velocidades (quando aplicável)',
    PRIMARY KEY (id),
    UNIQUE KEY uq_funcao_nome (nome),
    CONSTRAINT fk_funcoes_modo FOREIGN KEY (modo_id)
        REFERENCES modos_climatizador (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_funcoes_temp FOREIGN KEY (temperatura_id)
        REFERENCES temperaturas (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_funcoes_vel FOREIGN KEY (velocidade_id)
        REFERENCES velocidades (id) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX idx_categoria     (categoria),
    INDEX idx_modo          (modo_id),
    INDEX idx_temperatura   (temperatura_id),
    INDEX idx_velocidade    (velocidade_id)
) ENGINE=InnoDB
  COMMENT='Funções/ações dos comandos IR com modo, temperatura e velocidade normalizados';


-- ------------------------------------------------------------
-- 9. CODIGOS_IR — Códigos IR indexados por Modelo + Função
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS codigos_ir (
    id         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    modelo_id  INT UNSIGNED  NOT NULL,
    funcao_id  INT UNSIGNED  NOT NULL,
    protocolo  VARCHAR(30)   NULL     COMMENT 'Ex: NEC, SONY, RAW, DAIKIN, SAMSUNG_AC',
    codigo     TEXT          NOT NULL COMMENT 'Código RAW (array JSON), HEX ou string do sinal IR',
    frequencia SMALLINT      NULL     DEFAULT 38 COMMENT 'Frequência portadora em kHz (padrão 38)',
    criado_em  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    -- Cada modelo só pode ter um código por função
    UNIQUE KEY uq_modelo_funcao (modelo_id, funcao_id),
    CONSTRAINT fk_codigos_modelo FOREIGN KEY (modelo_id)
        REFERENCES modelos (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_codigos_funcao FOREIGN KEY (funcao_id)
        REFERENCES funcoes_ir (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX idx_modelo    (modelo_id),
    INDEX idx_funcao    (funcao_id),
    INDEX idx_protocolo (protocolo)
) ENGINE=InnoDB
  COMMENT='Códigos IR mapeados por modelo de dispositivo e função';


-- ============================================================
-- DADOS DE EXEMPLO
-- Cenário: Sala 101 com 2 climatizadores (LG e Samsung)
-- ============================================================

-- 1. Tipos
INSERT INTO tipos_dispositivo (nome) VALUES
    ('Climatizador'),
    ('Projetor'),
    ('TV');

-- 2. Marcas
INSERT INTO marcas (nome) VALUES
    ('LG'),
    ('Samsung'),
    ('Epson'),
    ('Midea');

-- 3. Modelos (marca_id e tipo_id referentes aos IDs acima)
INSERT INTO modelos (marca_id, tipo_id, nome, referencia) VALUES
    -- LG Climatizador  → marca_id=1, tipo_id=1
    (1, 1, 'Split Dual Inverter 12000 BTU', 'S4-Q12JA3AA'),
    -- Samsung Climatizador → marca_id=2, tipo_id=1
    (2, 1, 'WindFree Split 9000 BTU', 'AR09BXEAAWKNAZ');

-- 4a. Modos do climatizador
INSERT INTO modos_climatizador (nome) VALUES
    ('Quente'),          -- id=1
    ('Frio'),            -- id=2
    ('Ventilar'),        -- id=3
    ('Desumidificar');   -- id=4

-- 4b. Temperaturas (16°C a 30°C)
INSERT INTO temperaturas (valor_c) VALUES
    (16),(17),(18),(19),(20),(21),(22),(23),(24),(25),(26),(27),(28),(29),(30);
-- id=1→16°C, id=2→17°C … id=9→24°C … id=15→30°C

-- 4c. Velocidades (0 = Auto … 5 = Máxima)
INSERT INTO velocidades (nivel, descricao) VALUES
    (0, 'Auto'),      -- id=1
    (1, 'Mínima'),    -- id=2
    (2, 'Baixa'),     -- id=3
    (3, 'Média'),     -- id=4
    (4, 'Alta'),      -- id=5
    (5, 'Máxima');    -- id=6

-- 4d. Funções IR
-- Referências: modo: 1=Quente,2=Frio | temp: 5=20°C,7=22°C,9=24°C,11=26°C | vel: 1=Auto,2=Mín,3=Baixa,4=Média
INSERT INTO funcoes_ir (nome, categoria, modo_id, temperatura_id, velocidade_id) VALUES
    ('Ligar',                        'Energia',      NULL, NULL, NULL),  -- id=1
    ('Desligar',                     'Energia',      NULL, NULL, NULL),  -- id=2
    ('Modo Quente | 24°C | Vel. 3',  'Temperatura',  1,    9,    4),     -- id=3  (Quente, 24°C, Média)
    ('Modo Quente | 26°C | Vel. 2',  'Temperatura',  1,    11,   3),     -- id=4  (Quente, 26°C, Baixa)
    ('Modo Frio | 20°C | Vel. 1',    'Temperatura',  2,    5,    2),     -- id=5  (Frio, 20°C, Mínima)
    ('Modo Frio | 22°C | Vel. 2',    'Temperatura',  2,    7,    3),     -- id=6  (Frio, 22°C, Baixa)
    ('Velocidade Auto',              'Ventilação',   NULL, NULL, 1),     -- id=7  (só velocidade)
    ('Modo Seco',                    'Modo',         NULL, NULL, NULL);  -- id=8

-- 5. Códigos IR — LG Split (modelo_id=1)
INSERT INTO codigos_ir (modelo_id, funcao_id, protocolo, codigo, frequencia) VALUES
    (1, 1, 'NEC', '0x88B44BB4', 38),
    (1, 2, 'NEC', '0x88B448B7', 38),
    (1, 3, 'RAW',
        '[9000,4500,560,560,560,1690,560,560,560,560,560,1690,560,560,'
        '560,1690,560,560,560,560,560,1690,560,560,560,560,560,560,560,'
        '560,560,1690,560,560,560,560,560,1690,560,560,560,560,560,560,'
        '560,560,560,1690,560,560,560,560,560,560,560,560,560,1690,560,'
        '560,560,560,560,1690,560]',
        38),
    (1, 5, 'RAW',
        '[9000,4500,560,1690,560,560,560,1690,560,560,560,560,560,560,'
        '560,1690,560,560,560,560,560,560,560,1690,560,560,560,560,560,'
        '1690,560,560,560,560,560,1690,560,560,560,560,560,1690,560,560,'
        '560,560,560,560,560,560,560,560,560,1690,560,560,560,1690,560,'
        '560,560,560,560,560,560,560,560]',
        38);

-- 6. Códigos IR — Samsung Split (modelo_id=2)
INSERT INTO codigos_ir (modelo_id, funcao_id, protocolo, codigo, frequencia) VALUES
    (2, 1, 'SAMSUNG_AC', '0xC102820154000000', 38),
    (2, 2, 'SAMSUNG_AC', '0xC102820100000000', 38),
    (2, 3, 'SAMSUNG_AC', '0xC1028201540C3000', 38),
    (2, 5, 'SAMSUNG_AC', '0xC1028201001C1000', 38);

-- 7. Sala
INSERT INTO salas (nome, descricao) VALUES
    ('Sala 101', 'Sala de reuniões principal — 1º andar');

-- 8. Dispositivos físicos
INSERT INTO dispositivos (modelo_id, numero_serie, descricao) VALUES
    (1, 'LG-SN-20240101',  'Climatizador LG — parede norte'),
    (2, 'SAM-SN-20240202', 'Climatizador Samsung — parede sul');

-- 9. Alocar dispositivos na Sala 101 (sala_id=1)
INSERT INTO sala_dispositivos (sala_id, dispositivo_id, slot) VALUES
    (1, 1, 1),   -- Slot 1: LG
    (1, 2, 2);   -- Slot 2: Samsung

-- 10. Usuário admin (senha: admin123 — hash bcrypt)
INSERT INTO usuarios (nome_real, username, senha_hash, email, perfil) VALUES
    ('Administrador do Sistema', 'admin',
     '$2y$12$eXaVZDm7yfKVuNmhBHaFqOHd0fPqDiO3rJO8cMqxOiZGMSHs8KJmi',
     'admin@ircontrol.local', 1);


-- ============================================================
-- VIEWs ÚTEIS PARA O BACKEND / ESP32
-- ============================================================

-- View 1: Visão geral Sala × Slot × Dispositivo
CREATE OR REPLACE VIEW vw_sala_dispositivos AS
SELECT
    s.id          AS sala_id,
    s.nome        AS sala,
    sd.slot,
    d.id          AS dispositivo_id,
    d.numero_serie,
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
ORDER BY s.nome, sd.slot;


-- View 2: Comandos IR disponíveis por sala e slot
CREATE OR REPLACE VIEW vw_comandos_por_sala AS
SELECT
    s.nome        AS sala,
    sd.slot,
    m.nome        AS marca,
    mo.nome       AS modelo,
    f.categoria,
    f.nome        AS funcao,
    c.protocolo,
    c.frequencia  AS freq_khz,
    c.codigo      AS codigo_ir
FROM sala_dispositivos sd
JOIN salas             s   ON s.id        = sd.sala_id
JOIN dispositivos      d   ON d.id        = sd.dispositivo_id
JOIN modelos           mo  ON mo.id       = d.modelo_id
JOIN marcas            m   ON m.id        = mo.marca_id
JOIN codigos_ir        c   ON c.modelo_id = mo.id
JOIN funcoes_ir        f   ON f.id        = c.funcao_id
ORDER BY s.nome, sd.slot, f.categoria, f.nome;
