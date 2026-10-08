<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

/* ===== Descobre BASE_URI ===== */
if (!defined('BASE_URI')) {
  $scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/');
  $level1 = rtrim(str_replace('\\', '/', dirname($scriptPath)), '/');
  $level2 = rtrim(str_replace('\\', '/', dirname($level1)), '/');
  $base = $level2 === '' || $level2 === '.' ? '/' : rtrim(dirname($level2), '/');
  if ($base === '' || $base === '.') $base = '/';
  define('BASE_URI', $base);
}

/* helper h() */
if (!function_exists('h')) {
  function h(?string $s): string { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
}

/* Redireciona se já estiver logado */
if (!empty($_SESSION['user_id'])) {
  header('Location: ' . BASE_URI . '/view/interface/dashboard.php');
  exit;
}

/* Erro opcional (?err=) */
$erro = isset($_GET['err']) ? (string)$_GET['err'] : '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>HDS - Login</title>

  <!-- CSS principal -->
  <link rel="stylesheet" href="<?= h(BASE_URI) ?>/config/css/login.css" />

  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
</head>

<body id="loginbody">
  <div class="loginheader">
    <h1>HDS</h1>
    <p>Hour to Defense System</p>
  </div>

  <div class="login-card">
    <?php if ($erro): ?>
      <p class="login-error"><?= h($erro) ?></p>
    <?php endif; ?>

    <form action="<?= h(BASE_URI) ?>/control/controle.php?a=login" method="POST" autocomplete="off" novalidate>
      <div class="loginInputContainer">
        <label for="email">E-mail</label>
        <input id="email" name="email" type="email" placeholder="Seu e-mail" required autocomplete="email" />
      </div>

      <div class="loginInputContainer">
        <label for="password">Senha</label>
        <input id="password" name="password" type="password" placeholder="Sua senha" required autocomplete="current-password" />
      </div>

      <div class="loginButtonContainer">
        <button type="submit"><i class="fa fa-sign-in-alt"></i> Entrar</button>
      </div>
    </form>

    <p class="login-back">
      <a href="<?= h(BASE_URI) ?>/index.php"><i class="fa fa-home"></i> Voltar à Home</a>
    </p>
  </div>
</body>
</html>
