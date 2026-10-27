# 📡 IR Control — Sistema Web de Gestão e Disparo Infravermelho com ESP32

[![PHP](https://img.shields.io/badge/PHP-8.x-777BB4?style=flat&logo=php&logoColor=white)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-8.0%20%2F%20MariaDB-4479A1?style=flat&logo=mysql&logoColor=white)](https://www.mysql.com/)
[![ESP32](https://img.shields.io/badge/Hardware-ESP32%20IR-E7352C?style=flat&logo=espressif&logoColor=white)](https://www.espressif.com/)
[![License](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

O **IR Control** é uma plataforma web completa para controle, automação e monitoramento centralizado de equipamentos acionados por Infravermelho (como aparelhos de ar-condicionado, climatizadores, projetores e televisores) integrados a microcontroladores **ESP32**.

O sistema conta com um **Dashboard operacional** com interface em modo escuro (*Dark Glassmorphism*) e um **Painel Administrativo** para gestão de salas, dispositivos, alocação física por slots, usuários e banco de códigos IR (HEX e RAW).

---

## 🚀 Principais Funcionalidades

### 🎮 Dashboard Operacional
- **Visão por Salas:** Organização dos equipamentos alocados em cada ambiente.
- **Controle Digital de Climatizadores:**
  - Visor digital com indicação do modo e temperatura atual.
  - Ajuste de temperatura com botões `+` e `-` (faixa de 16°C a 30°C).
  - Seletor de modos de operação com seleção única (*❄️ Frio*, *🔥 Quente*, *💨 Ventilar*, *💧 Desumidificar*, *🔄 Automático*).
  - Botões rápidos de *Ligar* e *Desligar*.
  - Ações dinâmicas de ventilação, oscilação (*Swing*) e velocidades.
- **Controle Remoto Universal:** Interface para projetores e TVs dividida por categorias (*Energia, Entrada/Input, Zoom, Menu, Áudio*).
- **Feedback em Tempo Real:** Efeitos táteis de envio (`sending`), confirmações visuais e notificações Toast.

---

### ⚙️ Painel Administrativo
- **Gestão de Salas:** Cadastro e controle de ambientes com resumo de dispositivos instalados.
- **Alocação de Dispositivos (Regra dos 4 Slots):** Gerenciamento de aparelhos físicos por sala, respeitando posições físicas (Slot 1 a 4).
- **Marcas e Modelos:** Cadastro estruturado de fabricantes e linhas de produtos.
- **Funções IR Normalizadas:** Associação granular de ações a modos de operação, faixas de temperatura e velocidades do ventilador.
- **Códigos IR Individuais:** Cadastro e testes manuais de sinais HEX e pulsos RAW JSON.
- **⚡ Importação de Códigos IR em Matriz (Batch Import):**
  - Cadastro em lote através de formato CSV ou colagem direta de texto.
  - Pré-visualização dinâmica com validação de colunas antes da gravação.
  - Autocriação inteligente de tipos, marcas, modelos, modos e funções inexistentes no banco.
  - Upload direto de arquivos `.csv` e `.txt`.
- **Gestão de Usuários:** Perfis de acesso diferenciados (**Administrador** e **Usuário Padrão**).

---

## 📊 Padrão da Matriz de Códigos IR

A importação em matriz permite cadastrar ou atualizar dezenas de comandos com uma única operação no formato:

```text
tipo, marca, modelo, protocolo, modo_operacao, temperatura, velocidade_ventilador, codigo_hex
```

### Exemplos Práticos:

```csv
Climatizador, Elgin, ELGIN sala RA, COOLIX, Frio, 17, Auto, 0xB21F00
Climatizador, Elgin, ELGIN sala RA, COOLIX, Frio, 17, Mínimo, 0xB21F90
Climatizador, Elgin, ELGIN sala RA, COOLIX, Frio, 17, Médio, 0xB21F50
Climatizador, Elgin, ELGIN sala RA, COOLIX, Frio, 17, Máximo, 0xB21F10
Climatizador, Elgin, ELGIN sala RA, COOLIX, Frio, 18, Máximo, B21F10
Climatizador, Elgin, ELGIN sala RA, COOLIX, Frio, 19, Máximo, 0xB21F10
Climatizador, Elgin, ELGIN sala RA, COOLIX, Frio, 20, Máximo, B21F10
Climatizador, Elgin, ELGIN sala RA, COOLIX, Desligar, -, -, 0xB27B40
Climatizador, Elgin, ELGIN sala RA, COOLIX, Swing Toggle, -, -, 0xB27BE0
Climatizador, Elgin, ELGIN sala RA, COOLIX, Quente, 17, Auto, 0xB2DF00
Climatizador, Elgin, ELGIN sala RA, COOLIX, Desumidificar, -, -, 0xB25F40
Climatizador, Elgin, ELGIN sala RA, COOLIX, Ventilar, -, Auto, 0xB29F00
Climatizador, Elgin, ELGIN sala RA, COOLIX, Ventilar, -, Mínimo, 0xB29F90
Climatizador, Elgin, ELGIN sala RA, COOLIX, Ventilar, -, Médio, B29F50
Climatizador, Elgin, ELGIN sala RA, COOLIX, Ventilar, -, Máximo, B29F10
Climatizador, Elgin, ELGIN sala RA, COOLIX, Automático, -, -, B29F10
```

> **Dica:** O sistema aceita separadores por vírgula (`,`), ponto-e-vírgula (`;`) ou Tabulação (`\t`). Para comandos que não usam temperatura ou velocidade, basta preencher com `-`.

---

## 🛠️ Tecnologias Utilizadas

| Camada | Tecnologias |
| :--- | :--- |
| **Frontend** | HTML5 Semântico, Vanilla CSS (Design Tokens, Glassmorphism), JavaScript (ES6+ modular), Bootstrap 5 (Grid e Modais) |
| **Backend** | PHP 8.x estruturado com endpoints RESTful em JSON |
| **Banco de Dados** | MySQL 8.0 / MariaDB com integridade referencial, foreign keys e views otimizadas |
| **Hardware** | ESP32 (Wi-Fi / HTTP Client + Emissor Infravermelho 38kHz) |

---

## 📂 Estrutura de Diretórios

```plaintext
esp32-ir/
├── admin.php                      # Painel administrativo com abas e modais
├── dashboard.php                  # Painel operacional do usuário por salas
├── index.php                      # Roteador inicial de autenticação
├── login.php                      # Tela de login com validações
├── api/                           # Endpoints RESTful do backend
│   ├── auth/                      # Login, logout e verificação de sessão (me)
│   ├── codigos_ir.php             # CRUD de códigos infravermelhos
│   ├── codigos_matriz.php         # Processamento e importação em matriz
│   ├── dispositivos.php           # Gestão de aparelhos e alocação por slot
│   ├── funcoes_ir.php             # Ações, modos, temperaturas e velocidades
│   ├── ir/
│   │   ├── disparar.php           # Lógica de disparo e log para o ESP32
│   │   ├── pendente.php           # Consulta de comandos pendentes (Polling a cada 1s)
│   │   └── confirmar.php          # Confirmação de recebimento/execução pelo ESP32
│   ├── marcas.php                 # CRUD de marcas
│   ├── modelos.php                # CRUD de modelos de aparelhos
│   ├── salas.php                  # CRUD de salas e ambientes
│   └── usuarios.php               # CRUD de usuários e permissões
├── assets/
│   ├── css/
│   │   └── style.css              # Sistema de design (Dark Theme, responsividade)
│   └── js/
│       ├── admin.js               # Controladores e eventos do painel administrativo
│       ├── api.js                 # Camada de comunicação assíncrona (Fetch API)
│       ├── app.js                 # Utilitários de interface (Toasts, loaders, badges)
│       └── dashboard.js           # Lógica de controle do climatizador e remotos
├── config/
│   └── database.php               # Conexão PDO segura com MySQL e config ESP32_IP
├── database/
│   └── ir_control_schema.sql      # Estrutura completa das tabelas, views e seeds
├── firmware_esp32/                # Código-fonte para o microcontrolador ESP32
│   ├── firmware_esp32.ino         # Sketch Arduino com emissão no GPIO 4 (Web Server + Polling 1s)
│   └── README.md                  # Esquemático de ligação, pinagem e guia de gravação
└── includes/
    ├── auth.php                   # Controle de sessão e perfis de acesso
    └── helpers.php                # Funções auxiliares (jsonResponse, sanitização)
```

---

## ⚙️ Instalação e Configuração

### 1. Pré-requisitos
- Servidor Web Apache com PHP 8.0+ (ex: **XAMPP**, WampServer ou Docker).
- Banco de dados **MySQL** ou **MariaDB**.
- Git instalado.

### 2. Clonar o Repositório
Abra o terminal na pasta pública do seu servidor (ex: `C:\xampp\htdocs\`):

```bash
cd c:\xampp\htdocs
git clone https://github.com/jgr123/web-esp32.git esp32-ir
```

### 3. Configurar o Banco de Dados
1. Inicie o Apache e o MySQL no painel do XAMPP.
2. Acesse o **phpMyAdmin** (`http://localhost/phpmyadmin`) ou cliente MySQL de sua preferência.
3. Crie o banco de dados `ir_control`:
   ```sql
   CREATE DATABASE ir_control CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
4. Importe o arquivo SQL localizado em:
   ```plaintext
   database/ir_control_schema.sql
   ```

### 4. Ajustar Conexão (se necessário)
Caso seu MySQL possua senha ou utilize outra porta, altere as variáveis em [config/database.php](config/database.php):

```php
define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_NAME', 'ir_control');
define('DB_USER', 'root');
define('DB_PASS', '');
```

### 5. Acessar a Aplicação
Abra o navegador em:
```text
http://localhost/esp32-ir
```

---

## 🔑 Credenciais Padrão de Acesso

| Usuário | Senha | Perfil | Permissões |
| :--- | :--- | :--- | :--- |
| **admin** | `admin123` | **Administrador** (1) | Acesso total: Painel Admin, Dashboard, CRUD e Matriz |

*(Novos usuários e operadores podem ser cadastrados na aba Usuários do painel admin).*

---

## 🔌 Integração com o Microcontrolador ESP32

Quando um comando é acionado no Dashboard ou no Admin:
1. O frontend chama `POST /api/ir/disparar.php`.
2. O sistema localiza o código correto correspondente ao modelo e aos parâmetros de temperatura/modo.
3. O disparo é registrado na tabela `log_disparos`.
4. O payload é preparado para envio ao ESP32 via HTTP/MQTT contendo o código HEX/RAW e protocolo de transmissão.

---

## 📄 Licença

Este projeto é disponibilizado sob a licença [MIT](LICENSE).
Sinta-se à vontade para utilizar, modificar e distribuir conforme necessário.
