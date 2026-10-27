/**
 * api.js — Módulo de comunicação com a API REST do backend
 * Wrapper sobre fetch() com tratamento de erros centralizado
 */

const BASE = '/esp32-ir/api';

/**
 * Executa uma requisição autenticada para a API.
 * @param {string} endpoint - Caminho relativo ao BASE (ex: '/salas.php')
 * @param {object} options  - Opções do fetch (method, body, etc.)
 * @returns {Promise<object>} - JSON retornado pela API
 */
async function apiFetch(endpoint, options = {}) {
  const url = BASE + endpoint;

  const defaults = {
    method:  'GET',
    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
    credentials: 'same-origin',
  };

  const config = { ...defaults, ...options };
  if (config.headers) config.headers = { ...defaults.headers, ...(options.headers || {}) };

  // Serializa body se for objeto
  if (config.body && typeof config.body === 'object') {
    config.body = JSON.stringify(config.body);
  }

  const response = await fetch(url, config);

  // Redirecionar para login se não autenticado
  if (response.status === 401) {
    window.location.href = '/esp32-ir/login.php';
    return;
  }

  const data = await response.json();

  if (!response.ok && !data) {
    throw new Error(`Erro HTTP ${response.status}`);
  }

  return data;
}

/* ── Atalhos semânticos ────────────────────────────────────── */

const api = {
  // Auth
  login:   (body)        => apiFetch('/auth/login.php',  { method: 'POST', body }),
  logout:  ()            => apiFetch('/auth/logout.php', { method: 'POST' }),
  me:      ()            => apiFetch('/auth/me.php'),

  // Salas
  salas:   {
    list:    ()     => apiFetch('/salas.php'),
    get:     (id)   => apiFetch(`/salas.php?id=${id}`),
    create:  (body) => apiFetch('/salas.php',        { method: 'POST',   body }),
    update:  (id, b)=> apiFetch(`/salas.php?id=${id}`, { method: 'PUT',  body: b }),
    delete:  (id)   => apiFetch(`/salas.php?id=${id}`, { method: 'DELETE' }),
  },

  // Dispositivos
  dispositivos: {
    list:       ()          => apiFetch('/dispositivos.php'),
    bySala:     (sala_id)   => apiFetch(`/dispositivos.php?sala_id=${sala_id}`),
    get:        (id)        => apiFetch(`/dispositivos.php?id=${id}`),
    create:     (body)      => apiFetch('/dispositivos.php',         { method: 'POST',   body }),
    update:     (id, b)     => apiFetch(`/dispositivos.php?id=${id}`, { method: 'PUT',   body: b }),
    delete:     (id)        => apiFetch(`/dispositivos.php?id=${id}`, { method: 'DELETE' }),
  },

  // Marcas
  marcas: {
    list:    ()     => apiFetch('/marcas.php'),
    create:  (body) => apiFetch('/marcas.php',          { method: 'POST',   body }),
    update:  (id,b) => apiFetch(`/marcas.php?id=${id}`, { method: 'PUT',   body: b }),
    delete:  (id)   => apiFetch(`/marcas.php?id=${id}`, { method: 'DELETE' }),
  },

  // Modelos
  modelos: {
    list:       (params = {}) => {
      const qs = new URLSearchParams(params).toString();
      return apiFetch('/modelos.php' + (qs ? '?' + qs : ''));
    },
    tipos:   () => apiFetch('/modelos.php?aux=tipos'),
    create:  (body) => apiFetch('/modelos.php',           { method: 'POST',   body }),
    update:  (id,b) => apiFetch(`/modelos.php?id=${id}`,  { method: 'PUT',   body: b }),
    delete:  (id)   => apiFetch(`/modelos.php?id=${id}`,  { method: 'DELETE' }),
  },

  // Funções IR + auxiliares
  funcoes: {
    list:        ()     => apiFetch('/funcoes_ir.php'),
    get:         (id)   => apiFetch(`/funcoes_ir.php?id=${id}`),
    modos:       ()     => apiFetch('/funcoes_ir.php?aux=modos'),
    temperaturas:()     => apiFetch('/funcoes_ir.php?aux=temperaturas'),
    velocidades: ()     => apiFetch('/funcoes_ir.php?aux=velocidades'),
    create:      (body) => apiFetch('/funcoes_ir.php',          { method: 'POST',   body }),
    update:      (id,b) => apiFetch(`/funcoes_ir.php?id=${id}`, { method: 'PUT',   body: b }),
    delete:      (id)   => apiFetch(`/funcoes_ir.php?id=${id}`, { method: 'DELETE' }),
  },

  // Códigos IR
  codigos: {
    list:           ()          => apiFetch('/codigos_ir.php'),
    byModelo:       (modelo_id) => apiFetch(`/codigos_ir.php?modelo_id=${modelo_id}`),
    create:         (body)      => apiFetch('/codigos_ir.php',          { method: 'POST',   body }),
    update:         (id, b)     => apiFetch(`/codigos_ir.php?id=${id}`, { method: 'PUT',   body: b }),
    delete:         (id)        => apiFetch(`/codigos_ir.php?id=${id}`, { method: 'DELETE' }),
    importarMatriz: (body)      => apiFetch('/codigos_matriz.php',      { method: 'POST',   body }),
  },

  // IR Disparo
  ir: {
    disparar: (param1, param2) => {
      let payload = typeof param1 === 'object' && param1 !== null
        ? param1
        : { codigo_ir_id: param1, dispositivo_id: param2 };
      return apiFetch('/ir/disparar.php', { method: 'POST', body: payload });
    },
  },

  // Usuários
  usuarios: {
    list:    ()     => apiFetch('/usuarios.php'),
    get:     (id)   => apiFetch(`/usuarios.php?id=${id}`),
    create:  (body) => apiFetch('/usuarios.php',           { method: 'POST',   body }),
    update:  (id,b) => apiFetch(`/usuarios.php?id=${id}`,  { method: 'PUT',   body: b }),
    delete:  (id)   => apiFetch(`/usuarios.php?id=${id}`,  { method: 'DELETE' }),
  },
};
