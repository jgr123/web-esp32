<?php
/**
 * admin.php — Painel Administrativo (Exclusivo Perfil 1 - Administrador)
 * Gestão de Usuários, Salas, Dispositivos, Códigos IR, Funções, Modelos e Marcas
 */

require_once __DIR__ . '/includes/auth.php';

// Bloqueia usuários que não sejam Administrador (perfil = 1)
requireAdmin();

$user = getSessionUser();
$initials = strtoupper(mb_substr($user['nome_real'] ?? $user['username'] ?? 'A', 0, 2));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Painel Administrativo — IR Control</title>
  <!-- Bootstrap 5 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Custom Dark Theme Stylesheet -->
  <link rel="stylesheet" href="/esp32-ir/assets/css/style.css">
</head>
<body>

  <!-- ── Top Navbar ── -->
  <header class="navbar-ir">
    <div class="d-flex align-items-center justify-content-between w-100 max-w-1400 mx-auto">
      <div class="d-flex align-items-center gap-3">
        <a href="/esp32-ir/dashboard.php" class="navbar-brand-ir">
          <div class="brand-icon">⚡</div>
          <span>IR Control &bull; Admin</span>
        </a>

        <nav class="d-none d-md-flex align-items-center gap-2 ms-4">
          <a href="/esp32-ir/dashboard.php" class="btn-ghost-ir">
            🏢 Ir para Dashboard
          </a>
          <a href="/esp32-ir/admin.php" class="btn-ghost-ir text-white" style="border-color: var(--primary);">
            ⚡ Administração
          </a>
        </nav>
      </div>

      <div class="nav-user">
        <div class="d-none d-sm-block text-end">
          <div style="font-weight: 600; font-size: 0.85rem; color: var(--text-primary);">
            <?= htmlspecialchars($user['nome_real'] ?: $user['username']) ?>
          </div>
          <div style="font-size: 0.72rem;">
            <span class="badge-ir badge-admin">⚡ Administrador</span>
          </div>
        </div>

        <div class="avatar" title="<?= htmlspecialchars($user['username']) ?>">
          <?= $initials ?>
        </div>

        <a href="/esp32-ir/dashboard.php" class="btn-ghost-ir d-md-none" title="Dashboard">🏢</a>

        <button onclick="handleLogout()" class="btn-ghost-ir btn-danger-ir ms-1" title="Sair do sistema">
          <span>🚪</span>
          <span class="d-none d-sm-inline">Sair</span>
        </button>
      </div>
    </div>
  </header>

  <!-- ── Admin Navigation Tabs ── -->
  <nav class="admin-nav">
    <div class="max-w-1400 mx-auto">
      <ul class="admin-nav-tabs" id="adminTabs">
        <li><a href="#tab-salas" class="active" onclick="switchAdminTab('tab-salas', this)">🏢 Salas</a></li>
        <li><a href="#tab-dispositivos" onclick="switchAdminTab('tab-dispositivos', this)">❄️ Dispositivos &amp; Slots</a></li>
        <li><a href="#tab-codigos" onclick="switchAdminTab('tab-codigos', this)">📡 Códigos IR</a></li>
        <li><a href="#tab-usuarios" onclick="switchAdminTab('tab-usuarios', this)">👥 Usuários</a></li>
        <li><a href="#tab-modelos" onclick="switchAdminTab('tab-modelos', this)">🏷️ Modelos &amp; Marcas</a></li>
      </ul>
    </div>
  </nav>

  <!-- ── Main Content Area ── -->
  <main class="page-content">

    <!-- ════════════════════════════════════════════════════════════════
         ABA 1: SALAS
         ════════════════════════════════════════════════════════════════ -->
    <section id="tab-salas" class="admin-section">
      <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mb-4">
        <div>
          <h2 class="page-title">Gerenciamento de Salas</h2>
          <p class="page-subtitle">Cadastre os ambientes onde os climatizadores e projetores estão instalados</p>
        </div>
        <button onclick="openModalSala()" class="btn-primary-ir">
          <span>+ Nova Sala</span>
        </button>
      </div>

      <div class="ir-card p-0 overflow-hidden">
        <div class="table-responsive">
          <table class="ir-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Nome da Sala</th>
                <th>Descrição</th>
                <th>Ocupação (Slots)</th>
                <th>Status</th>
                <th class="text-end">Ações</th>
              </tr>
            </thead>
            <tbody id="salas-tbody">
              <tr><td colspan="6" class="text-center py-4"><span class="spinner-ir"></span> Carregando...</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- ════════════════════════════════════════════════════════════════
         ABA 2: DISPOSITIVOS (MÁX. 4 POR SALA)
         ════════════════════════════════════════════════════════════════ -->
    <section id="tab-dispositivos" class="admin-section" style="display: none;">
      <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mb-4">
        <div>
          <h2 class="page-title">Gerenciamento de Dispositivos</h2>
          <p class="page-subtitle">Vincule aparelhos a salas respeitando a regra de no máximo 4 dispositivos (slots 1 a 4)</p>
        </div>
        <button onclick="openModalDispositivo()" class="btn-primary-ir">
          <span>+ Novo Dispositivo</span>
        </button>
      </div>

      <div class="ir-card p-0 overflow-hidden">
        <div class="table-responsive">
          <table class="ir-table">
            <thead>
              <tr>
                <th>Sala</th>
                <th>Slot</th>
                <th>Tipo</th>
                <th>Marca &amp; Modelo</th>
                <th>Nº Série / Detalhes</th>
                <th>Status</th>
                <th class="text-end">Ações</th>
              </tr>
            </thead>
            <tbody id="dispositivos-tbody">
              <tr><td colspan="7" class="text-center py-4"><span class="spinner-ir"></span> Carregando...</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- ════════════════════════════════════════════════════════════════
         ABA 3: CÓDIGOS IR
         ════════════════════════════════════════════════════════════════ -->
    <section id="tab-codigos" class="admin-section" style="display: none;">
      <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mb-4">
        <div>
          <h2 class="page-title">Códigos Infravermelho (IR)</h2>
          <p class="page-subtitle">Comandos HEX e RAW associados aos modelos de climatizadores e projetores</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
          <button onclick="openModalFuncao()" class="btn-ghost-ir">
            <span>+ Nova Função IR</span>
          </button>
          <button type="button" onclick="openModalMatriz()" data-bs-toggle="modal" data-bs-target="#modalMatriz" class="btn-ghost-ir" style="border-color: rgba(6,182,212,0.5); color: var(--accent-cyan);">
            <span>⚡ + Importar Matriz IR</span>
          </button>
          <button onclick="openModalCodigo()" class="btn-primary-ir">
            <span>+ Novo Código IR</span>
          </button>
        </div>
      </div>

      <div class="ir-card p-0 overflow-hidden">
        <div class="table-responsive">
          <table class="ir-table">
            <thead>
              <tr>
                <th>Modelo</th>
                <th>Função / Ação</th>
                <th>Categoria</th>
                <th>Protocolo</th>
                <th>Freq.</th>
                <th>Código IR</th>
                <th class="text-end">Ações</th>
              </tr>
            </thead>
            <tbody id="codigos-tbody">
              <tr><td colspan="7" class="text-center py-4"><span class="spinner-ir"></span> Carregando...</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- ════════════════════════════════════════════════════════════════
         ABA 4: USUÁRIOS
         ════════════════════════════════════════════════════════════════ -->
    <section id="tab-usuarios" class="admin-section" style="display: none;">
      <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mb-4">
        <div>
          <h2 class="page-title">Gerenciamento de Usuários</h2>
          <p class="page-subtitle">Controle de acessos: Administradores (Perfil 1) e Usuários Padrão (Perfil 2)</p>
        </div>
        <button onclick="openModalUsuario()" class="btn-primary-ir">
          <span>+ Novo Usuário</span>
        </button>
      </div>

      <div class="ir-card p-0 overflow-hidden">
        <div class="table-responsive">
          <table class="ir-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Nome Real</th>
                <th>Usuário</th>
                <th>E-mail</th>
                <th>Perfil</th>
                <th>Status</th>
                <th class="text-end">Ações</th>
              </tr>
            </thead>
            <tbody id="usuarios-tbody">
              <tr><td colspan="7" class="text-center py-4"><span class="spinner-ir"></span> Carregando...</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- ════════════════════════════════════════════════════════════════
         ABA 5: MODELOS & MARCAS
         ════════════════════════════════════════════════════════════════ -->
    <section id="tab-modelos" class="admin-section" style="display: none;">
      <div class="row g-4">
        <!-- Coluna de Marcas -->
        <div class="col-lg-5">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
              <h3 style="font-size: 1.1rem; font-weight: 600; margin: 0;">Marcas / Fabricantes</h3>
              <p class="page-subtitle mb-0">Fabricantes de aparelhos (LG, Samsung, etc.)</p>
            </div>
            <button onclick="openModalMarca()" class="btn-ghost-ir">
              <span>+ Marca</span>
            </button>
          </div>

          <div class="ir-card p-0 overflow-hidden">
            <table class="ir-table">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Nome da Marca</th>
                  <th class="text-end">Ações</th>
                </tr>
              </thead>
              <tbody id="marcas-tbody">
                <tr><td colspan="3" class="text-center py-3"><span class="spinner-ir"></span></td></tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Coluna de Modelos -->
        <div class="col-lg-7">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
              <h3 style="font-size: 1.1rem; font-weight: 600; margin: 0;">Modelos de Aparelhos</h3>
              <p class="page-subtitle mb-0">Modelos específicos com SKU/Referência</p>
            </div>
            <button onclick="openModalModelo()" class="btn-primary-ir">
              <span>+ Novo Modelo</span>
            </button>
          </div>

          <div class="ir-card p-0 overflow-hidden">
            <table class="ir-table">
              <thead>
                <tr>
                  <th>Modelo</th>
                  <th>Marca</th>
                  <th>Tipo</th>
                  <th>Referência</th>
                  <th class="text-end">Ações</th>
                </tr>
              </thead>
              <tbody id="modelos-tbody">
                <tr><td colspan="5" class="text-center py-3"><span class="spinner-ir"></span></td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </section>

  </main>

  <!-- ════════════════════════════════════════════════════════════════
       MODAIS DE CADASTRO E EDIÇÃO
       ════════════════════════════════════════════════════════════════ -->

  <!-- 1. Modal Sala -->
  <div class="modal fade" id="modalSala" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modalSalaTitle">Nova Sala</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form id="formSala" onsubmit="saveSala(event)">
          <input type="hidden" id="sala-id">
          <div class="modal-body">
            <div class="mb-3">
              <label for="sala-nome" class="form-label">Nome da Sala *</label>
              <input type="text" class="form-control" id="sala-nome" placeholder="Ex: Sala 101, Auditório B" required>
            </div>
            <div class="mb-3">
              <label for="sala-descricao" class="form-label">Descrição</label>
              <input type="text" class="form-control" id="sala-descricao" placeholder="Ex: Bloco A, 2º andar">
            </div>
            <div class="mb-3" id="sala-ativo-group" style="display: none;">
              <label for="sala-ativo" class="form-label">Status</label>
              <select class="form-select" id="sala-ativo">
                <option value="1">Ativo</option>
                <option value="0">Inativo</option>
              </select>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn-ghost-ir" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" id="btn-save-sala" class="btn-primary-ir">Salvar Sala</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- 2. Modal Dispositivo (Com validação do limite de 4 slots por sala) -->
  <div class="modal fade" id="modalDispositivo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modalDispTitle">Adicionar Dispositivo</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form id="formDispositivo" onsubmit="saveDispositivo(event)">
          <input type="hidden" id="disp-id">
          <div class="modal-body">
            <!-- Alerta visual de regra dos 4 aparelhos -->
            <div id="disp-slot-warning" class="alert alert-info py-2 px-3 mb-3" style="font-size: 0.8rem;">
              ℹ️ Cada sala suporta no máximo <strong>4 dispositivos</strong> (slots 1 a 4).
            </div>

            <div class="mb-3" id="disp-sala-group">
              <label for="disp-sala-id" class="form-label">Sala de Instalação *</label>
              <select class="form-select" id="disp-sala-id" onchange="onSalaSelectedForDisp()" required>
                <option value="">Selecione uma sala...</option>
              </select>
            </div>

            <div class="mb-3" id="disp-slot-group">
              <label for="disp-slot" class="form-label">Slot da Sala (Posição 1 a 4) *</label>
              <select class="form-select" id="disp-slot" required>
                <option value="">Selecione a sala primeiro...</option>
              </select>
            </div>

            <div class="mb-3" id="disp-modelo-group">
              <label for="disp-modelo-id" class="form-label">Modelo do Aparelho *</label>
              <select class="form-select" id="disp-modelo-id" required>
                <option value="">Selecione um modelo...</option>
              </select>
            </div>

            <div class="mb-3">
              <label for="disp-serie" class="form-label">Número de Série (Opcional)</label>
              <input type="text" class="form-control" id="disp-serie" placeholder="Ex: LG-SN-202401">
            </div>

            <div class="mb-3">
              <label for="disp-descricao" class="form-label">Observações / Posição física</label>
              <input type="text" class="form-control" id="disp-descricao" placeholder="Ex: Parede Norte, Próximo à janela">
            </div>

            <div class="mb-3" id="disp-ativo-group" style="display: none;">
              <label for="disp-ativo" class="form-label">Status</label>
              <select class="form-select" id="disp-ativo">
                <option value="1">Ativo</option>
                <option value="0">Inativo</option>
              </select>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn-ghost-ir" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" id="btn-save-disp" class="btn-primary-ir">Salvar Dispositivo</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- 3. Modal Usuário -->
  <div class="modal fade" id="modalUsuario" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modalUserTitle">Novo Usuário</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form id="formUsuario" onsubmit="saveUsuario(event)">
          <input type="hidden" id="user-id">
          <div class="modal-body">
            <div class="mb-3">
              <label for="user-nome-real" class="form-label">Nome Real *</label>
              <input type="text" class="form-control" id="user-nome-real" placeholder="Ex: Carlos Silva" required>
            </div>

            <div class="row g-2 mb-3">
              <div class="col-sm-6">
                <label for="user-username" class="form-label">Usuário (Username) *</label>
                <input type="text" class="form-control" id="user-username" placeholder="Ex: csilva" required autocomplete="off">
              </div>
              <div class="col-sm-6">
                <label for="user-perfil" class="form-label">Perfil de Acesso *</label>
                <select class="form-select" id="user-perfil" required>
                  <option value="2">2 - Usuário Padrão</option>
                  <option value="1">1 - Administrador</option>
                </select>
              </div>
            </div>

            <div class="mb-3">
              <label for="user-email" class="form-label">E-mail *</label>
              <input type="email" class="form-control" id="user-email" placeholder="usuario@empresa.com" required>
            </div>

            <div class="mb-3">
              <label for="user-senha" class="form-label" id="user-senha-label">Senha *</label>
              <input type="password" class="form-control" id="user-senha" placeholder="Mínimo 6 caracteres" autocomplete="new-password">
              <small id="user-senha-hint" class="text-muted" style="font-size: 0.72rem; display: none;">Deixe em branco para manter a senha atual.</small>
            </div>

            <div class="mb-3" id="user-ativo-group" style="display: none;">
              <label for="user-ativo" class="form-label">Status da Conta</label>
              <select class="form-select" id="user-ativo">
                <option value="1">Ativa</option>
                <option value="0">Desativada</option>
              </select>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn-ghost-ir" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" id="btn-save-user" class="btn-primary-ir">Salvar Usuário</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- 4. Modal Código IR -->
  <div class="modal fade" id="modalCodigo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modalCodTitle">Novo Código IR</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form id="formCodigo" onsubmit="saveCodigo(event)">
          <input type="hidden" id="cod-id">
          <div class="modal-body">
            <div class="row g-3 mb-3">
              <div class="col-md-6" id="cod-modelo-group">
                <label for="cod-modelo-id" class="form-label">Modelo do Dispositivo *</label>
                <select class="form-select" id="cod-modelo-id" required>
                  <option value="">Selecione o modelo...</option>
                </select>
              </div>

              <div class="col-md-6" id="cod-funcao-group">
                <label for="cod-funcao-id" class="form-label">Função / Comando IR *</label>
                <select class="form-select" id="cod-funcao-id" required>
                  <option value="">Selecione a função...</option>
                </select>
              </div>
            </div>

            <div class="row g-3 mb-3">
              <div class="col-md-6">
                <label for="cod-protocolo" class="form-label">Protocolo IR</label>
                <input type="text" class="form-control" id="cod-protocolo" placeholder="Ex: NEC, SAMSUNG_AC, SONY, RAW">
              </div>

              <div class="col-md-6">
                <label for="cod-frequencia" class="form-label">Frequência Portadora (kHz)</label>
                <input type="number" class="form-control" id="cod-frequencia" value="38" min="30" max="60">
              </div>
            </div>

            <div class="mb-3">
              <label for="cod-codigo" class="form-label">Código IR (HEX ou RAW JSON) *</label>
              <textarea class="form-control" id="cod-codigo" rows="4" placeholder="Ex: 0x88B44BB4 ou [9000, 4500, 560, 560, ...]" required></textarea>
              <small style="color: var(--text-muted); font-size: 0.72rem;">
                Aceita strings Hexadecimais com <code>0x</code> ou arrays de pulsos RAW em formato JSON.
              </small>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn-ghost-ir" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" id="btn-save-cod" class="btn-primary-ir">Salvar Código IR</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- 5. Modal Função IR Auxiliar -->
  <div class="modal fade" id="modalFuncao" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Nova Função IR</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form id="formFuncao" onsubmit="saveFuncao(event)">
          <div class="modal-body">
            <div class="mb-3">
              <label for="fun-nome" class="form-label">Nome da Função *</label>
              <input type="text" class="form-control" id="fun-nome" placeholder="Ex: Ligar, Desligar, Frio 22°C Vel. 2" required>
            </div>
            <div class="mb-3">
              <label for="fun-categoria" class="form-label">Categoria *</label>
              <select class="form-select" id="fun-categoria" required>
                <option value="Energia">Energia (Ligar / Desligar)</option>
                <option value="Temperatura">Temperatura</option>
                <option value="Modo">Modo de Operação</option>
                <option value="Ventilação">Ventilação</option>
                <option value="Projetor">Projetor (Entrada, Zoom, etc.)</option>
                <option value="Geral">Geral</option>
              </select>
            </div>
            <div class="row g-2 mb-3">
              <div class="col-sm-4">
                <label for="fun-modo-id" class="form-label">Modo</label>
                <select class="form-select" id="fun-modo-id">
                  <option value="">Nenhum</option>
                </select>
              </div>
              <div class="col-sm-4">
                <label for="fun-temp-id" class="form-label">Temp.</label>
                <select class="form-select" id="fun-temp-id">
                  <option value="">Nenhum</option>
                </select>
              </div>
              <div class="col-sm-4">
                <label for="fun-vel-id" class="form-label">Velocidade</label>
                <select class="form-select" id="fun-vel-id">
                  <option value="">Nenhum</option>
                </select>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn-ghost-ir" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" id="btn-save-fun" class="btn-primary-ir">Cadastrar Função</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- 6. Modal Marca -->
  <div class="modal fade" id="modalMarca" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Nova Marca</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form id="formMarca" onsubmit="saveMarca(event)">
          <div class="modal-body">
            <div class="mb-3">
              <label for="marca-nome" class="form-label">Nome da Marca / Fabricante *</label>
              <input type="text" class="form-control" id="marca-nome" placeholder="Ex: LG, Samsung, Epson, Daikin" required>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn-ghost-ir" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" id="btn-save-marca" class="btn-primary-ir">Salvar Marca</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- 7. Modal Modelo -->
  <div class="modal fade" id="modalModelo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Novo Modelo</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form id="formModelo" onsubmit="saveModelo(event)">
          <div class="modal-body">
            <div class="mb-3">
              <label for="modelo-marca-id" class="form-label">Marca *</label>
              <select class="form-select" id="modelo-marca-id" required>
                <option value="">Selecione...</option>
              </select>
            </div>
            <div class="mb-3">
              <label for="modelo-tipo-id" class="form-label">Tipo de Dispositivo *</label>
              <select class="form-select" id="modelo-tipo-id" required>
                <option value="">Selecione...</option>
              </select>
            </div>
            <div class="mb-3">
              <label for="modelo-nome" class="form-label">Nome do Modelo *</label>
              <input type="text" class="form-control" id="modelo-nome" placeholder="Ex: Split Dual Inverter 12000 BTU" required>
            </div>
            <div class="mb-3">
              <label for="modelo-referencia" class="form-label">Código / Referência Fabricante</label>
              <input type="text" class="form-control" id="modelo-referencia" placeholder="Ex: S4-Q12JA3AA">
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn-ghost-ir" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" id="btn-save-modelo" class="btn-primary-ir">Salvar Modelo</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- 8. Modal Importar Códigos IR em Matriz -->
  <div class="modal fade" id="modalMatriz" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
      <div class="modal-content">
        <div class="modal-header">
          <div>
            <h5 class="modal-title">⚡ Importação de Códigos IR em Matriz</h5>
            <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 2px;">
              Cadastre modelos, funções e códigos IR em lote informando o padrão CSV ou colando os dados
            </div>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <!-- Instruções de formato -->
          <div class="p-3 mb-3 rounded" style="background: rgba(6, 182, 212, 0.08); border: 1px solid rgba(6, 182, 212, 0.25);">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
              <span style="font-size: 0.8rem; font-weight: 600; color: var(--accent-cyan);">
                📋 Padrão de colunas esperado (separado por vírgula, ponto-e-vírgula ou TAB):
              </span>
              <div class="d-flex gap-2">
                <button type="button" class="btn-ghost-ir py-1 px-2" style="font-size: 0.75rem;" onclick="carregarExemploMatriz()">
                  <span>📋 Inserir Exemplo da Matriz</span>
                </button>
                <button type="button" class="btn-ghost-ir py-1 px-2" style="font-size: 0.75rem;" onclick="limparMatriz()">
                  <span>🗑️ Limpar</span>
                </button>
              </div>
            </div>
            <code style="display: block; background: rgba(0,0,0,0.3); padding: 0.5rem 0.75rem; border-radius: 6px; font-size: 0.76rem; color: #a5f3fc; word-break: break-all;">
              tipo, marca, modelo, protocolo, modo_operacao, temperatura, velocidade_ventilador, codigo_hex
            </code>
            <div class="mt-2" style="font-size: 0.72rem; color: var(--text-muted);">
              • Se a temperatura ou velocidade não se aplicarem ao comando (ex: Desligar ou Swing Toggle), informe <code>-</code>.<br>
              • Se o tipo, marca, modelo, modo ou função não existirem no banco, eles serão cadastrados automaticamente.
            </div>
          </div>

          <!-- Campo de texto e upload -->
          <div class="mb-3">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <label for="matriz-texto" class="form-label mb-0" style="font-weight: 600;">
                Cole as linhas da matriz abaixo:
              </label>
              <label class="btn-ghost-ir py-0 px-2" style="font-size: 0.72rem; cursor: pointer; margin-bottom: 0;">
                📁 Carregar Arquivo CSV/TXT
                <input type="file" id="matriz-file-input" accept=".csv,.txt" style="display: none;" onchange="handleMatrizFileUpload(event)">
              </label>
            </div>
            <textarea id="matriz-texto" class="form-control" rows="7"
              placeholder="Exemplo:&#10;Climatizador, Elgin, ELGIN sala RA, COOLIX, Frio, 17, Auto, 0xB21F00&#10;Climatizador, Elgin, ELGIN sala RA, COOLIX, Desligar, -, -, 0xB27B40"
              style="font-family: monospace; font-size: 0.8rem; background: rgba(10, 14, 23, 0.7);"></textarea>
          </div>

          <!-- Botão de Pré-visualização e Contador -->
          <div class="d-flex justify-content-between align-items-center mb-3">
            <button type="button" id="btn-preview-matriz" class="btn-ghost-ir text-cyan" style="border-color: rgba(6,182,212,0.4);" onclick="previewMatriz()">
              <span>🔍 Analisar e Pré-visualizar</span>
            </button>
            <div id="matriz-count-badge" style="font-size: 0.8rem; color: var(--text-muted);">
              Nenhum dado processado ainda.
            </div>
          </div>

          <!-- Área de Pré-visualização -->
          <div id="matriz-preview-box" style="display: none;">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <h6 class="mb-0" style="font-size: 0.85rem; font-weight: 600; color: var(--text-primary);">
                Tabela de Pré-visualização dos Registros:
              </h6>
              <div id="matriz-stats" class="d-flex gap-2"></div>
            </div>

            <!-- Avisos/Erros -->
            <div id="matriz-erros-box" class="alert alert-warning py-2 mb-2" style="display: none; font-size: 0.78rem; background: rgba(245, 158, 11, 0.1); border-color: rgba(245, 158, 11, 0.3); color: #fbbf24;"></div>

            <!-- Tabela de Pré-visualização -->
            <div class="ir-card p-0 overflow-hidden mb-0" style="max-height: 280px; overflow-y: auto;">
              <table class="ir-table table-sm" style="font-size: 0.75rem;">
                <thead>
                  <tr>
                    <th style="width: 40px;">#</th>
                    <th>Tipo</th>
                    <th>Marca</th>
                    <th>Modelo</th>
                    <th>Protocolo</th>
                    <th>Modo / Ação</th>
                    <th>Temp.</th>
                    <th>Vel.</th>
                    <th>Código IR</th>
                    <th>Função no Sistema</th>
                  </tr>
                </thead>
                <tbody id="matriz-preview-tbody"></tbody>
              </table>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-ghost-ir" data-bs-dismiss="modal">Cancelar</button>
          <button type="button" id="btn-save-matriz" class="btn-primary-ir" onclick="salvarMatrizBanco()" disabled>
            <span>💾 Confirmar e Gravar Matriz</span>
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Bootstrap 5 JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <!-- Scripts do IR Control -->
  <script src="/esp32-ir/assets/js/api.js?v=<?= time() ?>"></script>
  <script src="/esp32-ir/assets/js/app.js?v=<?= time() ?>"></script>
  <script src="/esp32-ir/assets/js/admin.js?v=<?= time() ?>"></script>

  <script>
    async function handleLogout() {
      if (confirmAction('Deseja realmente encerrar a sessão?')) {
        try {
          await api.logout();
        } catch (e) { }
        window.location.href = '/esp32-ir/login.php';
      }
    }
  </script>
</body>
</html>
