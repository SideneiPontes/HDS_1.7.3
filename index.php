<?php
declare(strict_types=1);

// ===== Descobre o prefixo do projeto na URL (ex.: "/hds") =====
$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
define('BASE_URI', $scriptDir === '' ? '/' : $scriptDir);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>HDS - Sistema de Planejamento Climático</title>

  <!-- CSS principal -->
  <link rel="stylesheet" type="text/css" href="<?= BASE_URI ?>/config/css/login.css" />

  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />

  <link rel="icon" href="<?= BASE_URI ?>/config/img/favicon.ico" type="image/x-icon" />
</head>

<body id="bodyHome">
  <!-- ========================= -->
  <!-- CABEÇALHO FIXO INSTITUCIONAL -->
  <!-- ========================= -->
  <header class="navbar">
    <div class="nav-container">
      <div class="nav-logo">
        <i class="fa-solid fa-seedling"></i>
        <span>HDS</span>
      </div>
      <nav class="nav-menu" id="navMenu">
        <a href="<?= BASE_URI ?>/index.php" class="active">Início</a>
        <a href="#sobre">Sobre</a>
        <a href="#contato">Contato</a>
        <a href="<?= BASE_URI ?>/control/controle.php?a=login" class="login-btn">Login</a>
      </nav>
      <div class="nav-toggle" id="navToggle">
        <i class="fa-solid fa-bars"></i>
      </div>
    </div>
  </header>

  <!-- ========================= -->
  <!-- BANNER PRINCIPAL -->
  <!-- ========================= -->
  <section class="banner">
    <div class="homepageContainer">
      <div class="bannerHeader">
        <h1>HDS</h1>
        <p>Sistema de Planejamento Climático para o Agricultor</p>
      </div>

      <p class="bannerTagline">
        Decida o melhor momento para aplicar defensivos agrícolas com base em informações reais do clima.
      </p>

      <div class="vantagens">
        <ul>
          <li>✅ Previsão do tempo direto da sua propriedade;</li>
          <li>✅ Menos erros nas decisões de campo;</li>
          <li>✅ Gráficos e dados em tempo real;</li>
          <li>✅ Histórico das janelas climáticas;</li>
          <li>✅ Agenda de aplicação para auxiliar o produtor.</li>
        </ul>
      </div>

      <div class="bannerButtons">
        <a href="https://github.com/SideneiPontes" target="_blank">
          <i class="fab fa-github"></i><span>Ver Código</span>
        </a>
        <a href="https://drive.google.com/file/d/1kjC2TbKkXRGHS3KtU0e_f_4H6XSRlnIo/view?usp=sharing" target="_blank">
          <i class="fa fa-bookmark"></i><span>Sobre o Projeto</span>
        </a>
        <a href="<?= BASE_URI ?>/view/interface/dashboard.php">
          <i class="fa fa-bolt"></i><span>Acessar o Dashboard</span>
        </a>
      </div>
    </div>
  </section>

  <!-- ========================= -->
  <!-- SEÇÃO SOBRE -->
  <!-- ========================= -->
  <section id="sobre" class="section-info">
    <h2>Sobre o Sistema</h2>
    <p>O HDS é um sistema web desenvolvido para auxiliar o produtor rural na tomada de decisão sobre o uso de defensivos agrícolas, utilizando informações meteorológicas em tempo real e análises inteligentes baseadas em dados locais.</p>
  </section>

  <!-- ========================= -->
  <!-- SEÇÃO CONTATO -->
  <!-- ========================= -->
  <section id="contato" class="section-info">
    <h2>Contato</h2>
    <p>Entre em contato pelo e-mail <strong>sideneimendes@alunos.utfpr.edu.br</strong> ou através das nossas redes sociais.</p>
  </section>

  <!-- ========================= -->
  <!-- RODAPÉ -->
  <!-- ========================= -->
  <footer class="footer">
    <div class="footer-container">
      <div class="footer-column">
        <h3>Desenvolvido por</h3>
        <p><strong>Sidenei Mendes Pontes Junior</strong></p>
        <p>Mestrado em Agricultura de Precisão – UTFPR</p>
      </div>

      <div class="footer-column">
        <h3>Localização</h3>
        <p>Medianeira – PR, Brasil</p>
      </div>

      <div class="footer-column">
        <h3>Redes Sociais</h3>
        <div class="social-icons">
          <a href="https://www.linkedin.com" target="_blank"><i class="fab fa-linkedin"></i></a>
          <a href="https://github.com/SideneiPontes" target="_blank"><i class="fab fa-github"></i></a>
          <a href="https://instagram.com" target="_blank"><i class="fab fa-instagram"></i></a>
        </div>
      </div>
    </div>

    <div class="footer-bottom">
      <p>© 2025 – Sistema DSS Web | Todos os direitos reservados.</p>
    </div>
  </footer>

  <!-- ========================= -->
  <!-- SCRIPTS -->
  <!-- ========================= -->
  <script>
    // Animação do footer
    document.addEventListener("DOMContentLoaded", () => {
      const footer = document.querySelector(".footer");
      setTimeout(() => footer.style.opacity = "1", 300);
    });

    // Menu responsivo
    const toggle = document.getElementById("navToggle");
    const menu = document.getElementById("navMenu");
    toggle.addEventListener("click", () => {
      menu.classList.toggle("active");
      toggle.classList.toggle("open");
    });
  </script>
</body>
</html>
