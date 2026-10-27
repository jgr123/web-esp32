<?php
/**
 * login.php — Tela de autenticação de usuários
 * Acesso seguro para Administradores e Usuários Comuns
 */

require_once __DIR__ . '/includes/auth.php';

startSession();

// Se já estiver logado, redireciona diretamente ao dashboard
if (isLoggedIn()) {
    header('Location: /esp32-ir/dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login — IR Control System</title>
  <!-- Bootstrap 5 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Custom Dark Theme Stylesheet -->
  <link rel="stylesheet" href="/esp32-ir/assets/css/style.css">
</head>
<body>

<div class="login-wrapper">
  <div class="login-card">
    <div class="login-logo">
      📡
    </div>

    <div class="text-center mb-4">
      <h2 style="font-size: 1.4rem; font-weight: 700; margin-bottom: 0.25rem;">ESP32 IR Control</h2>
      <p style="color: var(--text-muted); font-size: 0.85rem;">Controle Remoto de Climatizadores e Projetores</p>
    </div>

    <div id="login-alert" class="alert d-none py-2 px-3 mb-3" style="font-size: 0.85rem; border-radius: var(--radius-sm);" role="alert"></div>

    <form id="form-login" onsubmit="handleLogin(event)">
      <div class="mb-3">
        <label for="username" class="form-label">Usuário</label>
        <div class="input-group">
          <input 
            type="text" 
            class="form-control" 
            id="username" 
            name="username" 
            placeholder="Digite seu usuário..." 
            required 
            autocomplete="username" 
            autofocus
          >
        </div>
      </div>

      <div class="mb-4">
        <label for="senha" class="form-label">Senha</label>
        <div class="input-group">
          <input 
            type="password" 
            class="form-control" 
            id="senha" 
            name="senha" 
            placeholder="Digite sua senha..." 
            required 
            autocomplete="current-password"
          >
        </div>
      </div>

      <button type="submit" id="btn-submit" class="btn-primary-ir w-100 justify-content-center">
        <span>Entrar no Sistema</span>
        <span style="font-size: 1.1rem;">➔</span>
      </button>
    </form>

    <div class="mt-4 pt-3 text-center border-top" style="border-color: var(--border) !important;">
      <small style="color: var(--text-muted); font-size: 0.75rem; display: block;">
        Ambiente de Demonstração:<br>
        <strong>Usuário:</strong> <code class="mono" style="color: var(--accent-cyan);">admin</code> &bull; 
        <strong>Senha:</strong> <code class="mono" style="color: var(--accent-cyan);">admin123</code>
      </small>
    </div>
  </div>
</div>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Scripts da Aplicação -->
<script src="/esp32-ir/assets/js/api.js"></script>
<script src="/esp32-ir/assets/js/app.js"></script>

<script>
async function handleLogin(e) {
  e.preventDefault();
  const btn = document.getElementById('btn-submit');
  const alertEl = document.getElementById('login-alert');
  const username = document.getElementById('username').value.trim();
  const senha = document.getElementById('senha').value;

  alertEl.className = 'alert d-none';

  if (!username || !senha) {
    alertEl.className = 'alert alert-warning py-2 px-3 mb-3';
    alertEl.textContent = 'Por favor, preencha usuário e senha.';
    return;
  }

  setLoading(btn, true);

  try {
    const res = await api.login({ username, senha });

    if (res && res.success) {
      showToast('Autenticação bem-sucedida! Redirecionando...', 'success');
      setTimeout(() => {
        window.location.href = '/esp32-ir/dashboard.php';
      }, 700);
    } else {
      alertEl.className = 'alert alert-danger py-2 px-3 mb-3';
      alertEl.textContent = res.message || 'Credenciais inválidas.';
      showToast(res.message || 'Falha no login', 'error');
      setLoading(btn, false);
    }
  } catch (err) {
    alertEl.className = 'alert alert-danger py-2 px-3 mb-3';
    alertEl.textContent = 'Erro ao conectar ao servidor. Tente novamente.';
    showToast('Erro de conexão.', 'error');
    setLoading(btn, false);
  }
}
</script>

</body>
</html>
