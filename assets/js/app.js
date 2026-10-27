/**
 * app.js — Utilitários globais do sistema IR Control
 * Toast, Loading, confirmação e helpers de UI
 */

/* ── Toast Notifications ────────────────────────────────────── */
(function buildToast() {
  const el = document.createElement('div');
  el.id = 'toast-ir';
  el.className = 'toast-ir';
  el.innerHTML = `<span id="toast-icon"></span><div><div id="toast-title" style="font-weight:600;font-size:.85rem"></div><div id="toast-msg" style="color:var(--text-muted);font-size:.8rem"></div></div>`;
  document.body.appendChild(el);
})();

let _toastTimer = null;

function showToast(message, type = 'success', title = null) {
  const el    = document.getElementById('toast-ir');
  const icons = { success: '✅', error: '❌', info: 'ℹ️', warning: '⚠️' };

  document.getElementById('toast-icon').textContent  = icons[type] || icons.info;
  document.getElementById('toast-title').textContent = title || (type === 'success' ? 'Sucesso' : type === 'error' ? 'Erro' : 'Aviso');
  document.getElementById('toast-msg').textContent   = message;

  el.className = `toast-ir ${type} show`;
  clearTimeout(_toastTimer);
  _toastTimer = setTimeout(() => { el.className = 'toast-ir'; }, 4000);
}

/* ── Loading Overlay ────────────────────────────────────────── */
function setLoading(btn, loading) {
  if (!btn) return;
  if (loading) {
    btn._originalHTML = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-ir"></span> Aguarde...';
    btn.disabled = true;
  } else {
    btn.innerHTML = btn._originalHTML || btn.innerHTML;
    btn.disabled = false;
  }
}

/* ── Confirm Dialog ─────────────────────────────────────────── */
function confirmAction(message) {
  return confirm(message);
}

/* ── Populate <select> ──────────────────────────────────────── */
function populateSelect(selectEl, items, valueKey, labelKey, placeholder = 'Selecione...') {
  selectEl.innerHTML = `<option value="">${placeholder}</option>`;
  items.forEach(item => {
    const opt = document.createElement('option');
    opt.value = item[valueKey];
    opt.textContent = item[labelKey];
    selectEl.appendChild(opt);
  });
}

/* ── Badge helpers ──────────────────────────────────────────── */
function perfilBadge(perfil) {
  return perfil == 1
    ? '<span class="badge-ir badge-admin">⚡ Admin</span>'
    : '<span class="badge-ir badge-user">👤 Padrão</span>';
}

function ativoBadge(ativo) {
  return ativo == 1
    ? '<span class="badge-ir badge-active">● Ativo</span>'
    : '<span class="badge-ir badge-inactive">● Inativo</span>';
}

/* ── Category Icons for IR commands ────────────────────────── */
const CATEGORY_ICONS = {
  'Energia':     '⚡',
  'Temperatura': '🌡️',
  'Ventilação':  '💨',
  'Modo':        '🔄',
};

const MODE_ICONS = {
  'Quente':        '🔥',
  'Frio':          '❄️',
  'Ventilar':      '💨',
  'Desumidificar': '💧',
};

function getCategoryIcon(cat) { return CATEGORY_ICONS[cat] || '🔘'; }
function getModeIcon(mode)     { return MODE_ICONS[mode]    || '🔄'; }

/* ── Build IR button label ──────────────────────────────────── */
function buildCmdLabel(cmd) {
  const parts = [];
  if (cmd.modo)        parts.push(getModeIcon(cmd.modo) + ' ' + cmd.modo);
  if (cmd.temperatura) parts.push('🌡️ ' + cmd.temperatura + '°C');
  if (cmd.velocidade)  parts.push('💨 ' + cmd.velocidade);
  return parts.length ? parts.join('\n') : cmd.funcao;
}

/* ── Tipo device icon ───────────────────────────────────────── */
function tipoIcon(tipo) {
  const m = { 'Climatizador': '❄️', 'Projetor': '📽️', 'TV': '📺' };
  return m[tipo] || '🔌';
}
