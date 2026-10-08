/* HDS — dashboard.js
 * Coleta central a cada 60 s, independente do módulo visível.
 * Requer as rotas existentes em control/controle.php e Chart.js no PHP.
 * A coleta depende do navegador aberto; não é uma tarefa do servidor.
 */
(() => {
"use strict";
if (window.__HDS_DASHBOARD_LOADED__) return;
window.__HDS_DASHBOARD_LOADED__ = true;
// HDS — integração com a estrutura existente.
window.addEventListener("DOMContentLoaded", () => {
  const ids = ["dash1","dash2","dash3","dash4","dash5","dash6","dashboard_sidbar","dashboard_content_container","dashboardContentMain","toggleBtn","logoutBtn"];
  for (const id of ids) {
    if (!document.getElementById(id)) {
      console.warn("⚠️ Elemento ausente:", id);
    }
  }
});

// - Mantém todas as funcionalidades originais (NewsData.io, OWM, gráficos, semáforo, janelas, polling, logout).
// - Elimina erros de "Cannot read properties of null (addEventListener)" com helpers e checagens seguras.
// - Inicialização centralizada via DOMContentLoaded + initDashboard().
// - Compatível com dashboard.php atualizado e estrutura confirmada pelo Junior.

// -------------------------------------------
// BASE da instalação atual
// -------------------------------------------
window.HDS_BASE = '/hds_mvc_7.2/hds_mvc';

// -------------------------------------------
// Flags globais
// -------------------------------------------
window.HDS_APIKEY  = window.HDS_APIKEY ?? null; // OpenWeatherMap
window.HDS_FLAGURL = window.HDS_FLAGURL ?? "https://countryflagsapi.netlify.app/flag/";
window.HDS_DATASET = window.HDS_DATASET ?? "global"; // "global" (tabela clima) ou "minha" (clima_medianeira)
const apiCountryURL = window.HDS_FLAGURL;

// -------------------------------------------
/** Endpoints backend (controle.php) */
const endpoint = (action) => `${window.HDS_BASE}/control/controle.php?a=${action}`;

// -------------------------------------------
// Helpers seguros para DOM e eventos
// -------------------------------------------
const $  = (id) => document.getElementById(id);
const on = (el, ev, fn, opts) => { if (el) el.addEventListener(ev, fn, opts); };

// -------------------------------------------
// Efeito de destaque no semáforo
// -------------------------------------------
function flashSemaphore() {
  const sem = $("appSemaphore");
  if (!sem) return;
  sem.classList.add("flash-semaforo");
  setTimeout(() => sem.classList.remove("flash-semaforo"), 400);
}

// -------------------------------------------
// CSS para cards de notícias (grid responsiva)
// -------------------------------------------
function injectNewsStyles() {
  if ($("hds-news-css")) return;
  const css = `
    #newsContainer, .news-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 16px; padding: 8px; align-items: stretch;
    }
    .news-card {
      background: rgba(255,255,255,0.15);
      backdrop-filter: blur(6px);
      -webkit-backdrop-filter: blur(6px);
      border: 1px solid rgba(255,255,255,0.25);
      border-radius: 10px;
      overflow: hidden;
      box-shadow: 0 2px 8px rgba(0,0,0,0.08);
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .news-card:hover { transform: scale(1.02); box-shadow: 0 4px 14px rgba(0,0,0,0.15); }
    .news-card img { width: 100%; height: 120px; object-fit: cover; display: block; }
    .news-card h4 { font-size: 14px; color: #111; margin: 8px 10px 4px; font-weight: 600; line-height: 1.3; }
    .news-card p { font-size: 12px; color: #333; margin: 0 10px 8px; line-height: 1.35; min-height: 34px; }
    .news-card .news-meta { display: flex; justify-content: space-between; align-items: center; gap: 6px; margin: 0 10px 10px; font-size: 11.5px; color: #555; }
    .news-card .news-meta a { margin-left: auto; text-decoration: none; color: #0e7a0e; font-weight: 600; }
    .news-card .news-meta a:hover { text-decoration: underline; }
    .fadeIn { animation: fadeIn 0.5s ease-in-out; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }
  `;
  const style = document.createElement("style");
  style.id = "hds-news-css";
  style.textContent = css;
  document.head.appendChild(style);
}

// ====================================================================
// =================== NEWS: NewsData.io (cache 6h) ===================
// ====================================================================
async function fetchNoticiasInteligente() {
  const agora = Date.now();
  const ultima = localStorage.getItem("hds_news_time");
  const cache = localStorage.getItem("hds_news_cache");

  // 6 horas de cache
  if (cache && ultima && (agora - ultima < 21600000)) {
    try { return JSON.parse(cache); } catch { /* ignore */ }
  }

  const temas = [
    "clima agricultura",
    "agricultura sustentável",
    "tecnologia agrícola",
    "previsão meteorológica",
    "agrotecnologia",
    "produtividade rural"
  ];
  const termo = temas[Math.floor(Math.random() * temas.length)];
  const API_URL = `https://newsdata.io/api/1/news?apikey=pub_c3b3ecf88f8547898c77256eb2c420ba&language=pt&country=br&q=${encodeURIComponent(termo)}`;

  try {
    const res = await fetch(API_URL, { cache: "no-store" });
    const data = await res.json();

    if (data?.results?.length) {
      localStorage.setItem("hds_news_time", String(agora));
      localStorage.setItem("hds_news_cache", JSON.stringify(data));
      return data;
    } else if (cache) {
      return JSON.parse(cache);
    }
    return null;
  } catch (err) {
    if (cache) {
      try { return JSON.parse(cache); } catch { /* ignore */ }
    }
    return null;
  }
}

function escaparHTML(valor) {
  return String(valor ?? "").replace(/[&<>"']/g, c => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
}
function urlSegura(valor) {
  try { const url = new URL(valor); return ["http:", "https:"].includes(url.protocol) ? url.href : ""; }
  catch { return ""; }
}
async function carregarNoticias() {
  injectNewsStyles();
  const container = $("newsContainer");
  if (container) container.innerHTML = "<p style='text-align:center;color:#666;'>Carregando notícias...</p>";

  const data = await fetchNoticiasInteligente();
  if (!container) return;

  if (!data || !data.results?.length) {
    container.innerHTML = "<p style='text-align:center;color:#999;'>Nenhuma notícia disponível no momento.</p>";
    return;
  }

  container.innerHTML = `<div id="newsGrid" class="news-grid"></div>`;
  const grid = $("newsGrid");

  grid.innerHTML = data.results.slice(0, 6).map(a => {
    const date = a.pubDate
      ? new Date(a.pubDate).toLocaleDateString("pt-BR", { day: "2-digit", month: "short" })
      : "";
    const img = urlSegura(a.image_url);
    const desc = escaparHTML(a.description || "");
    const fonte = a.source_id ? `<span style='color:#0e7a0e;'>${escaparHTML(a.source_id)}</span>` : "";
    return `
      <div class="news-card fadeIn">
        ${img ? `<img src="${escaparHTML(img)}" alt="Imagem da notícia">` : `<div style="height:120px;display:grid;place-items:center;">Imagem indisponível</div>`}
        <h4>${escaparHTML(a.title)}</h4>
        <p>${desc}</p>
        <div class="news-meta">
          <span>${date}</span>
          ${fonte}
          <a href="${escaparHTML(urlSegura(a.link))}" target="_blank" rel="noopener">Ler mais →</a>
        </div>
      </div>
    `;
  }).join("");
  grid.querySelectorAll("img").forEach(img => {
    img.addEventListener("error", () => {
      const fallback = document.createElement("div");
      fallback.style.cssText = "height:120px;display:grid;place-items:center";
      fallback.textContent = "Imagem indisponível";
      img.replaceWith(fallback);
    }, { once: true });
  });
}

// ====================================================================
// =================== PÁGINA INICIAL (Notícias + API) =================
// ====================================================================
async function showDashboardContent() {
  abrirTela("home");
  const dashboardContentMain = $("dashboardContentMain");
  if (!dashboardContentMain) return;

  dashboardContentMain.innerHTML = `
    <div style="padding:12px;">
      <h3 style="text-align:center; color:#0e7a0e; margin: 0 0 6px;">🌎 Notícias sobre Clima e Agricultura</h3>
      <p style="text-align:center; color:#555; margin:0 0 10px;">Conteúdo dinâmico para manter você sempre atualizado.</p>

      <div style="margin:10px auto 0; max-width:520px; display:flex; gap:6px; justify-content:center;">
        <input type="text" id="station-input" placeholder="Digite 'padrao' ou 'api_medianeira'"
               style="flex:1; max-width:320px; padding:8px 10px; border:1px solid #ddd; border-radius:8px;" />
        <button id="station-search" class="btn-search"><i class="fa-solid fa-arrow-right"></i> Entrar</button>
      </div>

      <div id="newsContainer" style="margin-top:14px;">
        <p style="text-align:center; color:#666;">Carregando notícias...</p>
      </div>
    </div>
  `;

  const apiInput = $("station-input");
  const apiBtn   = $("station-search");

  on(apiBtn, "click", () => aplicarChave(apiInput?.value));
  on(apiInput, "keyup", (e) => { if (e.key === "Enter") aplicarChave(apiInput?.value); });

  await carregarNoticias();
}

// ====================================================================
// =================== DADOS GLOBAIS (OpenWeatherMap) =================
// ====================================================================

// Coleta única por página, independente da tela aberta.
const coleta = {
  cidadeGlobal: "",
  geracao: 0,
  pendente: null,
  timer: null,
  ultima: null,
  tela: "home",
  telaVersao: 0
};
let tempChart = null;
let chartRequest = 0;

function abrirTela(nome) {
  coleta.tela = nome;
  coleta.telaVersao += 1;
  chartRequest += 1;
  if (tempChart) {
    tempChart.destroy();
    tempChart = null;
  }
  document.querySelectorAll(".dashboard_menu_lists li").forEach(li => {
    li.classList.remove("menuActive");
  });
  const ids = { home: "dash1", global: "dash2", chart: "dash3", janelas: "dash4", previsoes: "dash5", minha: "dash6" };
  $(ids[nome])?.closest("li")?.classList.add("menuActive");
}

function interromperConsulta() {
  coleta.geracao += 1;
  coleta.pendente?.controller.abort();
  coleta.pendente = null;
}

async function fetchComPrazo(url, options = {}) {
  const controller = new AbortController();
  const externo = options.signal;
  const abortar = () => controller.abort();
  if (externo?.aborted) controller.abort();
  externo?.addEventListener("abort", abortar, { once: true });
  const timeout = setTimeout(abortar, 25000);
  try {
    const resposta = await fetch(url, { ...options, signal: controller.signal });
    const texto = await resposta.text();
    if (!resposta.ok) throw new Error(`HTTP ${resposta.status} em ${new URL(url, location.href).pathname}`);
    return { resposta, texto };
  } finally {
    clearTimeout(timeout);
    externo?.removeEventListener("abort", abortar);
  }
}

function avisoColeta(mensagem, erro = false) {
  let aviso = $("hdsColetaStatus");
  const pai = $("persistentHeader") || $("dashboard_content_container");
  if (!aviso && pai) {
    aviso = document.createElement("p");
    aviso.id = "hdsColetaStatus";
    aviso.style.cssText = "font-size:13px;margin:10px 16px;line-height:1.5";
    pai.appendChild(aviso);
  }
  if (aviso) {
    aviso.textContent = mensagem;
    aviso.style.color = erro ? "#b42318" : "inherit";
    aviso.setAttribute("role", erro ? "alert" : "status");
  }
}

function montarCartaoClima(comBusca) {
  return `
    <div id="weatherModal">
      <div class="form">
        <h3>${comBusca ? "Confira o clima de uma cidade:" : "Minha Estação — Medianeira-PR"}</h3>
        ${comBusca ? `<div class="form-input-container">
          <input type="text" id="city-input" placeholder="Digite a cidade, por exemplo: Paranaguá,BR" />
          <button id="search" type="button"><i class="fa-solid fa-magnifying-glass"></i> Buscar</button>
        </div>` : `<p>Dados via OpenWeatherMap para Medianeira,BR.</p>`}
      </div>
      <div id="weather-data" class="hide" style="margin-top:20px;">
        <h2><i class="fa-solid fa-location-dot"></i> <span id="city"></span>
          <img alt="Bandeira do país" id="country" style="width:32px;vertical-align:middle;" /></h2>
        <p id="temperature"><span></span>&deg;C</p>
        <div id="description-container"><p id="description"></p><img alt="Condições do tempo" id="weather-icon" /></div>
        <div id="details-container">
          <p id="umidity"><i class="fa-solid fa-droplet"></i> <span></span></p>
          <p id="wind"><i class="fa-solid fa-wind"></i> <span></span></p>
        </div>
        <p id="hdsWeatherTime" style="font-size:13px;"></p>
      </div>
    </div>`;
}

function renderizarUltimaLeitura() {
  const leitura = coleta.ultima;
  if (!leitura || leitura.modo !== window.HDS_DATASET) return;
  if (coleta.tela !== leitura.modo) return;
  const { data, temp, humid, windKmh, recebidoEm } = leitura;
  const texto = (selector, valor) => {
    const el = document.querySelector(selector);
    if (el) el.textContent = valor;
  };
  texto("#city", data.name || "");
  texto("#temperature span", temp.toFixed(1).replace(".", ","));
  texto("#description", data.weather?.[0]?.description || "");
  texto("#umidity span", `${humid}%`);
  texto("#wind span", `${windKmh.toFixed(1).replace(".", ",")} km/h`);
  const icon = $("weather-icon");
  if (icon && data.weather?.[0]?.icon) icon.src = `https://openweathermap.org/img/wn/${data.weather[0].icon}.png`;
  const flag = $("country");
  if (flag) {
    flag.hidden = false;
    flag.onerror = () => { flag.hidden = true; };
    flag.src = `${apiCountryURL}${(data.sys?.country || "BR").toUpperCase()}.png`;
  }
  const dataFonte = Number(data.dt);
  const horarioFonte = Number.isFinite(dataFonte) && dataFonte > 0
    ? new Date(dataFonte * 1000).toLocaleString("pt-BR") : "não informado";
  texto("#hdsWeatherTime", `Dado da fonte: ${horarioFonte} • Consulta: ${recebidoEm.toLocaleTimeString("pt-BR")} (horário deste computador)`);
  $("weather-data")?.classList.remove("hide");
}

async function coletarClima() {
  if (!window.HDS_APIKEY || coleta.pendente) return;
  const modo = window.HDS_DATASET;
  const cidade = modo === "minha" ? "Medianeira,BR" : coleta.cidadeGlobal;
  if (!cidade) return;
  const tarefa = { controller: new AbortController(), geracao: coleta.geracao };
  coleta.pendente = tarefa;
  const vigente = () => tarefa.geracao === coleta.geracao && !tarefa.controller.signal.aborted;
  let consultou = false;
  try {
    const url = `https://api.openweathermap.org/data/2.5/weather?q=${encodeURIComponent(cidade)}&units=metric&appid=${encodeURIComponent(window.HDS_APIKEY)}&lang=pt_br`;
    const { texto } = await fetchComPrazo(url, { cache: "no-store", signal: tarefa.controller.signal });
    if (!vigente()) return;
    const data = JSON.parse(texto);
    if (Number(data.cod) !== 200 || !data.main) throw new Error(data.message || "Resposta meteorológica inválida.");
    const temp = Number(data.main.temp);
    const humid = Number(data.main.humidity);
    const windKmh = Number(data.wind?.speed) * 3.6;
    if (![temp, humid, windKmh].every(Number.isFinite)) throw new Error("Temperatura, umidade ou vento ausentes na resposta.");
    coleta.ultima = { modo, cidade, data, temp, humid, windKmh, recebidoEm: new Date() };
    consultou = true;
    renderizarUltimaLeitura();
    const med = { temp, humidity: humid, wind: windKmh };
    for (const fn of [window.HDS_setMeasurements, window.HDS_updateCharts]) {
      if (typeof fn === "function") {
        try { fn(med); } catch (erro) { console.error("[HDS] Falha na exibição:", erro); }
      }
    }
    flashSemaphore();
    const rota = modo === "minha" ? "salvardados_medianeira" : "salvardados";
    const gravacao = await fetchComPrazo(endpoint(rota), {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      cache: "no-store",
      signal: tarefa.controller.signal,
      body: JSON.stringify({
        city: data.name || cidade, temp,
        desc: data.weather?.[0]?.description || "",
        humidity: `${humid}%`, wind: `${windKmh.toFixed(1)} km/h`, wind_unit: "km/h"
      })
    });
    if (!vigente()) return;
    if (gravacao.resposta.redirected || /^\s*</.test(gravacao.texto)) {
      throw new Error("O backend retornou HTML ou redirecionou. Confira a sessão e o PHP.");
    }
    // Aceita resposta vazia/textual legada e verifica erros explícitos em JSON.
    const corpo = gravacao.texto.trim();
    let resultado;
    if (/^[{\[]/.test(corpo) || /^(false|null)$/.test(corpo)) {
      resultado = JSON.parse(corpo);
    }
    if (resultado === false || resultado === null || resultado?.success === false || resultado?.ok === false || resultado?.error || resultado?.erro || ["error", "erro"].includes(resultado?.status) || /^(erro|error|falha)\b/i.test(corpo)) {
      throw new Error("O backend informou falha ao salvar. Consulte a resposta na aba Network.");
    }
    avisoColeta(`${data.name || cidade}: consulta às ${new Date().toLocaleTimeString("pt-BR")}. Envio ao banco concluído; nova consulta em aproximadamente 1 minuto.`);
    console.info("[HDS] Ciclo concluído:", { cidade: data.name || cidade, modo, horario: new Date().toISOString() });
    // A leitura do gráfico só começa DEPOIS da resposta da gravação.
    window.dispatchEvent(new Event("HDS:dataUpdated"));
  } catch (erro) {
    if (!vigente()) return;
    const etapa = consultou ? "gravar no banco" : "consultar o clima";
    avisoColeta(`Falha ao ${etapa}: ${erro.message}. A próxima tentativa será automática.`, true);
    console.error(`[HDS] Falha ao ${etapa}:`, erro);
  } finally {
    if (coleta.pendente === tarefa) coleta.pendente = null;
  }
}

function iniciarColeta() {
  if (coleta.timer !== null) clearInterval(coleta.timer);
  coleta.timer = setInterval(() => { void coletarClima(); }, 60000);
}

async function showWeatherModal() {
  if ($("dash2")?.classList.contains("disabled")) return;
  const main = $("dashboardContentMain");
  if (!main) return;
  abrirTela("global");
  main.innerHTML = montarCartaoClima(true);
  const input = $("city-input");
  input.value = coleta.cidadeGlobal;
  renderizarUltimaLeitura();
  const buscar = () => {
    const cidade = input.value.trim();
    if (!cidade) { alert("Por favor, digite uma cidade."); return; }
    interromperConsulta();
    coleta.cidadeGlobal = cidade;
    coleta.ultima = null;
    window.HDS_DATASET = "global";
    iniciarColeta();
    void coletarClima();
  };
  on($("search"), "click", buscar);
  on(input, "keydown", e => { if (e.key === "Enter") { e.preventDefault(); buscar(); } });
}

// ====================================================================
// =============== VER AGORA (Gráfico: Temperatura × Hora) ============
// ====================================================================
async function atualizarGrafico() {
  if (coleta.tela !== "chart" || !$("tempHourCanvas")) return;
  const versao = coleta.telaVersao;
  const modo = window.HDS_DATASET;
  const request = ++chartRequest;
  const canvas = $("tempHourCanvas");
  try {
    const rota = modo === "minha" ? "dadosTemperatura_medianeira" : "dadosTemperatura";
    const { texto, resposta } = await fetchComPrazo(endpoint(rota), { cache: "no-store" });
    if (resposta.redirected) throw new Error("Sessão expirada ou redirecionamento do backend.");
    const rows = JSON.parse(texto);
    if (!Array.isArray(rows)) throw new Error("O backend não retornou uma lista de leituras.");
    if (request !== chartRequest || versao !== coleta.telaVersao || modo !== window.HDS_DATASET || !canvas.isConnected) return;
    const leituras = rows.slice().reverse();
    const horas = leituras.map(r => r.horario);
    const temperaturas = leituras.map(r => {
      const valor = parseFloat(r.temperatura);
      return Number.isFinite(valor) ? valor : null;
    });
    if (typeof Chart !== "function") throw new Error("Chart.js não foi carregado pelo dashboard.php.");
    if (tempChart) {
      tempChart.data.labels = horas;
      tempChart.data.datasets[0].data = temperaturas;
      tempChart.update();
    } else {
      tempChart = new Chart(canvas.getContext("2d"), {
        type: "line",
        data: { labels: horas, datasets: [{ label: "Temperatura (°C)", data: temperaturas, borderWidth: 2, fill: false, borderColor: "rgb(75, 192, 192)", tension: 0.3 }] },
        options: { responsive: true, scales: {
          x: { title: { display: true, text: "Horário registrado no banco" } },
          y: { title: { display: true, text: "°C" }, beginAtZero: false }
        } }
      });
    }
    const status = $("hdsChartStatus");
    if (status) status.textContent = rows.length
      ? `Histórico atualizado às ${new Date().toLocaleTimeString("pt-BR")}. Fonte: ${modo === "minha" ? "clima_medianeira" : "clima"}.`
      : "Nenhuma leitura retornada pelo banco.";
  } catch (erro) {
    if (request !== chartRequest || versao !== coleta.telaVersao) return;
    console.error("[HDS] Falha no gráfico:", erro);
    const status = $("hdsChartStatus");
    if (status) status.textContent = `Não foi possível atualizar o gráfico: ${erro.message}`;
  }
}

function showTempContent() {
  if ($("dash3")?.classList.contains("disabled")) return;
  const main = $("dashboardContentMain");
  if (!main) return;
  abrirTela("chart");
  main.innerHTML = `<div class="single-chart-container" style="display:flex;flex-direction:column;align-items:center;gap:12px;">
    <h3>Temperatura × Hora (últimas leituras)</h3>
    <p id="hdsChartStatus">Carregando histórico...</p>
    <canvas id="tempHourCanvas" style="max-width:700px;width:100%;height:220px;"></canvas>
  </div>`;
  void atualizarGrafico();
}

// ====================================================================
// =========== MINHA ESTAÇÃO (Medianeira-PR) — OWM + BD ===============
// ====================================================================
async function showMinhaEstacao() {
  if ($("dash6")?.classList.contains("disabled")) return;
  const main = $("dashboardContentMain");
  if (!main) return;
  abrirTela("minha");
  main.innerHTML = montarCartaoClima(false);
  renderizarUltimaLeitura();
  if (!coleta.ultima) await coletarClima();
}

// ====================================================================
// =========== JANELAS DE APLICAÇÃO (BD) — seletor de horizonte =======
// ====================================================================
async function showJanelasAplicacao() {
  const dash4 = $("dash4");
  if (dash4?.classList?.contains("disabled")) return;

  const dashboardContentMain = $("dashboardContentMain");
  if (!dashboardContentMain) return;

  dashboardContentMain.innerHTML = `
    <div style="padding:16px;">
      <h3>Janelas de Aplicação — Medianeira-PR</h3>
      <p style="color:#555;margin-top:4px;">
        Baseado em dados do banco (sem previsão). Critérios definidos em <code>config/criterios.json</code>.
      </p>

      <div style="margin-top:12px; display:flex; align-items:center; gap:10px;">
        <label for="horizonte" style="font-weight:600;">Horizonte (passado):</label>
        <select id="horizonte" style="padding:6px 10px; border-radius:6px; border:1px solid #ccc;">
          <option value="6">6h</option>
          <option value="12">12h</option>
          <option value="24" selected>24h</option>
          <option value="48">48h</option>
        </select>
        <button id="btnAtualizarJanelas" class="btn-search">
          <i class="fa fa-rotate-right"></i> Atualizar
        </button>
      </div>

      <div id="listaJanelas" style="margin-top:16px;"></div>
    </div>`;

  abrirTela("janelas");
  const lista = $("listaJanelas");
  const selectHorizonte = $("horizonte");
  const btnAtualizar = $("btnAtualizarJanelas");

  async function carregarJanelas() {
    const h = (selectHorizonte?.value || 24);
    if (lista) lista.innerHTML = `<p style="color:#555;">Carregando janelas (${h}h)...</p>`;

    try {
      const res = await fetch(`${endpoint('janelas_aplicacao')}&h=${h}&_=${Date.now()}`, { cache: "no-store" });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const json = await res.json();
      const arr = json?.janelas_verdes || [];

      if (!arr.length) {
        if (lista) {
          lista.innerHTML = `<div class="alert alert-warning" style="border:1px solid #f3d08b; background:#fff8e6; padding:10px; border-radius:8px;">
            Nenhuma janela verde encontrada nas últimas ${h}h.
          </div>`;
        }
        return;
      }

      let html = '';
      arr.forEach((j, i) => {
        const ini = new Date(j.inicio);
        const fim = new Date(j.fim);
        const fmt = (d) => d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        const conf = j.confianca_media != null ? Number(j.confianca_media).toFixed(2) : '—';
        html += `
          <div class="janela-card" style="border:1px solid #e3e3e3; border-radius:12px; padding:12px; margin-bottom:10px;">
            <div style="display:flex;justify-content:space-between;align-items:center;">
              <strong>Janela ${i + 1}</strong>
              <span style="background:#16a34a;color:#fff;border-radius:12px;padding:4px 8px;">
                Confiança ${conf}
              </span>
            </div>
            <div style="margin-top:6px;">
              ⏱️ <b>${fmt(ini)} – ${fmt(fim)}</b> • Duração: <b>${j.duracao_min} min</b>
            </div>
          </div>`;
      });
      if (lista) lista.innerHTML = html;
    } catch (err) {
      console.error(err);
      if (lista) {
        lista.innerHTML = `<div class="alert alert-danger" style="border:1px solid #e39b9b; background:#fff1f1; padding:10px; border-radius:8px;">
          Erro ao carregar janelas (${h}h).
        </div>`;
      }
    }
  }

  on(btnAtualizar, "click", carregarJanelas);
  on(selectHorizonte, "change", carregarJanelas);
  await carregarJanelas();
}

// ====================================================================
// =================== Aplicar chave / habilitar menus =================
// ====================================================================
function aplicarChave(valor) {
  const v = (valor || "").trim().toLowerCase();
  const dash2 = $("dash2"), dash3 = $("dash3"), dash4 = $("dash4"), dash5 = $("dash5"), dash6 = $("dash6");
  if (v !== "padrao" && v !== "api_medianeira") {
    alert("Chave inválida. Digite 'padrao' ou 'api_medianeira'.");
    return;
  }
  [dash2, dash3, dash4, dash5, dash6].forEach(el => el?.classList?.add("disabled"));
  interromperConsulta();
  coleta.ultima = null;
  if (v === "padrao") {
    window.HDS_APIKEY  = "04b79ece1895eb43302ec282f6d5955b";
    window.HDS_DATASET = "global";
    alert("✅ API padrão aplicada. Liberado: Dados Globais e Ver Agora.");
    dash2?.classList?.remove("disabled");
    dash3?.classList?.remove("disabled");
    iniciarColeta();
    avisoColeta(coleta.cidadeGlobal ? `Coleta global: ${coleta.cidadeGlobal}.` : "Abra Dados Globais e busque a cidade para iniciar a coleta por minuto.");
    void coletarClima();
    return;
  }
  if (v === "api_medianeira") {
    window.HDS_APIKEY  = "04b79ece1895eb43302ec282f6d5955b";
    window.HDS_DATASET = "minha";
    alert("✅ API Medianeira aplicada. Liberado: Minha Estação, Ver Agora, Janelas e Previsões.");
    dash3?.classList?.remove("disabled");
    dash4?.classList?.remove("disabled");
    dash5?.classList?.remove("disabled");
    dash6?.classList?.remove("disabled");
    iniciarColeta();
    void coletarClima();
    return;
  }
  alert("❌ Chave inválida. Digite 'padrao' ou 'api_medianeira'.");
}

// ====================================================================
// ====================================================================
// =================== Inicialização do Dashboard =====================
// ====================================================================
function initDashboard() {
  // Referências principais
  const toggleBtn                 = $("toggleBtn");
  const dashboardSidebar          = $("dashboard_sidbar");
  const dashboardContentContainer = $("dashboard_content_container");

  // Navbar / tema (se existirem no DOM atual)
  const navToggle = $("navToggle");
  const navMenu   = $("navMenu");
  const themeToggleBtn = $("themeToggle");

  // Menu responsivo superior
  on(navToggle, "click", (e) => {
    e.preventDefault();
    e.stopImmediatePropagation();
    navMenu?.classList?.toggle("active");
    navToggle?.classList?.toggle("open");
  }, { capture: true });

  // Dark/Light mode (via botão no topo)
  on(themeToggleBtn, "click", (e) => {
    e.preventDefault();
    e.stopImmediatePropagation();
    const html = document.documentElement;
    const key  = "hds-theme";
    const cur  = html.getAttribute("data-theme") === "dark" ? "light" : "dark";
    html.setAttribute("data-theme", cur);
    document.body.setAttribute("data-theme", cur);
    localStorage.setItem(key, cur);
  }, { capture: true });

  // Sidebar (desktop colapsável / mobile drawer)
  on(toggleBtn, "click", (e) => {
    e.preventDefault();
    e.stopImmediatePropagation();
    if (!dashboardSidebar || !dashboardContentContainer) return;

    // Em telas largas: colapsa
    if (window.innerWidth >= 1024) {
      const collapsed = dashboardSidebar.classList.toggle("collapsed");
      dashboardContentContainer.classList.toggle("expanded", collapsed);
    } else {
      // Em telas pequenas: abre/fecha como drawer
      dashboardSidebar.classList.toggle("open");
    }
  }, { capture: true });

  // Eventos de menu (seguros)
  const dash1 = $("dash1");
  const dash2 = $("dash2");
  const dash3 = $("dash3");
  const dash4 = $("dash4");
  const dash5 = $("dash5");
  const dash6 = $("dash6");

  on(dash1, "click", (e) => { e.preventDefault(); showDashboardContent(); });
  on(dash2, "click", async (e) => {
    if (!dash2?.classList?.contains("disabled")) {
      e.preventDefault();
      await showWeatherModal();
    }
  });
  on(dash3, "click", (e) => {
    if (!dash3?.classList?.contains("disabled")) {
      e.preventDefault();
      showTempContent();
    }
  });
  on(dash4, "click", async (e) => {
    if (!dash4?.classList?.contains("disabled")) {
      e.preventDefault();
      await showJanelasAplicacao();
    }
  });
  on(dash5, "click", (e) => {
    if (dash5?.classList?.contains("disabled")) return;
    e.preventDefault();
    abrirTela("previsoes");
    const main = $("dashboardContentMain");
    if (main) main.innerHTML = `
      <div style="padding:20px; text-align:center;">
        <i class="fa-solid fa-chart-line" style="font-size:48px; color:#2c3e50;"></i>
        <h3>Previsões</h3>
        <p>📊 Esta função está em desenvolvimento.</p>
      </div>`;
  });
  on(dash6, "click", async (e) => {
    if (!dash6?.classList?.contains("disabled")) {
      e.preventDefault();
      await showMinhaEstacao();
    }
  });

  // Logout seguro
  const logoutBtn = $("logoutBtn");
  on(logoutBtn, "click", (e) => {
    e.preventDefault();
    const confirmar = confirm("Você deseja mesmo encerrar a sua sessão?");
    if (confirmar) {
      window.location.href = `${window.HDS_BASE}/control/controle.php?a=logout`;
    }

  });



  window.addEventListener("HDS:dataUpdated", () => { void atualizarGrafico(); });
  iniciarColeta();
  window.addEventListener("pagehide", () => {
    clearInterval(coleta.timer);
    coleta.timer = null;
    interromperConsulta();
  });
  window.addEventListener("pageshow", () => {
    if (coleta.timer === null) iniciarColeta();
  });
  // Render inicial
  showDashboardContent();
}

// ====================================================================
// ======================= Bootstrap do script ========================
// ====================================================================
function iniciarHDS() {
  // Restaura tema escolhido anteriormente
  const savedTheme = localStorage.getItem("hds-theme");
  if (savedTheme) {
    document.documentElement.setAttribute("data-theme", savedTheme);
    document.body.setAttribute("data-theme", savedTheme);
  }
  initDashboard();
 }
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", iniciarHDS, { once: true });
} else {
  iniciarHDS();
}

})();
