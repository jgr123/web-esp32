/**
 * dashboard.js — Lógica do Dashboard principal
 * Carrega salas, mostra dispositivos e controle remoto IR com interface avançada de Climatizador (+ / - e Modos)
 */

document.addEventListener('DOMContentLoaded', async () => {
  await loadStats();
  await loadRooms();
});

// Estado em memória de cada climatizador aberto (temperatura 16-30 e modo ativo)
const acDevicesState = {};

/* ── Stats ──────────────────────────────────────────────────── */
async function loadStats() {
  try {
    const [salas, disps] = await Promise.all([
      api.salas.list(),
      api.dispositivos.list(),
    ]);

    const totalSalas = salas.data?.length ?? 0;
    const totalDisp  = disps.data?.length ?? 0;
    const occupiedSlots = disps.data?.length ?? 0;

    document.getElementById('stat-salas').textContent  = totalSalas;
    document.getElementById('stat-disps').textContent  = totalDisp;
    document.getElementById('stat-slots').textContent  = (totalSalas * 4) - occupiedSlots;
  } catch (e) { /* silencioso */ }
}

/* ── Carregar grade de salas ────────────────────────────────── */
async function loadRooms() {
  const container = document.getElementById('rooms-grid');
  container.innerHTML = '<div class="empty-state"><div class="spinner-ir" style="width:32px;height:32px;border-width:3px;margin:0 auto"></div></div>';

  try {
    const res = await api.salas.list();
    const salas = res.data || [];

    if (!salas.length) {
      container.innerHTML = `
        <div class="empty-state" style="grid-column:1/-1">
          <div class="empty-icon">🏢</div>
          <div class="empty-text">Nenhuma sala cadastrada ainda.</div>
        </div>`;
      return;
    }

    container.innerHTML = salas.map(sala => {
      const total = parseInt(sala.total_dispositivos) || 0;
      const dots  = Array.from({length: 4}, (_, i) =>
        `<div class="slot-dot ${i < total ? (total >= 4 ? 'full' : 'occupied') : ''}"></div>`
      ).join('');

      return `
        <div class="room-card" onclick="openRoom(${sala.id}, '${escHtml(sala.nome)}')">
          <div class="room-card-icon">🏢</div>
          <div class="room-name">${escHtml(sala.nome)}</div>
          <div class="room-desc">${escHtml(sala.descricao || 'Sem descrição')}</div>
          <div style="display:flex;align-items:center;justify-content:space-between;margin-top:auto">
            <div class="slot-indicators">${dots}</div>
            <small style="color:var(--text-muted);font-size:.7rem">
              ${total}/4 dispositivos
            </small>
          </div>
        </div>`;
    }).join('');

  } catch (e) {
    container.innerHTML = `<div class="empty-state" style="grid-column:1/-1"><div class="empty-icon">⚠️</div><div class="empty-text">Erro ao carregar salas.</div></div>`;
  }
}

function escHtml(str) {
  return String(str || '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
}

/* ── Abrir Sala — modal de controle ─────────────────────────── */
async function openRoom(salaId, salaNome) {
  const modal     = new bootstrap.Modal(document.getElementById('roomModal'));
  const titleEl   = document.getElementById('roomModalTitle');
  const bodyEl    = document.getElementById('roomModalBody');

  titleEl.textContent = '🏢 ' + salaNome;
  bodyEl.innerHTML    = '<div class="d-flex-center py-4"><div class="spinner-ir" style="width:32px;height:32px;border-width:3px"></div></div>';
  modal.show();

  try {
    const res     = await api.salas.get(salaId);
    const sala    = res.data;
    const disps   = sala.dispositivos || [];

    if (!disps.length) {
      bodyEl.innerHTML = `<div class="empty-state"><div class="empty-icon">🔌</div><div class="empty-text">Nenhum dispositivo instalado nesta sala.</div></div>`;
      return;
    }

    // Tabs de dispositivos
    const tabIds = disps.map(d => `dev-${d.dispositivo_id}`);
    const tabsHtml = disps.map((d, i) => `
      <div class="device-tab ${i === 0 ? 'active' : ''}" onclick="switchDeviceTab('${tabIds[i]}', this)">
        ${tipoIcon(d.tipo)} <span>Slot ${d.slot} — ${escHtml(d.marca)}</span>
      </div>`
    ).join('');

    const panelsHtml = disps.map((d, i) => `
      <div id="${tabIds[i]}" class="device-panel" style="display:${i === 0 ? 'block' : 'none'}">
        <div class="d-flex-center py-3"><div class="spinner-ir" style="width:24px;height:24px;border-width:2px"></div></div>
      </div>`
    ).join('');

    bodyEl.innerHTML = `
      <div class="device-tabs">${tabsHtml}</div>
      ${panelsHtml}`;

    // Carregar comandos IR de cada dispositivo
    disps.forEach(d => loadDeviceRemote(d));

  } catch (e) {
    bodyEl.innerHTML = `<div class="empty-state"><div class="empty-icon">⚠️</div><div class="empty-text">Erro ao carregar dispositivos.</div></div>`;
  }
}

function switchDeviceTab(panelId, clickedTab) {
  document.querySelectorAll('.device-tab').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.device-panel').forEach(p => p.style.display = 'none');
  clickedTab.classList.add('active');
  document.getElementById(panelId).style.display = 'block';
}

/* ── Carregar Remote Control de um dispositivo ──────────────── */
async function loadDeviceRemote(disp) {
  const panelEl = document.getElementById(`dev-${disp.dispositivo_id}`);

  try {
    const res   = await api.dispositivos.get(disp.dispositivo_id);
    const data  = res.data;
    const cmds  = data.comandos || [];

    if (!cmds.length) {
      panelEl.innerHTML = `
        <div class="empty-state">
          <div class="empty-icon">📡</div>
          <div class="empty-text">Nenhum código IR cadastrado para este dispositivo.</div>
        </div>`;
      return;
    }

    const isClimatizador = (data.tipo || '').toLowerCase().includes('climatizador') ||
                           (data.tipo || '').toLowerCase().includes('ar');

    if (isClimatizador) {
      panelEl.innerHTML = renderClimatizadorRemote(data, cmds);
    } else {
      panelEl.innerHTML = renderGenericRemote(data, cmds);
    }

  } catch (e) {
    panelEl.innerHTML = `<div class="empty-state"><div class="empty-icon">⚠️</div><div class="empty-text">Erro ao carregar comandos.</div></div>`;
  }
}

/* ── Renderizador Especializado para Climatizadores ─────────── */
function renderClimatizadorRemote(data, cmds) {
  const dispId = data.dispositivo_id;

  // Inicializa o estado se ainda não existir
  if (!acDevicesState[dispId]) {
    acDevicesState[dispId] = {
      temp: 22,
      modo: 'Frio',
    };
  }

  const state = acDevicesState[dispId];

  // Identifica botões de energia
  const powerOnCmd  = cmds.find(c => c.funcao.toLowerCase() === 'ligar');
  const powerOffCmd = cmds.find(c => c.funcao.toLowerCase() === 'desligar');

  const powerButtonsHtml = `
    <div class="cmd-section">
      <div class="cmd-section-title">⚡ Energia</div>
      <div class="btn-group-ir cols-2">
        <button class="ir-btn power-on" onclick="${powerOnCmd ? `sendIR(${powerOnCmd.codigo_ir_id}, ${dispId}, this)` : `sendAcDirectPower(${dispId}, 'Ligar', this)`}" title="Ligar Climatizador">
          <span class="ir-btn-icon">⏻</span>
          <span class="ir-btn-label">Ligar</span>
        </button>
        <button class="ir-btn power-off" onclick="${powerOffCmd ? `sendIR(${powerOffCmd.codigo_ir_id}, ${dispId}, this)` : `sendAcDirectPower(${dispId}, 'Desligar', this)`}" title="Desligar Climatizador">
          <span class="ir-btn-icon">⏻</span>
          <span class="ir-btn-label">Desligar</span>
        </button>
      </div>
    </div>`;

  // Visor Digital + Controlador de Temperatura (Botões + / - e contador de 16 a 30)
  const isMinusDisabled = state.temp <= 16 ? 'disabled' : '';
  const isPlusDisabled  = state.temp >= 30 ? 'disabled' : '';

  const tempControlHtml = `
    <div class="cmd-section">
      <div class="cmd-section-title">🌡️ Temperatura Alvo</div>
      <div class="ac-screen">
        <div class="ac-screen-top">
          <span class="ac-screen-status">● Climatizador</span>
          <span class="ac-screen-mode-badge" id="ac-mode-badge-${dispId}">
            ${getModeIcon(state.modo)} ${state.modo}
          </span>
        </div>

        <div class="ac-temp-row">
          <button class="ac-temp-btn" id="btn-temp-minus-${dispId}" onclick="changeAcTemp(${dispId}, -1, this)" ${isMinusDisabled} title="Diminuir temperatura (-)">
            −
          </button>

          <div class="ac-temp-display">
            <span class="ac-temp-number" id="ac-temp-val-${dispId}">${state.temp}</span>
            <span class="ac-temp-celsius">°C</span>
          </div>

          <button class="ac-temp-btn" id="btn-temp-plus-${dispId}" onclick="changeAcTemp(${dispId}, +1, this)" ${isPlusDisabled} title="Aumentar temperatura (+)">
            +
          </button>
        </div>

        <div class="ac-temp-range-hint">Faixa de ajuste: 16°C mín &bull; 30°C máx</div>
      </div>
    </div>`;

  // Seletor de Modos (Quente, Frio, Ventilar, Desumidificar — Apenas um ativo)
  const MODOS = [
    { key: 'Quente',        label: 'Quente',        icon: '🔥' },
    { key: 'Frio',          label: 'Frio',          icon: '❄️' },
    { key: 'Ventilar',      label: 'Ventilar',      icon: '💨' },
    { key: 'Desumidificar', label: 'Desumidificar', icon: '💧' },
  ];

  const modesHtml = MODOS.map(m => {
    const isActive = state.modo === m.key ? 'active' : '';
    return `
      <button class="ac-mode-btn ${isActive}" data-mode="${m.key}" onclick="selectAcMode(${dispId}, '${m.key}', this)" title="Modo ${m.label}">
        <span class="mode-icon">${m.icon}</span>
        <span>${m.label}</span>
      </button>`;
  }).join('');

  const modesSectionHtml = `
    <div class="cmd-section">
      <div class="cmd-section-title">🔄 Modo de Operação (Seleção Única)</div>
      <div class="ac-modes-grid" id="ac-modes-group-${dispId}">
        ${modesHtml}
      </div>
    </div>`;

  // Funções extras (ex: Velocidades ou outros comandos que não sejam Ligar/Desligar/Modos/Temps)
  const extraCmds = cmds.filter(c => {
    const name = c.funcao.toLowerCase();
    return !name.includes('ligar') &&
           !name.includes('desligar') &&
           !c.temperatura &&
           !c.modo;
  });

  let extraHtml = '';
  if (extraCmds.length > 0) {
    const cols = extraCmds.length <= 2 ? 'cols-2' : extraCmds.length <= 3 ? 'cols-3' : 'cols-4';
    const extraButtons = extraCmds.map(cmd => `
      <button class="ir-btn" onclick="sendIR(${cmd.codigo_ir_id}, ${dispId}, this)" title="${escHtml(cmd.funcao)}">
        <span class="ir-btn-icon">${getCategoryIcon(cmd.categoria)}</span>
        <span class="ir-btn-label">${escHtml(cmd.funcao)}</span>
      </button>
    `).join('');

    extraHtml = `
      <div class="cmd-section">
        <div class="cmd-section-title">⚙️ Comandos Adicionais</div>
        <div class="btn-group-ir ${cols}">${extraButtons}</div>
      </div>`;
  }

  return `
    <div class="remote-panel mx-auto">
      <div class="remote-header">
        <div class="remote-brand">${escHtml(data.tipo)} • Slot ${data.slot}</div>
        <div class="remote-model">${escHtml(data.marca)} ${escHtml(data.modelo)}</div>
        ${data.numero_serie ? `<div style="font-size:.7rem;color:var(--text-muted);margin-top:.25rem">S/N: ${escHtml(data.numero_serie)}</div>` : ''}
      </div>
      ${powerButtonsHtml}
      ${tempControlHtml}
      ${modesSectionHtml}
      ${extraHtml}
    </div>`;
}

/* ── Alterar Temperatura do Climatizador (+ / -) ───────────── */
async function changeAcTemp(dispositivoId, delta, btn) {
  if (!acDevicesState[dispositivoId]) {
    acDevicesState[dispositivoId] = { temp: 22, modo: 'Frio' };
  }

  const state = acDevicesState[dispositivoId];
  const oldTemp = state.temp;
  const newTemp = Math.min(30, Math.max(16, oldTemp + delta));

  if (newTemp === oldTemp) return; // Limites 16 ou 30 atingidos

  state.temp = newTemp;

  // Atualiza display na tela imediatamente
  const tempValEl = document.getElementById(`ac-temp-val-${dispositivoId}`);
  if (tempValEl) tempValEl.textContent = newTemp;

  // Atualiza estado de desabilitação dos botões + e -
  const btnMinus = document.getElementById(`btn-temp-minus-${dispositivoId}`);
  const btnPlus  = document.getElementById(`btn-temp-plus-${dispositivoId}`);
  if (btnMinus) btnMinus.disabled = newTemp <= 16;
  if (btnPlus)  btnPlus.disabled  = newTemp >= 30;

  // Efeito visual no botão clicado
  btn.classList.add('sending');

  try {
    // Busca na base de dados o código IR correspondente e dispara para o ESP32
    const res = await api.ir.disparar({
      dispositivo_id: dispositivoId,
      temperatura:    newTemp,
      modo:           state.modo,
    });

    if (res.success) {
      showToast(res.message, 'success', `Ajustado para ${newTemp}°C`);
    } else {
      showToast(res.message || 'Código IR não encontrado para esta temperatura.', 'warning');
    }
  } catch (err) {
    showToast('Falha na comunicação com o servidor.', 'error');
  } finally {
    btn.classList.remove('sending');
  }
}

/* ── Selecionar Modo do Climatizador (Apenas um ativo) ─────── */
async function selectAcMode(dispositivoId, modo, clickedBtn) {
  if (!acDevicesState[dispositivoId]) {
    acDevicesState[dispositivoId] = { temp: 22, modo: 'Frio' };
  }

  const state = acDevicesState[dispositivoId];
  state.modo = modo;

  // Garante que APENAS o botão clicado fique com a classe .active
  const groupEl = document.getElementById(`ac-modes-group-${dispositivoId}`);
  if (groupEl) {
    groupEl.querySelectorAll('.ac-mode-btn').forEach(btn => btn.classList.remove('active'));
  }
  clickedBtn.classList.add('active');

  // Atualiza o badge de modo no visor digital
  const badgeEl = document.getElementById(`ac-mode-badge-${dispositivoId}`);
  if (badgeEl) {
    badgeEl.innerHTML = `${getModeIcon(modo)} ${modo}`;
  }

  clickedBtn.classList.add('sending');

  try {
    // Busca na base de dados o código IR do modo e dispara para o ESP32
    const res = await api.ir.disparar({
      dispositivo_id: dispositivoId,
      modo:           modo,
      temperatura:    state.temp,
    });

    if (res.success) {
      showToast(res.message, 'success', `Modo ${modo} Ativado`);
    } else {
      showToast(res.message || `Código IR para o Modo ${modo} não cadastrado.`, 'warning');
    }
  } catch (err) {
    showToast('Falha na comunicação com o servidor.', 'error');
  } finally {
    clickedBtn.classList.remove('sending');
  }
}

/* ── Ligar/Desligar direto do Climatizador ──────────────────── */
async function sendAcDirectPower(dispositivoId, acao, btn) {
  btn.classList.add('sending');
  try {
    const res = await api.dispositivos.get(dispositivoId);
    const cmds = res.data?.comandos || [];
    const match = cmds.find(c => c.funcao.toLowerCase() === acao.toLowerCase());

    if (match) {
      await sendIR(match.codigo_ir_id, dispositivoId, btn);
    } else {
      showToast(`Comando '${acao}' não cadastrado para este dispositivo.`, 'error');
    }
  } catch (e) {
    showToast('Falha na comunicação.', 'error');
  } finally {
    btn.classList.remove('sending');
  }
}

/* ── Renderizador Genérico (Projetores, TVs, etc.) ──────────── */
function renderGenericRemote(data, cmds) {
  const grouped = {};
  cmds.forEach(cmd => {
    const cat = cmd.categoria || 'Outros';
    if (!grouped[cat]) grouped[cat] = [];
    grouped[cat].push(cmd);
  });

  const remoteParts = Object.entries(grouped).map(([cat, commands]) => {
    const cols = commands.length <= 2 ? 'cols-2' : commands.length <= 3 ? 'cols-3' : 'cols-4';
    const buttons = commands.map(cmd => {
      const isOn  = cmd.funcao.toLowerCase().includes('ligar');
      const isOff = cmd.funcao.toLowerCase().includes('desligar');
      const cls   = isOn ? 'power-on' : isOff ? 'power-off' : '';

      return `
        <button class="ir-btn ${cls}" onclick="sendIR(${cmd.codigo_ir_id}, ${data.dispositivo_id}, this)" title="${escHtml(cmd.funcao)}">
          <span class="ir-btn-icon">${getCategoryIcon(cat)}</span>
          <span class="ir-btn-label">${escHtml(cmd.funcao)}</span>
        </button>`;
    }).join('');

    return `
      <div class="cmd-section">
        <div class="cmd-section-title">${getCategoryIcon(cat)} ${cat}</div>
        <div class="btn-group-ir ${cols}">${buttons}</div>
      </div>`;
  }).join('');

  return `
    <div class="remote-panel mx-auto">
      <div class="remote-header">
        <div class="remote-brand">${escHtml(data.tipo)} • Slot ${data.slot}</div>
        <div class="remote-model">${escHtml(data.marca)} ${escHtml(data.modelo)}</div>
        ${data.numero_serie ? `<div style="font-size:.7rem;color:var(--text-muted);margin-top:.25rem">S/N: ${escHtml(data.numero_serie)}</div>` : ''}
      </div>
      ${remoteParts}
    </div>`;
}

/* ── Enviar sinal IR padrão ─────────────────────────────────── */
async function sendIR(codigoIrId, dispositivoId, btn) {
  btn.classList.add('sending');
  const originalIcon = btn.querySelector('.ir-btn-icon')?.textContent;
  if (btn.querySelector('.ir-btn-icon')) btn.querySelector('.ir-btn-icon').textContent = '📡';

  try {
    const res = await api.ir.disparar(codigoIrId, dispositivoId);

    if (res.success) {
      showToast(res.message, 'success', 'Sinal Enviado');
      btn.style.borderColor = 'var(--accent-green)';
      btn.style.background  = 'rgba(16,185,129,0.15)';
      setTimeout(() => {
        btn.style.borderColor = '';
        btn.style.background  = '';
      }, 1200);
    } else {
      showToast(res.message || 'Erro ao enviar comando.', 'error');
    }
  } catch (e) {
    showToast('Falha na comunicação com o servidor.', 'error');
  } finally {
    btn.classList.remove('sending');
    if (btn.querySelector('.ir-btn-icon') && originalIcon) {
      btn.querySelector('.ir-btn-icon').textContent = originalIcon;
    }
  }
}
