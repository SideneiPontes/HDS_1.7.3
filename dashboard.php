<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: /hds_mvc_7.2/hds_mvc/control/controle.php?a=login');
    exit;
}
$USER_NAME = htmlspecialchars($_SESSION['user_name'] ?? 'Usuário', ENT_QUOTES, 'UTF-8');

$projectRoot = '/hds_mvc_7.2/hds_mvc';
define('BASE_URI', $projectRoot);
?>
<!DOCTYPE html>
<html lang="pt-br" data-theme="light">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Dashboard - HDS</title>

  <!-- === CSS PRINCIPAIS === -->
  <link rel="stylesheet" href="<?= BASE_URI ?>/config/css/dashboard.css" />
  <link rel="stylesheet" href="<?= BASE_URI ?>/config/css/weatherModal.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

</head>

<body id="bodyDashboard">

  <!-- =================== NAV SUPERIOR (MESMO PADRÃO INDEX) =================== -->
  <header class="navbar">
    <div class="nav-container">
      <div class="nav-logo">
        <i class="fa-solid fa-seedling"></i>
        <span>HDS</span>
      </div>

      <nav class="nav-menu" id="navMenu">
        <a href="<?= BASE_URI ?>/index.php">Início</a>
        <a href="<?= BASE_URI ?>/index.php#sobre">Sobre</a>
        <a href="<?= BASE_URI ?>/index.php#contato">Contato</a>
        <span class="nav-user"><i class="fa-regular fa-circle-user"></i> <?= $USER_NAME ?></span>
        <button id="themeToggle" class="ghost-btn" type="button" title="Alternar tema">
          <i class="fa-regular fa-moon"></i>
        </button>
        <a id="logoutBtn" class="login-btn" href="<?= BASE_URI ?>/control/controle.php?a=logout">
          <i class="fa fa-power-off"></i> Sair
        </a>
      </nav>

      <div class="nav-toggle" id="navToggle">
        <i class="fa-solid fa-bars"></i>
      </div>
    </div>
  </header>

  <!-- =================== CONTAINER PRINCIPAL =================== -->
  <div id="dashboardMainContainer">
    <!-- ===== SIDEBAR ===== -->
    <aside class="dashboard_sidbar" id="dashboard_sidbar">
      <h3 class="dashboard_logo">HDS</h3>
      <div class="dashboard_sidbar_user">
        <i class="fa fa-user-circle" style="font-size:64px;" aria-hidden="true"></i>
        <span><?= $USER_NAME ?></span>
      </div>

      <ul class="dashboard_menu_lists">
        <li class="menuActive"><a id="dash1" href="#"><i class="fa-solid fa-house-chimney"></i> Página Inicial</a></li>
        <li><a id="dash2" href="#" class="disabled"><i class="fa-solid fa-magnifying-glass-chart"></i> Dados Globais</a></li>
        <li><a id="dash6" href="#" class="disabled"><i class="fa-solid fa-tower-broadcast"></i> Minha Estação</a></li>
        <li><a id="dash3" href="#" class="disabled"><i class="fa-solid fa-chart-line"></i> Ver Agora</a></li>
        <li><a id="dash4" href="#" class="disabled"><i class="fa-solid fa-cloud-sun"></i> Janelas de Aplicação</a></li>
        <li><a id="dash5" href="#" class="disabled"><i class="fa-solid fa-earth-americas"></i> Previsões</a></li>
      </ul>
    </aside>

    <!-- ===== ÁREA DE CONTEÚDO ===== -->
    <main class="dashboard_content_container" id="dashboard_content_container">
      <div class="dashboard_topNav">
        <button id="toggleBtn" class="ghost-btn" aria-label="Alternar menu lateral">
          <i class="fa fa-navicon"></i>
        </button>
      </div>

      <!-- ===== SEMÁFORO ===== -->
      <section id="persistentHeader">
        <div class="semaphore-card" id="appSemaphore">
          <div class="semaphore-header">
            <i class="fa fa-traffic-light"></i>
            <h4>Semáforo de Aplicação</h4>
          </div>
          <div class="semaphore-body">
            <div class="semaphore-pole">
              <div class="lamp red" id="lampRed"></div>
              <div class="lamp yellow" id="lampYellow"></div>
              <div class="lamp green" id="lampGreen"></div>
            </div>
            <div class="semaphore-status">
              <div id="statusBadge" class="status-badge">Aguardando dados...</div>
              <div class="metrics-line" id="metricsLine">
                <span><strong>T:</strong> — °C</span> ·
                <span><strong>UR:</strong> — %</span> ·
                <span><strong>Vento:</strong> — km/h</span>
              </div>
              <small>Envie dados para avaliar as condições de aplicação.</small>
            </div>
          </div>
        </div>
      </section>

      <!-- ===== CONTEÚDO PRINCIPAL ===== -->
      <section id="dashboardContentMain" class="content-wrap">
        <article class="card info">
          <p><strong>Bem-vindo!</strong> Clique em <em>Minha Estação</em> para buscar dados climáticos e alimentar os gráficos e o semáforo.</p>
        </article>

        <div id="chartsContainer" class="charts-grid">
          <div class="chart-card"><h4>Temperatura</h4><canvas id="chartTemp"></canvas></div>
          <div class="chart-card"><h4>Umidade</h4><canvas id="chartUmi"></canvas></div>
          <div class="chart-card"><h4>Pressão</h4><canvas id="chartPress"></canvas></div>
          <div class="chart-card"><h4>Vento</h4><canvas id="chartWind"></canvas></div>
        </div>
      </section>
    </main>
  </div>

  <!-- =================== JS AUXILIARES =================== -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="<?= BASE_URI ?>/config/js/semaforo.js"></script>
  <script src="<?= BASE_URI ?>/config/js/charts.js"></script>
  <script src="<?= BASE_URI ?>/config/js/dashboard.js"></script>

  <script>
    // Menu responsivo
    const toggle = document.getElementById("navToggle");
    const menu = document.getElementById("navMenu");
    if (toggle && menu) {
      toggle.addEventListener("click", () => {
        menu.classList.toggle("active");
        toggle.classList.toggle("open");
      });
    }

    // Dark / Light Mode
    const html = document.documentElement;
    const btn = document.getElementById("themeToggle");
    const key = "hds-theme";
    const saved = localStorage.getItem(key);
    if (saved) html.setAttribute("data-theme", saved);
    if (btn) {
      btn.addEventListener("click", () => {
        const cur = html.getAttribute("data-theme") === "dark" ? "light" : "dark";
        html.setAttribute("data-theme", cur);
        localStorage.setItem(key, cur);
      });
    }

    // Sidebar retrátil
    const sideBtn = document.getElementById("toggleBtn");
    const sidebar = document.getElementById("dashboard_sidbar");
    if (sideBtn && sidebar) {
      sideBtn.addEventListener("click", () => {
        sidebar.classList.toggle("open");
      });
    }
  </script>
</body>
</html>
