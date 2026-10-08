// /config/js/semaforo.js
'use strict';

(() => {
  const HISTERESIS_MS = 20000;
  let estadoAtual = 'AGUARDANDO';
  let ultimaTroca = 0;

  // --- Utils numéricos
  const num = (v) => Number.isFinite(Number(v)) ? Number(v) : NaN;

  // =================== [NOVO] loader + decisão baseada em criterios.json =================== //
  async function loadCriteriosLocal() {
    if (window.HDS_CRITERIOS) return window.HDS_CRITERIOS; // reaproveita cache global do dashboard, se existir
    const base = window.HDS_BASE || (function(){
      const parts = location.pathname.split('/').filter(Boolean);
      return parts.length>=1?('/'+parts[0]):'';
    })();
    const url = `${base}/control/controle.php?a=criterios&_=${Date.now()}`;
    const res = await fetch(url, { cache: 'no-store' });
    window.HDS_CRITERIOS = await res.json();
    return window.HDS_CRITERIOS;
  }

  function classePorBandas(valor, bandas) {
    const v = Number(valor);
    if (!Number.isFinite(v)) return 'RED';
    for (const b of bandas) {
      const minOk = (b.from === null) ? true : (b.from_inclusive ? v >= b.from : v > b.from);
      const maxOk = (b.to   === null) ? true : (b.to_inclusive   ? v <= b.to   : v < b.to);
      if (minOk && maxOk) return b.classe;
    }
    return 'RED';
  }

  function decideEstadoPorCriterios({ temp, humidity, windKmh }, C) {
    const w = classePorBandas(windKmh, C.vento_kmh);
    const u = classePorBandas(humidity, C.umidade_pct);
    const t = classePorBandas(temp,     C.temperatura_c);
    if (w === 'RED' || u === 'RED' || t === 'RED') return 'STOP';
    if (w === 'GREEN' && u === 'GREEN' && t === 'GREEN') return 'OK';
    return 'ATENCAO';
  }

  // --- Atualização do semáforo ---
  function setLamps(r, y, g) {
    document.getElementById('lampRed')?.classList.toggle('on', !!r);
    document.getElementById('lampYellow')?.classList.toggle('on', !!y);
    document.getElementById('lampGreen')?.classList.toggle('on', !!g);
  }

  function setStatusBadge(txt, cls) {
    const el = document.getElementById('statusBadge');
    if (!el) return;
    el.textContent = txt;
    el.classList.remove('status-OK', 'status-ATEN', 'status-STOP');
    if (cls) el.classList.add(cls);
  }

  function setHint(txt) {
    const el = document.getElementById('statusHint');
    if (el) el.textContent = txt;
  }

  function setMetrics(med) {
    const el = document.getElementById('metricsLine');
    if (!el || !med) return;
    const t = Number.isFinite(med.temp) ? med.temp.toFixed(1) : '—';
    const h = Number.isFinite(med.humidity) ? med.humidity.toFixed(1) : '—';
    const w = Number.isFinite(med.wind) ? med.wind.toFixed(1) : '—';
    el.innerHTML =
      `<span><strong>T:</strong> <code>${t} °C</code></span> ·
       <span><strong>UR:</strong> <code>${h} %</code></span> ·
       <span><strong>Vento:</strong> <code>${w} km/h</code></span>`;
  }

  function atualizarUIEstado(novoEstado, med) {
    const agora = Date.now();

    // Histerese: evita alternância rápida
    if (estadoAtual !== 'AGUARDANDO' && novoEstado !== estadoAtual && (agora - ultimaTroca) < HISTERESIS_MS) {
      novoEstado = estadoAtual;
    } else if (novoEstado !== estadoAtual) {
      estadoAtual = novoEstado;
      ultimaTroca = agora;
    }

    if (estadoAtual === 'OK') {
      setLamps(false, false, true);
      setStatusBadge('VERDE — Pode aplicar', 'status-OK');
      setHint('Condições ideais para aplicação.');
    } else if (estadoAtual === 'ATENCAO') {
      setLamps(false, true, false);
      setStatusBadge('AMARELO — Atenção', 'status-ATEN');
      setHint('Condições intermediárias. Atenção às condições climáticas.');
    } else if (estadoAtual === 'STOP') {
      setLamps(true, false, false);
      setStatusBadge('VERMELHO — Não aplicar', 'status-STOP');
      setHint('Fora dos limites favoráveis à aplicação.');
    } else {
      setLamps(false, false, false);
      setStatusBadge('Aguardando dados…', '');
      setHint('Envie dados para avaliar.');
    }

    setMetrics(med);
  }

  // === Integração: agora a decisão usa criterios.json (mesmo do backend) ===
  window.HDS_setMeasurements = async (med) => {
    const safe = {
      temp:     num(med?.temp),
      humidity: num(med?.humidity),
      wind:     num(med?.wind), // km/h
    };
    const C = await loadCriteriosLocal();
    const novoEstado = decideEstadoPorCriterios({
      temp: safe.temp,
      humidity: safe.humidity,
      windKmh: safe.wind
    }, C);
    atualizarUIEstado(novoEstado, safe);
  };

  // Útil para debug/inspeção
  window.HDS_decideEstado = async (m) => {
    const C = await loadCriteriosLocal();
    return decideEstadoPorCriterios({ temp: m.temp, humidity: m.humidity, windKmh: m.wind }, C);
  };

  // Estado inicial ao carregar a página
  document.addEventListener('DOMContentLoaded', () => {
    atualizarUIEstado('AGUARDANDO');
  });
})();
