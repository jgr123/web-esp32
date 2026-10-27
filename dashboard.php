<?php
/**
 * dashboard.php — Dashboard Principal de Controle IR
 * Visualização das salas e envio de comandos IR para climatizadores e projetores
 */

require_once __DIR__ . '/includes/auth.php';

requireLogin();

$user = getSessionUser();
$isAdmin = isAdmin();
$initials = strtoupper(mb_substr($user['nome_real'] ?? $user['username'] ?? 'U', 0, 2));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard — IR Control</title>
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
          <div class="brand-icon">📡</div>
          <span>ESP32 IR Control</span>
        </a>

        <nav class="d-none d-md-flex align-items-center gap-2 ms-4">
          <a href="/esp32-ir/dashboard.php" class="btn-ghost-ir text-white" style="border-color: var(--primary);">
            🏢 Salas
          </a>
          <?php if ($isAdmin): ?>
            <a href="/esp32-ir/admin.php" class="btn-ghost-ir">
              ⚡ Painel Admin
            </a>
          <?php endif; ?>
        </nav>
      </div>

      <div class="nav-user">
        <div class="d-none d-sm-block text-end">
          <div style="font-weight: 600; font-size: 0.85rem; color: var(--text-primary);">
            <?= htmlspecialchars($user['nome_real'] ?: $user['username']) ?>
          </div>
          <div style="font-size: 0.72rem; color: var(--text-muted);">
            <?= $isAdmin ? '<span class="badge-ir badge-admin">⚡ Administrador</span>' : '<span class="badge-ir badge-user">👤 Usuário Padrão</span>' ?>
          </div>
        </div>

        <div class="avatar" title="<?= htmlspecialchars($user['username']) ?>">
          <?= $initials ?>
        </div>

        <?php if ($isAdmin): ?>
          <a href="/esp32-ir/admin.php" class="btn-ghost-ir d-md-none" title="Painel Admin">⚡</a>
        <?php endif; ?>

        <button onclick="handleLogout()" class="btn-ghost-ir btn-danger-ir ms-1" title="Sair do sistema">
          <span>🚪</span>
          <span class="d-none d-sm-inline">Sair</span>
        </button>
      </div>
    </div>
  </header>

  <!-- ── Main Content ── -->
  <main class="page-content">
    <div class="page-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
      <div>
        <h1 class="page-title">Controle de Ambientes</h1>
        <p class="page-subtitle">Selecione uma sala para abrir o controle remoto dos climatizadores e projetores</p>
      </div>
      
      <?php if ($isAdmin): ?>
        <div>
          <a href="/esp32-ir/admin.php?tab=dispositivos" class="btn-primary-ir">
            <span>+ Novo Dispositivo</span>
          </a>
        </div>
      <?php endif; ?>
    </div>

    <!-- ── Stat Cards ── -->
    <div class="stat-grid">
      <div class="stat-card">
        <div class="d-flex justify-content-between align-items-center">
          <span class="stat-label">Salas Ativas</span>
          <span class="stat-icon">🏢</span>
        </div>
        <div id="stat-salas" class="stat-value">--</div>
        <small style="color: var(--text-muted); font-size: 0.7rem;">Ambientes monitorados</small>
      </div>

      <div class="stat-card">
        <div class="d-flex justify-content-between align-items-center">
          <span class="stat-label">Dispositivos Vinculados</span>
          <span class="stat-icon">❄️</span>
        </div>
        <div id="stat-disps" class="stat-value">--</div>
        <small style="color: var(--text-muted); font-size: 0.7rem;">Climatizadores e projetores</small>
      </div>

      <div class="stat-card">
        <div class="d-flex justify-content-between align-items-center">
          <span class="stat-label">Slots Livres no Sistema</span>
          <span class="stat-icon">🔌</span>
        </div>
        <div id="stat-slots" class="stat-value">--</div>
        <small style="color: var(--text-muted); font-size: 0.7rem;">(Máximo 4 por sala)</small>
      </div>
    </div>

    <!-- ── Grade de Salas ── -->
    <div class="mb-3 d-flex justify-content-between align-items-center">
      <h2 style="font-size: 1.1rem; font-weight: 600; margin: 0;">Salas Disponíveis</h2>
      <button onclick="loadRooms()" class="btn-ghost-ir" style="padding: 0.35rem 0.75rem; font-size: 0.75rem;">
        🔄 Atualizar
      </button>
    </div>

    <div id="rooms-grid" class="room-grid">
      <!-- Carregado via assets/js/dashboard.js -->
    </div>
  </main>

  <!-- ── Modal de Controle Remoto da Sala ── -->
  <div class="modal fade" id="roomModal" tabindex="-1" aria-labelledby="roomModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="roomModalTitle">Controle da Sala</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body" id="roomModalBody">
          <!-- Carregado dinamicamente: tabs de dispositivos dos slots 1..4 e botões IR -->
        </div>
        <div class="modal-footer d-flex justify-content-between">
          <small style="color: var(--text-muted); font-size: 0.75rem;">
            💡 Os disparos são roteados em tempo real para a integração com o ESP32.
          </small>
          <button type="button" class="btn-ghost-ir" data-bs-dismiss="modal">Fechar</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Bootstrap 5 JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <!-- Scripts do IR Control -->
  <script src="/esp32-ir/assets/js/api.js"></script>
  <script src="/esp32-ir/assets/js/app.js"></script>
  <script src="/esp32-ir/assets/js/dashboard.js"></script>

  <script>
    async function handleLogout() {
      if (confirmAction('Deseja realmente encerrar a sessão?')) {
        try {
          await api.logout();
        } catch (e) {
          // ignora erro
        }
        window.location.href = '/esp32-ir/login.php';
      }
    }
  </script>
</body>
</html>
