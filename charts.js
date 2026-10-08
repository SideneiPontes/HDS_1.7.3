// /assets/js/charts.js
// Mantém os gráficos ilustrativos iniciais e permite atualização dinâmica via HDS_updateCharts(med).

'use strict';

// Guardas globais das instâncias dos gráficos
window._chartTemp  = null;
window._chartUmi   = null;
window._chartPress = null;
window._chartWind  = null;

// Garante que a lib Chart.js esteja presente antes de usar
function ensureChart() {
  if (!window.Chart) {
    console.warn('Chart.js não foi carregado antes de charts.js. Verifique a ordem dos scripts.');
    return false;
  }
  return true;
}

function safeGetCtx(id) {
  const el = document.getElementById(id);
  if (!el) return null;
  const ctx = el.getContext?.('2d');
  return ctx ? el : null;
}

function initializeCharts() {
  if (!ensureChart()) return;

  // Destroi instâncias existentes (se houver) para recriar limpo
  try { window._chartTemp?.destroy();  } catch {}
  try { window._chartUmi?.destroy();   } catch {}
  try { window._chartPress?.destroy(); } catch {}
  try { window._chartWind?.destroy();  } catch {}

  // ===== Gráfico 1 – Temperatura (linha) =====
  if (safeGetCtx('chartTemp')) {
    window._chartTemp = new Chart(document.getElementById('chartTemp'), {
      type: 'line',
      data: {
        labels: ['Jan','Fev','Mar','Abr','Mai','Jun'], // ilustrativo
        datasets: [{
          label: 'Temperatura (°C)',
          data: [22,24,21,20,23,25],                    // ilustrativo
          borderColor: 'rgba(255,99,132,1)',
          backgroundColor: 'rgba(255,99,132,0.2)',
          fill: true,
          tension: 0.3
        }]
      },
      options: { responsive:true, scales:{ y:{ beginAtZero:true } } }
    });
  }

  // ===== Gráfico 2 – Umidade (barra) =====
  if (safeGetCtx('chartUmi')) {
    window._chartUmi = new Chart(document.getElementById('chartUmi'), {
      type: 'bar',
      data: {
        labels: ['Jan','Fev','Mar','Abr','Mai','Jun'], // ilustrativo
        datasets: [{
          label: 'Umidade (%)',
          data: [60,65,70,75,68,72],                    // ilustrativo
          backgroundColor: 'rgba(54,162,235,0.7)'
        }]
      },
      options: { responsive:true, scales:{ y:{ beginAtZero:true, max:100 } } }
    });
  }

  // ===== Gráfico 3 – Pressão (linha) =====
  if (safeGetCtx('chartPress')) {
    window._chartPress = new Chart(document.getElementById('chartPress'), {
      type: 'line',
      data: {
        labels: ['Jan','Fev','Mar','Abr','Mai','Jun'], // ilustrativo
        datasets: [{
          label: 'Pressão (hPa)',
          data: [1012,1013,1011,1010,1014,1015],        // ilustrativo
          borderColor: 'rgba(255,206,86,1)',
          backgroundColor: 'rgba(255,206,86,0.2)',
          fill: true,
          tension: 0.3
        }]
      },
      options: { responsive:true, scales:{ y:{ beginAtZero:false } } }
    });
  }

  // ===== Gráfico 4 – Vento (barra) =====
  if (safeGetCtx('chartWind')) {
    window._chartWind = new Chart(document.getElementById('chartWind'), {
      type: 'bar',
      data: {
        labels: ['Jan','Fev','Mar','Abr','Mai','Jun'], // ilustrativo
        datasets: [{
          label: 'Vento (km/h)',
          data: [10,12,9,14,11,13],                     // ilustrativo
          backgroundColor: 'rgba(75,192,192,0.7)'
        }]
      },
      options: { responsive:true, scales:{ y:{ beginAtZero:true } } }
    });
  }
}

// Função pública para adicionar novos pontos quando chegarem medições reais
// Espera um objeto: { temp, humidity, wind, pressure? }
window.HDS_updateCharts = function (med) {
  if (!ensureChart()) return;

  try {
    const t = new Date();
    const hh = String(t.getHours()).padStart(2,'0');
    const mm = String(t.getMinutes()).padStart(2,'0');
    const label = `${hh}:${mm}`;

    // Temperatura
    if (window._chartTemp && Number.isFinite(med?.temp)) {
      const c = window._chartTemp;
      c.data.labels.push(label);
      c.data.datasets[0].data.push(Number(med.temp));
      if (c.data.labels.length > 60) { c.data.labels.shift(); c.data.datasets[0].data.shift(); }
      c.update();
    }

    // Umidade
    if (window._chartUmi && Number.isFinite(med?.humidity)) {
      const c = window._chartUmi;
      c.data.labels.push(label);
      c.data.datasets[0].data.push(Number(med.humidity));
      if (c.data.labels.length > 60) { c.data.labels.shift(); c.data.datasets[0].data.shift(); }
      c.update();
    }

    // Pressão (se fornecida)
    if (window._chartPress && Number.isFinite(med?.pressure)) {
      const c = window._chartPress;
      c.data.labels.push(label);
      c.data.datasets[0].data.push(Number(med.pressure));
      if (c.data.labels.length > 60) { c.data.labels.shift(); c.data.datasets[0].data.shift(); }
      c.update();
    }

    // Vento
    if (window._chartWind && Number.isFinite(med?.wind)) {
      const c = window._chartWind;
      c.data.labels.push(label);
      c.data.datasets[0].data.push(Number(med.wind));
      if (c.data.labels.length > 60) { c.data.labels.shift(); c.data.datasets[0].data.shift(); }
      c.update();
    }
  } catch (e) {
    console.warn('HDS_updateCharts falhou:', e);
  }
};

// Inicializa os gráficos quando a página carrega
document.addEventListener('DOMContentLoaded', () => {
  initializeCharts();
});

// Expõe initializeCharts para chamadas externas (dashboard.js)
window.initializeCharts = initializeCharts;
