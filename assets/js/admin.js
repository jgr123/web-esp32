/**
 * admin.js — Lógica completa do Painel Administrativo do IR Control
 * Gestão de Usuários, Salas, Dispositivos (Regra dos 4 slots), Códigos IR, Modelos e Marcas
 */

let modalSalaInstance = null;
let modalDispInstance = null;
let modalUserInstance = null;
let modalCodInstance  = null;
let modalFunInstance  = null;
let modalMarcaInstance = null;
let modalModeloInstance = null;
let modalMatrizInstance = null;

// Cache temporário para formulários
let cacheSalas = [];
let cacheModelos = [];
let cacheFuncoes = [];
let cacheMarcas = [];
let cacheTipos = [];

document.addEventListener('DOMContentLoaded', async () => {
  // Inicializa instâncias dos modais Bootstrap
  modalSalaInstance   = new bootstrap.Modal(document.getElementById('modalSala'));
  modalDispInstance   = new bootstrap.Modal(document.getElementById('modalDispositivo'));
  modalUserInstance   = new bootstrap.Modal(document.getElementById('modalUsuario'));
  modalCodInstance    = new bootstrap.Modal(document.getElementById('modalCodigo'));
  modalFunInstance    = new bootstrap.Modal(document.getElementById('modalFuncao'));
  modalMarcaInstance  = new bootstrap.Modal(document.getElementById('modalMarca'));
  modalModeloInstance = new bootstrap.Modal(document.getElementById('modalModelo'));
  modalMatrizInstance = new bootstrap.Modal(document.getElementById('modalMatriz'));

  // Detecta aba inicial pela URL (hash ou ?tab=)
  const urlParams = new URLSearchParams(window.location.search);
  const tabParam  = urlParams.get('tab');
  const hashParam = window.location.hash.replace('#', '');
  const initialTab = tabParam ? `tab-${tabParam}` : (hashParam || 'tab-salas');

  const tabLink = document.querySelector(`.admin-nav-tabs a[href="#${initialTab}"]`);
  if (tabLink) {
    switchAdminTab(initialTab, tabLink);
  } else {
    loadSalas();
  }
});

/* ── Troca de Abas ─────────────────────────────────────────── */
function switchAdminTab(tabId, clickedEl) {
  document.querySelectorAll('.admin-nav-tabs a').forEach(a => a.classList.remove('active'));
  document.querySelectorAll('.admin-section').forEach(s => s.style.display = 'none');

  if (clickedEl) clickedEl.classList.add('active');
  const targetSection = document.getElementById(tabId);
  if (targetSection) targetSection.style.display = 'block';

  // Carrega os dados correspondentes sob demanda
  if (tabId === 'tab-salas')        loadSalas();
  if (tabId === 'tab-dispositivos') loadDispositivos();
  if (tabId === 'tab-codigos')      loadCodigos();
  if (tabId === 'tab-usuarios')     loadUsuarios();
  if (tabId === 'tab-modelos')      { loadMarcas(); loadModelos(); }
}

/* ════════════════════════════════════════════════════════════════
   1. GERENCIAMENTO DE SALAS
   ════════════════════════════════════════════════════════════════ */
async function loadSalas() {
  const tbody = document.getElementById('salas-tbody');
  tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4"><span class="spinner-ir"></span> Carregando salas...</td></tr>';

  try {
    const res = await api.salas.list();
    cacheSalas = res.data || [];

    if (!cacheSalas.length) {
      tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">Nenhuma sala cadastrada.</td></tr>';
      return;
    }

    tbody.innerHTML = cacheSalas.map(sala => {
      const total = parseInt(sala.total_dispositivos) || 0;
      const livres = 4 - total;
      const badgeOcupacao = total >= 4
        ? `<span class="badge-ir badge-inactive">4/4 (Lotada)</span>`
        : `<span class="badge-ir badge-active">${total}/4 dispositivos (${livres} livres)</span>`;

      return `
        <tr>
          <td><code class="mono" style="color:var(--text-muted)">#${sala.id}</code></td>
          <td style="font-weight:600">${esc(sala.nome)}</td>
          <td style="color:var(--text-muted)">${esc(sala.descricao || '—')}</td>
          <td>${badgeOcupacao}</td>
          <td>${ativoBadge(sala.ativo)}</td>
          <td class="text-end">
            <button onclick="editSala(${sala.id})" class="btn-ghost-ir me-1" title="Editar">✏️ Editar</button>
            <button onclick="deleteSala(${sala.id}, '${esc(sala.nome)}')" class="btn-ghost-ir btn-danger-ir" title="Excluir">🗑️</button>
          </td>
        </tr>`;
    }).join('');

  } catch (e) {
    tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-danger">Erro ao carregar salas.</td></tr>';
  }
}

function openModalSala() {
  document.getElementById('formSala').reset();
  document.getElementById('sala-id').value = '';
  document.getElementById('modalSalaTitle').textContent = 'Nova Sala';
  document.getElementById('sala-ativo-group').style.display = 'none';
  modalSalaInstance.show();
}

function editSala(id) {
  const sala = cacheSalas.find(s => s.id == id);
  if (!sala) return;

  document.getElementById('sala-id').value = sala.id;
  document.getElementById('sala-nome').value = sala.nome;
  document.getElementById('sala-descricao').value = sala.descricao || '';
  document.getElementById('sala-ativo').value = sala.ativo;
  document.getElementById('sala-ativo-group').style.display = 'block';
  document.getElementById('modalSalaTitle').textContent = 'Editar Sala';
  modalSalaInstance.show();
}

async function saveSala(e) {
  e.preventDefault();
  const id   = document.getElementById('sala-id').value;
  const btn  = document.getElementById('btn-save-sala');
  const nome = document.getElementById('sala-nome').value.trim();
  const desc = document.getElementById('sala-descricao').value.trim();
  const ativo = document.getElementById('sala-ativo').value;

  setLoading(btn, true);
  try {
    const payload = { nome, descricao: desc, ativo: parseInt(ativo) };
    const res = id ? await api.salas.update(id, payload) : await api.salas.create(payload);

    if (res.success) {
      showToast(res.message, 'success');
      modalSalaInstance.hide();
      loadSalas();
    } else {
      showToast(res.message || 'Erro ao salvar sala.', 'error');
    }
  } catch (err) {
    showToast('Falha na comunicação com o servidor.', 'error');
  } finally {
    setLoading(btn, false);
  }
}

async function deleteSala(id, nome) {
  if (!confirmAction(`Tem certeza que deseja excluir a sala "${nome}"?\nTodos os dispositivos vinculados a ela serão desvinculados.`)) return;
  try {
    const res = await api.salas.delete(id);
    if (res.success) {
      showToast(res.message, 'success');
      loadSalas();
    } else {
      showToast(res.message || 'Erro ao remover sala.', 'error');
    }
  } catch (e) {
    showToast('Erro ao remover sala.', 'error');
  }
}

/* ════════════════════════════════════════════════════════════════
   2. GERENCIAMENTO DE DISPOSITIVOS (MÁX. 4 POR SALA)
   ════════════════════════════════════════════════════════════════ */
async function loadDispositivos() {
  const tbody = document.getElementById('dispositivos-tbody');
  tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4"><span class="spinner-ir"></span> Carregando dispositivos...</td></tr>';

  try {
    const res = await api.dispositivos.list();
    const disps = res.data || [];

    if (!disps.length) {
      tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">Nenhum dispositivo cadastrado.</td></tr>';
      return;
    }

    tbody.innerHTML = disps.map(d => {
      const slotColors = { 1: '#6366f1', 2: '#22d3ee', 3: '#10b981', 4: '#f59e0b' };
      const slotColor = slotColors[d.slot] || '#a855f7';
      const slotBadge = `<span class="badge-ir" style="background:rgba(255,255,255,0.06);border:1px solid ${slotColor};color:${slotColor}">Slot ${d.slot}</span>`;

      return `
        <tr>
          <td style="font-weight:600">🏢 ${esc(d.sala)}</td>
          <td>${slotBadge}</td>
          <td>${tipoIcon(d.tipo)} ${esc(d.tipo)}</td>
          <td><strong>${esc(d.marca)}</strong> ${esc(d.modelo)}</td>
          <td style="font-size:0.8rem;color:var(--text-muted)">
            ${d.numero_serie ? `<span class="mono">S/N: ${esc(d.numero_serie)}</span><br>` : ''}
            <span>${esc(d.descricao || 'Sem observações')}</span>
          </td>
          <td>${ativoBadge(d.ativo)}</td>
          <td class="text-end">
            <button onclick="editDispositivo(${d.dispositivo_id}, '${esc(d.numero_serie || '')}', '${esc(d.descricao || '')}', ${d.ativo})" class="btn-ghost-ir me-1" title="Editar">✏️ Editar</button>
            <button onclick="deleteDispositivo(${d.dispositivo_id}, '${esc(d.marca)} ${esc(d.modelo)} - Slot ${d.slot}')" class="btn-ghost-ir btn-danger-ir" title="Excluir">🗑️</button>
          </td>
        </tr>`;
    }).join('');

  } catch (e) {
    tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-danger">Erro ao carregar dispositivos.</td></tr>';
  }
}

async function openModalDispositivo() {
  document.getElementById('formDispositivo').reset();
  document.getElementById('disp-id').value = '';
  document.getElementById('modalDispTitle').textContent = 'Adicionar Dispositivo à Sala';
  document.getElementById('disp-sala-group').style.display = 'block';
  document.getElementById('disp-slot-group').style.display = 'block';
  document.getElementById('disp-modelo-group').style.display = 'block';
  document.getElementById('disp-ativo-group').style.display = 'none';

  const btnSave = document.getElementById('btn-save-disp');
  btnSave.disabled = false;

  // Carrega salas e modelos
  try {
    const [resSalas, resModelos] = await Promise.all([
      api.salas.list(),
      api.modelos.list(),
    ]);

    cacheSalas = resSalas.data || [];
    cacheModelos = resModelos.data || [];

    const selectSala = document.getElementById('disp-sala-id');
    populateSelect(selectSala, cacheSalas, 'id', 'nome', 'Selecione uma sala...');

    const selectModelo = document.getElementById('disp-modelo-id');
    selectModelo.innerHTML = '<option value="">Selecione um modelo...</option>' +
      cacheModelos.map(m => `<option value="${m.id}">${esc(m.tipo)} • ${esc(m.marca)} - ${esc(m.nome)}</option>`).join('');

    // Reseta slots
    document.getElementById('disp-slot').innerHTML = '<option value="">Selecione a sala primeiro...</option>';

    modalDispInstance.show();
  } catch (e) {
    showToast('Erro ao carregar salas e modelos.', 'error');
  }
}

/**
 * Validação reativa da Regra de Negócio: Máximo 4 aparelhos por sala
 * Desabilita slots já ocupados e bloqueia se sala estiver lotada
 */
async function onSalaSelectedForDisp() {
  const salaId = document.getElementById('disp-sala-id').value;
  const slotSelect = document.getElementById('disp-slot');
  const warningEl = document.getElementById('disp-slot-warning');
  const btnSave = document.getElementById('btn-save-disp');

  if (!salaId) {
    slotSelect.innerHTML = '<option value="">Selecione a sala primeiro...</option>';
    warningEl.className = 'alert alert-info py-2 px-3 mb-3';
    warningEl.innerHTML = 'ℹ️ Cada sala suporta no máximo <strong>4 dispositivos</strong> (slots 1 a 4).';
    btnSave.disabled = false;
    return;
  }

  slotSelect.innerHTML = '<option value="">Verificando slots...</option>';

  try {
    const res = await api.dispositivos.bySala(salaId);
    const ocupados = res.data || [];
    const slotsOcupadosMap = {};
    ocupados.forEach(d => { slotsOcupadosMap[d.slot] = d; });

    const totalOcupados = ocupados.length;

    if (totalOcupados >= 4) {
      // ── Sala já tem 4 dispositivos: Bloqueia ──
      warningEl.className = 'alert alert-danger py-2 px-3 mb-3';
      warningEl.innerHTML = '❌ <strong>Limite Atingido!</strong> Esta sala já possui 4 dispositivos cadastrados (capacidade máxima). Para adicionar outro, remova um dispositivo existente.';
      slotSelect.innerHTML = '<option value="">Sala lotada (4/4)</option>';
      slotSelect.disabled = true;
      btnSave.disabled = true;
      return;
    }

    slotSelect.disabled = false;
    btnSave.disabled = false;

    // Constrói as opções dos slots 1..4
    warningEl.className = 'alert alert-success py-2 px-3 mb-3';
    warningEl.innerHTML = `✅ Sala com <strong>${totalOcupados}/4</strong> dispositivos instalados. Slots livres disponíveis:`;

    let html = '<option value="">Selecione um slot livre...</option>';
    for (let s = 1; s <= 4; s++) {
      if (slotsOcupadosMap[s]) {
        const d = slotsOcupadosMap[s];
        html += `<option value="${s}" disabled>Slot ${s} — Ocupado por ${esc(d.marca)} ${esc(d.modelo)}</option>`;
      } else {
        html += `<option value="${s}">Slot ${s} — Disponível</option>`;
      }
    }
    slotSelect.innerHTML = html;

  } catch (e) {
    slotSelect.innerHTML = '<option value="">Erro ao carregar slots.</option>';
  }
}

function editDispositivo(id, serie, desc, ativo) {
  document.getElementById('formDispositivo').reset();
  document.getElementById('disp-id').value = id;
  document.getElementById('disp-serie').value = serie || '';
  document.getElementById('disp-descricao').value = desc || '';
  document.getElementById('disp-ativo').value = ativo;

  document.getElementById('modalDispTitle').textContent = 'Editar Dispositivo';
  document.getElementById('disp-sala-group').style.display = 'none';
  document.getElementById('disp-slot-group').style.display = 'none';
  document.getElementById('disp-modelo-group').style.display = 'none';
  document.getElementById('disp-ativo-group').style.display = 'block';

  document.getElementById('disp-slot-warning').className = 'alert alert-info py-2 px-3 mb-3';
  document.getElementById('disp-slot-warning').innerHTML = 'ℹ️ Editando número de série, observações e status do dispositivo.';

  document.getElementById('btn-save-disp').disabled = false;
  modalDispInstance.show();
}

async function saveDispositivo(e) {
  e.preventDefault();
  const id   = document.getElementById('disp-id').value;
  const btn  = document.getElementById('btn-save-disp');

  setLoading(btn, true);

  try {
    let res;
    if (id) {
      // Edição
      const payload = {
        numero_serie: document.getElementById('disp-serie').value.trim(),
        descricao:    document.getElementById('disp-descricao').value.trim(),
        ativo:        parseInt(document.getElementById('disp-ativo').value),
      };
      res = await api.dispositivos.update(id, payload);
    } else {
      // Criação e vinculação
      const payload = {
        sala_id:      parseInt(document.getElementById('disp-sala-id').value),
        slot:         parseInt(document.getElementById('disp-slot').value),
        modelo_id:    parseInt(document.getElementById('disp-modelo-id').value),
        numero_serie: document.getElementById('disp-serie').value.trim(),
        descricao:    document.getElementById('disp-descricao').value.trim(),
      };

      if (!payload.slot || payload.slot < 1 || payload.slot > 4) {
        showToast('Selecione um slot válido (entre 1 e 4).', 'warning');
        setLoading(btn, false);
        return;
      }

      res = await api.dispositivos.create(payload);
    }

    if (res.success) {
      showToast(res.message, 'success');
      modalDispInstance.hide();
      loadDispositivos();
    } else {
      showToast(res.message || 'Erro ao processar dispositivo.', 'error');
    }
  } catch (err) {
    showToast('Falha de conexão com o servidor.', 'error');
  } finally {
    setLoading(btn, false);
  }
}

async function deleteDispositivo(id, desc) {
  if (!confirmAction(`Deseja remover o dispositivo "${desc}"?`)) return;
  try {
    const res = await api.dispositivos.delete(id);
    if (res.success) {
      showToast(res.message, 'success');
      loadDispositivos();
    } else {
      showToast(res.message || 'Erro ao remover dispositivo.', 'error');
    }
  } catch (e) {
    showToast('Erro ao remover dispositivo.', 'error');
  }
}

/* ════════════════════════════════════════════════════════════════
   3. GERENCIAMENTO DE CÓDIGOS IR
   ════════════════════════════════════════════════════════════════ */
async function loadCodigos() {
  const tbody = document.getElementById('codigos-tbody');
  tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4"><span class="spinner-ir"></span> Carregando códigos IR...</td></tr>';

  try {
    const res = await api.codigos.list();
    const cods = res.data || [];

    if (!cods.length) {
      tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">Nenhum código IR cadastrado.</td></tr>';
      return;
    }

    tbody.innerHTML = cods.map(c => {
      const codePreview = c.codigo.length > 25 ? c.codigo.substring(0, 25) + '...' : c.codigo;
      return `
        <tr>
          <td><strong>${esc(c.marca)}</strong> ${esc(c.modelo)}</td>
          <td style="font-weight:600">${esc(c.funcao)}</td>
          <td><span class="badge-ir badge-user">${getCategoryIcon(c.categoria)} ${esc(c.categoria || 'Geral')}</span></td>
          <td><code class="mono" style="color:var(--accent-cyan)">${esc(c.protocolo || 'RAW')}</code></td>
          <td>${c.frequencia || 38} kHz</td>
          <td><code class="mono" style="font-size:0.75rem;color:var(--text-primary)" title="${esc(c.codigo)}">${esc(codePreview)}</code></td>
          <td class="text-end">
            <button onclick="testCodigoIR(${c.id}, this)" class="btn-ghost-ir me-1" style="border-color:var(--accent-green);color:var(--accent-green)" title="Testar Disparo">📡 Testar</button>
            <button onclick="editCodigo(${c.id})" class="btn-ghost-ir me-1" title="Editar">✏️</button>
            <button onclick="deleteCodigo(${c.id})" class="btn-ghost-ir btn-danger-ir" title="Excluir">🗑️</button>
          </td>
        </tr>`;
    }).join('');

  } catch (e) {
    tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-danger">Erro ao carregar códigos IR.</td></tr>';
  }
}

async function testCodigoIR(codigoId, btn) {
  setLoading(btn, true);
  try {
    const res = await api.ir.disparar(codigoId, null);
    if (res.success) {
      showToast(res.message, 'success', 'Sinal Enviado');
    } else {
      showToast(res.message || 'Erro ao disparar.', 'error');
    }
  } catch (e) {
    showToast('Falha na comunicação com o servidor.', 'error');
  } finally {
    setLoading(btn, false);
  }
}

async function openModalCodigo() {
  document.getElementById('formCodigo').reset();
  document.getElementById('cod-id').value = '';
  document.getElementById('modalCodTitle').textContent = 'Novo Código IR';
  document.getElementById('cod-modelo-group').style.display = 'block';
  document.getElementById('cod-funcao-group').style.display = 'block';

  try {
    const [resModelos, resFuncoes] = await Promise.all([
      api.modelos.list(),
      api.funcoes.list(),
    ]);

    cacheModelos = resModelos.data || [];
    cacheFuncoes = resFuncoes.data || [];

    const selectModelo = document.getElementById('cod-modelo-id');
    selectModelo.innerHTML = '<option value="">Selecione o modelo...</option>' +
      cacheModelos.map(m => `<option value="${m.id}">${esc(m.tipo)} • ${esc(m.marca)} - ${esc(m.nome)}</option>`).join('');

    const selectFuncao = document.getElementById('cod-funcao-id');
    selectFuncao.innerHTML = '<option value="">Selecione a função...</option>' +
      cacheFuncoes.map(f => `<option value="${f.id}">[${esc(f.categoria || 'Geral')}] ${esc(f.nome)}</option>`).join('');

    modalCodInstance.show();
  } catch (e) {
    showToast('Erro ao carregar modelos e funções.', 'error');
  }
}

async function editCodigo(id) {
  try {
    const res = await apiFetch(`/codigos_ir.php?id=${id}`);
    const cod = res.data;
    if (!cod) return;

    document.getElementById('cod-id').value = cod.id;
    document.getElementById('cod-protocolo').value = cod.protocolo || '';
    document.getElementById('cod-frequencia').value = cod.frequencia || 38;
    document.getElementById('cod-codigo').value = cod.codigo;

    document.getElementById('modalCodTitle').textContent = `Editar Código IR: ${cod.marca} ${cod.modelo} - ${cod.funcao}`;
    document.getElementById('cod-modelo-group').style.display = 'none';
    document.getElementById('cod-funcao-group').style.display = 'none';

    modalCodInstance.show();
  } catch (e) {
    showToast('Erro ao buscar dados do código.', 'error');
  }
}

async function saveCodigo(e) {
  e.preventDefault();
  const id   = document.getElementById('cod-id').value;
  const btn  = document.getElementById('btn-save-cod');

  setLoading(btn, true);

  try {
    let res;
    if (id) {
      res = await api.codigos.update(id, {
        protocolo:  document.getElementById('cod-protocolo').value.trim(),
        frequencia: parseInt(document.getElementById('cod-frequencia').value) || 38,
        codigo:     document.getElementById('cod-codigo').value.trim(),
      });
    } else {
      res = await api.codigos.create({
        modelo_id:  parseInt(document.getElementById('cod-modelo-id').value),
        funcao_id:  parseInt(document.getElementById('cod-funcao-id').value),
        protocolo:  document.getElementById('cod-protocolo').value.trim(),
        frequencia: parseInt(document.getElementById('cod-frequencia').value) || 38,
        codigo:     document.getElementById('cod-codigo').value.trim(),
      });
    }

    if (res.success) {
      showToast(res.message, 'success');
      modalCodInstance.hide();
      loadCodigos();
    } else {
      showToast(res.message || 'Erro ao salvar código IR.', 'error');
    }
  } catch (err) {
    showToast('Falha na comunicação com o servidor.', 'error');
  } finally {
    setLoading(btn, false);
  }
}

async function deleteCodigo(id) {
  if (!confirmAction('Tem certeza que deseja excluir este código IR?')) return;
  try {
    const res = await api.codigos.delete(id);
    if (res.success) {
      showToast(res.message, 'success');
      loadCodigos();
    } else {
      showToast(res.message || 'Erro ao remover código.', 'error');
    }
  } catch (e) {
    showToast('Erro ao remover código.', 'error');
  }
}

/* ── Modal Função IR Auxiliar ──────────────────────────────── */
async function openModalFuncao() {
  document.getElementById('formFuncao').reset();

  try {
    const [resModos, resTemps, resVels] = await Promise.all([
      api.funcoes.modos(),
      api.funcoes.temperaturas(),
      api.funcoes.velocidades(),
    ]);

    populateSelect(document.getElementById('fun-modo-id'), resModos.data || [], 'id', 'nome', 'Nenhum');
    
    // Temperaturas com °C
    const tempSelect = document.getElementById('fun-temp-id');
    tempSelect.innerHTML = '<option value="">Nenhuma</option>' +
      (resTemps.data || []).map(t => `<option value="${t.id}">${t.valor_c}°C</option>`).join('');

    populateSelect(document.getElementById('fun-vel-id'), resVels.data || [], 'id', 'descricao', 'Nenhuma');

    modalFunInstance.show();
  } catch (e) {
    showToast('Erro ao carregar dados auxiliares.', 'error');
  }
}

async function saveFuncao(e) {
  e.preventDefault();
  const btn = document.getElementById('btn-save-fun');
  setLoading(btn, true);

  try {
    const payload = {
      nome:           document.getElementById('fun-nome').value.trim(),
      categoria:      document.getElementById('fun-categoria').value,
      modo_id:        document.getElementById('fun-modo-id').value || null,
      temperatura_id: document.getElementById('fun-temp-id').value || null,
      velocidade_id:  document.getElementById('fun-vel-id').value  || null,
    };

    const res = await api.funcoes.create(payload);
    if (res.success) {
      showToast(res.message, 'success');
      modalFunInstance.hide();
    } else {
      showToast(res.message || 'Erro ao criar função.', 'error');
    }
  } catch (err) {
    showToast('Falha na comunicação.', 'error');
  } finally {
    setLoading(btn, false);
  }
}

/* ── Importação de Códigos IR em Matriz ─────────────────────── */
function openModalMatriz() {
  const txt = document.getElementById('matriz-texto');
  if (txt) txt.value = '';
  const fileInp = document.getElementById('matriz-file-input');
  if (fileInp) fileInp.value = '';
  const prevBox = document.getElementById('matriz-preview-box');
  if (prevBox) prevBox.style.display = 'none';
  const btnSave = document.getElementById('btn-save-matriz');
  if (btnSave) btnSave.disabled = true;
  const countBadge = document.getElementById('matriz-count-badge');
  if (countBadge) countBadge.innerHTML = 'Nenhum dado processado ainda.';

  if (!modalMatrizInstance) {
    const el = document.getElementById('modalMatriz');
    if (el && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
      modalMatrizInstance = bootstrap.Modal.getInstance(el) || new bootstrap.Modal(el);
    }
  }
  if (modalMatrizInstance) {
    modalMatrizInstance.show();
  }
}

function limparMatriz() {
  document.getElementById('matriz-texto').value = '';
  document.getElementById('matriz-file-input').value = '';
  document.getElementById('matriz-preview-box').style.display = 'none';
  document.getElementById('btn-save-matriz').disabled = true;
  document.getElementById('matriz-count-badge').innerHTML = 'Nenhum dado processado ainda.';
}

function carregarExemploMatriz() {
  const exemplo = `Climatizador, Elgin, ELGIN sala RA, COOLIX, Frio, 17, Auto, 0xB21F00
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
Climatizador, Elgin, ELGIN sala RA, COOLIX, Automárico, -, -, B29F10`;

  document.getElementById('matriz-texto').value = exemplo;
  previewMatriz();
}

function handleMatrizFileUpload(e) {
  const file = e.target.files[0];
  if (!file) return;

  const reader = new FileReader();
  reader.onload = function(evt) {
    document.getElementById('matriz-texto').value = evt.target.result;
    previewMatriz();
  };
  reader.readAsText(file);
}

async function previewMatriz() {
  const texto = document.getElementById('matriz-texto').value.trim();
  const btn = document.getElementById('btn-preview-matriz');
  const previewBox = document.getElementById('matriz-preview-box');
  const tbody = document.getElementById('matriz-preview-tbody');
  const countBadge = document.getElementById('matriz-count-badge');
  const errosBox = document.getElementById('matriz-erros-box');
  const statsBox = document.getElementById('matriz-stats');
  const btnSave = document.getElementById('btn-save-matriz');

  if (!texto) {
    showToast('Informe ou cole os dados da matriz primeiro.', 'warning');
    return;
  }

  setLoading(btn, true);

  try {
    const res = await api.codigos.importarMatriz({ texto, dry_run: true });

    if (!res.success) {
      showToast(res.message || 'Erro ao processar matriz.', 'error');
      errosBox.style.display = 'block';
      errosBox.innerHTML = (res.erros || [res.message]).map(e => `<div>⚠️ ${esc(e)}</div>`).join('');
      previewBox.style.display = 'block';
      tbody.innerHTML = '';
      btnSave.disabled = true;
      return;
    }

    const itens = res.itens || [];
    countBadge.innerHTML = `<span class="badge" style="background: rgba(16,185,129,0.2); color: var(--accent-green); font-size: 0.8rem;">✓ ${itens.length} linha(s) válida(s)</span>`;

    if (res.erros && res.erros.length > 0) {
      errosBox.style.display = 'block';
      errosBox.innerHTML = res.erros.map(e => `<div>⚠️ ${esc(e)}</div>`).join('');
    } else {
      errosBox.style.display = 'none';
    }

    statsBox.innerHTML = `
      <span class="badge" style="background: rgba(6,182,212,0.15); color: var(--accent-cyan);">Total: ${itens.length}</span>
    `;

    tbody.innerHTML = itens.map((item) => `
      <tr>
        <td style="color: var(--text-muted); font-size: 0.7rem;">${item.linha}</td>
        <td><span class="badge" style="background: rgba(255,255,255,0.06);">${esc(item.tipo)}</span></td>
        <td><strong>${esc(item.marca)}</strong></td>
        <td>${esc(item.modelo)}</td>
        <td><code>${esc(item.protocolo || 'Padrão')}</code></td>
        <td><span style="color: var(--text-primary); font-weight: 500;">${esc(item.modo_nome || '-')}</span></td>
        <td>${item.temperatura ? `<span class="badge bg-secondary">${item.temperatura}°C</span>` : '<span style="color:var(--text-muted)">-</span>'}</td>
        <td>${item.velocidade ? `<span class="badge bg-dark border">${esc(item.velocidade)}</span>` : '<span style="color:var(--text-muted)">-</span>'}</td>
        <td><code style="color: var(--accent-green); font-weight: 600;">${esc(item.codigo)}</code></td>
        <td><span style="font-size: 0.72rem; color: var(--accent-cyan);">${esc(item.funcao_sugerida)}</span></td>
      </tr>
    `).join('');

    previewBox.style.display = 'block';
    btnSave.disabled = (itens.length === 0);

    showToast(`${itens.length} linha(s) processadas para pré-visualização.`, 'success');
  } catch (err) {
    showToast('Falha na comunicação com o servidor.', 'error');
  } finally {
    setLoading(btn, false);
  }
}

async function salvarMatrizBanco() {
  const texto = document.getElementById('matriz-texto').value.trim();
  const btn = document.getElementById('btn-save-matriz');

  if (!texto) {
    showToast('Nenhum dado na matriz para salvar.', 'warning');
    return;
  }

  setLoading(btn, true);

  try {
    const res = await api.codigos.importarMatriz({ texto, dry_run: false });

    if (res.success) {
      showToast(res.message, 'success', 'Importação Concluída');
      if (modalMatrizInstance) {
        modalMatrizInstance.hide();
      } else {
        const el = document.getElementById('modalMatriz');
        const inst = (typeof bootstrap !== 'undefined' && bootstrap.Modal) ? bootstrap.Modal.getInstance(el) : null;
        if (inst) inst.hide();
      }

      // Recarrega as tabelas afetadas
      await Promise.all([
        loadCodigos(),
        loadModelos(),
      ]);
    } else {
      showToast(res.message || 'Erro ao gravar matriz.', 'error');
      if (res.erros && res.erros.length > 0) {
        const errosBox = document.getElementById('matriz-erros-box');
        errosBox.style.display = 'block';
        errosBox.innerHTML = res.erros.map(e => `<div>⚠️ ${esc(e)}</div>`).join('');
      }
    }
  } catch (err) {
    showToast('Erro ao gravar dados da matriz no servidor.', 'error');
  } finally {
    setLoading(btn, false);
  }
}

/* ════════════════════════════════════════════════════════════════
   4. GERENCIAMENTO DE USUÁRIOS
   ════════════════════════════════════════════════════════════════ */
async function loadUsuarios() {
  const tbody = document.getElementById('usuarios-tbody');
  tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4"><span class="spinner-ir"></span> Carregando usuários...</td></tr>';

  try {
    const res = await api.usuarios.list();
    const users = res.data || [];

    if (!users.length) {
      tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">Nenhum usuário cadastrado.</td></tr>';
      return;
    }

    tbody.innerHTML = users.map(u => `
      <tr>
        <td><code class="mono" style="color:var(--text-muted)">#${u.id}</code></td>
        <td style="font-weight:600">${esc(u.nome_real)}</td>
        <td><code class="mono" style="color:var(--accent-cyan)">${esc(u.username)}</code></td>
        <td>${esc(u.email)}</td>
        <td>${perfilBadge(u.perfil)}</td>
        <td>${ativoBadge(u.ativo)}</td>
        <td class="text-end">
          <button onclick="editUsuario(${u.id})" class="btn-ghost-ir me-1" title="Editar">✏️ Editar</button>
          <button onclick="deleteUsuario(${u.id}, '${esc(u.username)}')" class="btn-ghost-ir btn-danger-ir" title="Excluir">🗑️</button>
        </td>
      </tr>
    `).join('');

  } catch (e) {
    tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-danger">Erro ao carregar usuários.</td></tr>';
  }
}

function openModalUsuario() {
  document.getElementById('formUsuario').reset();
  document.getElementById('user-id').value = '';
  document.getElementById('modalUserTitle').textContent = 'Novo Usuário';
  document.getElementById('user-senha').required = true;
  document.getElementById('user-senha-label').textContent = 'Senha *';
  document.getElementById('user-senha-hint').style.display = 'none';
  document.getElementById('user-ativo-group').style.display = 'none';
  modalUserInstance.show();
}

async function editUsuario(id) {
  try {
    const res = await api.usuarios.get(id);
    const u = res.data;
    if (!u) return;

    document.getElementById('user-id').value = u.id;
    document.getElementById('user-nome-real').value = u.nome_real;
    document.getElementById('user-username').value = u.username;
    document.getElementById('user-email').value = u.email;
    document.getElementById('user-perfil').value = u.perfil;
    document.getElementById('user-ativo').value = u.ativo;

    document.getElementById('modalUserTitle').textContent = `Editar Usuário: ${u.username}`;
    document.getElementById('user-senha').value = '';
    document.getElementById('user-senha').required = false;
    document.getElementById('user-senha-label').textContent = 'Nova Senha (Opcional)';
    document.getElementById('user-senha-hint').style.display = 'block';
    document.getElementById('user-ativo-group').style.display = 'block';

    modalUserInstance.show();
  } catch (e) {
    showToast('Erro ao buscar dados do usuário.', 'error');
  }
}

async function saveUsuario(e) {
  e.preventDefault();
  const id   = document.getElementById('user-id').value;
  const btn  = document.getElementById('btn-save-user');
  const nome = document.getElementById('user-nome-real').value.trim();
  const user = document.getElementById('user-username').value.trim();
  const mail = document.getElementById('user-email').value.trim();
  const pass = document.getElementById('user-senha').value;
  const perf = parseInt(document.getElementById('user-perfil').value);

  setLoading(btn, true);

  try {
    let res;
    if (id) {
      const payload = {
        nome_real: nome,
        username:  user,
        email:     mail,
        perfil:    perf,
        ativo:     parseInt(document.getElementById('user-ativo').value),
      };
      if (pass) payload.senha = pass;
      res = await api.usuarios.update(id, payload);
    } else {
      res = await api.usuarios.create({
        nome_real: nome,
        username:  user,
        email:     mail,
        senha:     pass,
        perfil:    perf,
      });
    }

    if (res.success) {
      showToast(res.message, 'success');
      modalUserInstance.hide();
      loadUsuarios();
    } else {
      showToast(res.message || 'Erro ao salvar usuário.', 'error');
    }
  } catch (err) {
    showToast('Falha na comunicação com o servidor.', 'error');
  } finally {
    setLoading(btn, false);
  }
}

async function deleteUsuario(id, username) {
  if (!confirmAction(`Tem certeza que deseja excluir o usuário "${username}"?`)) return;
  try {
    const res = await api.usuarios.delete(id);
    if (res.success) {
      showToast(res.message, 'success');
      loadUsuarios();
    } else {
      showToast(res.message || 'Erro ao remover usuário.', 'error');
    }
  } catch (e) {
    showToast('Erro ao remover usuário.', 'error');
  }
}

/* ════════════════════════════════════════════════════════════════
   5. GERENCIAMENTO DE MARCAS E MODELOS
   ════════════════════════════════════════════════════════════════ */
async function loadMarcas() {
  const tbody = document.getElementById('marcas-tbody');
  tbody.innerHTML = '<tr><td colspan="3" class="text-center py-3"><span class="spinner-ir"></span></td></tr>';

  try {
    const res = await api.marcas.list();
    cacheMarcas = res.data || [];

    if (!cacheMarcas.length) {
      tbody.innerHTML = '<tr><td colspan="3" class="text-center py-3 text-muted">Nenhuma marca.</td></tr>';
      return;
    }

    tbody.innerHTML = cacheMarcas.map(m => `
      <tr>
        <td><code class="mono" style="color:var(--text-muted)">#${m.id}</code></td>
        <td style="font-weight:600">${esc(m.nome)}</td>
        <td class="text-end">
          <button onclick="deleteMarca(${m.id}, '${esc(m.nome)}')" class="btn-ghost-ir btn-danger-ir" title="Excluir">🗑️</button>
        </td>
      </tr>
    `).join('');
  } catch (e) {
    tbody.innerHTML = '<tr><td colspan="3" class="text-center py-3 text-danger">Erro.</td></tr>';
  }
}

function openModalMarca() {
  document.getElementById('formMarca').reset();
  modalMarcaInstance.show();
}

async function saveMarca(e) {
  e.preventDefault();
  const btn = document.getElementById('btn-save-marca');
  const nome = document.getElementById('marca-nome').value.trim();
  setLoading(btn, true);

  try {
    const res = await api.marcas.create({ nome });
    if (res.success) {
      showToast(res.message, 'success');
      modalMarcaInstance.hide();
      loadMarcas();
    } else {
      showToast(res.message || 'Erro ao cadastrar marca.', 'error');
    }
  } catch (err) {
    showToast('Falha na comunicação.', 'error');
  } finally {
    setLoading(btn, false);
  }
}

async function deleteMarca(id, nome) {
  if (!confirmAction(`Deseja remover a marca "${nome}"?`)) return;
  try {
    const res = await api.marcas.delete(id);
    if (res.success) {
      showToast(res.message, 'success');
      loadMarcas();
    } else {
      showToast(res.message || 'Erro ao remover marca.', 'error');
    }
  } catch (e) {
    showToast('Não é possível remover marca com modelos associados.', 'error');
  }
}

async function loadModelos() {
  const tbody = document.getElementById('modelos-tbody');
  tbody.innerHTML = '<tr><td colspan="5" class="text-center py-3"><span class="spinner-ir"></span></td></tr>';

  try {
    const res = await api.modelos.list();
    cacheModelos = res.data || [];

    if (!cacheModelos.length) {
      tbody.innerHTML = '<tr><td colspan="5" class="text-center py-3 text-muted">Nenhum modelo.</td></tr>';
      return;
    }

    tbody.innerHTML = cacheModelos.map(m => `
      <tr>
        <td style="font-weight:600">${esc(m.nome)}</td>
        <td>${esc(m.marca)}</td>
        <td>${tipoIcon(m.tipo)} ${esc(m.tipo)}</td>
        <td><code class="mono" style="font-size:0.75rem">${esc(m.referencia || '—')}</code></td>
        <td class="text-end">
          <button onclick="deleteModelo(${m.id}, '${esc(m.nome)}')" class="btn-ghost-ir btn-danger-ir" title="Excluir">🗑️</button>
        </td>
      </tr>
    `).join('');
  } catch (e) {
    tbody.innerHTML = '<tr><td colspan="5" class="text-center py-3 text-danger">Erro.</td></tr>';
  }
}

async function openModalModelo() {
  document.getElementById('formModelo').reset();

  try {
    const [resMarcas, resTipos] = await Promise.all([
      api.marcas.list(),
      api.modelos.tipos(),
    ]);

    populateSelect(document.getElementById('modelo-marca-id'), resMarcas.data || [], 'id', 'nome', 'Selecione a marca...');
    populateSelect(document.getElementById('modelo-tipo-id'), resTipos.data || [], 'id', 'nome', 'Selecione o tipo...');

    modalModeloInstance.show();
  } catch (e) {
    showToast('Erro ao carregar dados auxiliares.', 'error');
  }
}

async function saveModelo(e) {
  e.preventDefault();
  const btn = document.getElementById('btn-save-modelo');
  setLoading(btn, true);

  try {
    const payload = {
      marca_id:   parseInt(document.getElementById('modelo-marca-id').value),
      tipo_id:    parseInt(document.getElementById('modelo-tipo-id').value),
      nome:       document.getElementById('modelo-nome').value.trim(),
      referencia: document.getElementById('modelo-referencia').value.trim() || null,
    };

    const res = await api.modelos.create(payload);
    if (res.success) {
      showToast(res.message, 'success');
      modalModeloInstance.hide();
      loadModelos();
    } else {
      showToast(res.message || 'Erro ao cadastrar modelo.', 'error');
    }
  } catch (err) {
    showToast('Falha na comunicação.', 'error');
  } finally {
    setLoading(btn, false);
  }
}

async function deleteModelo(id, nome) {
  if (!confirmAction(`Deseja remover o modelo "${nome}"?`)) return;
  try {
    const res = await api.modelos.delete(id);
    if (res.success) {
      showToast(res.message, 'success');
      loadModelos();
    } else {
      showToast(res.message || 'Erro ao remover modelo.', 'error');
    }
  } catch (e) {
    showToast('Não é possível remover modelo em uso por dispositivos.', 'error');
  }
}

/* ── Escaping Helper ────────────────────────────────────────── */
function esc(str) {
  return String(str || '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
}
