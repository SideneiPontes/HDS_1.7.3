<?php
declare(strict_types=1);

/**
 * controle.php — Versão segura (corrigida)
 * Autor: Mestre (ajuste solicitado pelo Junior)
 *
 * Melhores práticas de segurança aplicadas:
 * - Sessão com cookie seguro (Secure, HttpOnly, SameSite)
 * - Cabeçalhos de segurança (CSP, X-Frame-Options, X-Content-Type-Options)
 * - Forçar HTTPS (exceto em localhost)
 * - Proteção básica contra brute-force via session
 * - Uso de password_verify() e rehash com password_needs_rehash()
 * - session_regenerate_id() após login
 *
 * OBS: Mantenha cópia do arquivo original antes de substituir.
 */

/* ========= Configurações iniciais de segurança ========= */
// Ajuste dos parâmetros do cookie de sessão antes do session_start()
$cookieParams = session_get_cookie_params();
$secureFlag   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? '') === '443';
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => $cookieParams['path'] ?? '/',
    'domain'   => $cookieParams['domain'] ?? '',
    'secure'   => $secureFlag,      // true em HTTPS
    'httponly' => true,
    'samesite' => 'Lax'
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* Forçar HTTPS quando não estiver em localhost (recomendado em produção) */
$host = $_SERVER['HTTP_HOST'] ?? '';
$isLocalhost = (strpos($host, 'localhost') !== false) || (strpos($host, '127.0.0.1') !== false);
if (!$isLocalhost && empty($_SERVER['HTTPS'])) {
    $uri = 'https://' . $_SERVER['HTTP_HOST'] . ($_SERVER['REQUEST_URI'] ?? '/');
    header('Location: ' . $uri, true, 301);
    exit;
}

/* Cabeçalhos de segurança básicos */
header("X-Frame-Options: SAMEORIGIN");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: no-referrer-when-downgrade");
header("Permissions-Policy: geolocation=(), microphone=()");
header("Content-Security-Policy: default-src 'self'; img-src 'self' https://images.pexels.com data:; script-src 'self' 'unsafe-inline' https:; style-src 'self' 'unsafe-inline' https:;");



/* ========= BASE_DIR / BASE_URI ========= */
if (!defined('BASE_DIR')) {
    define('BASE_DIR', dirname(__DIR__)); // .../hds_mvc
}
if (!defined('BASE_URI')) {
    // Ex.: /hds_mvc/control/controle.php -> BASE_URI = /hds_mvc
    $scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/');
    $level1     = rtrim(str_replace('\\', '/', dirname($scriptPath)), '/'); // /hds_mvc/control
    $base       = rtrim(str_replace('\\', '/', dirname($level1)), '/');     // /hds_mvc
    if ($base === '' || $base === '.') $base = '/';
    define('BASE_URI', $base);
}

/* ========= DEV errors (opcional) ========= */
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

/* ========= Timezone global ========= */
if (function_exists('date_default_timezone_set')) {
    @date_default_timezone_set('America/Sao_Paulo');
}

/* ========= Autoload simples ========= */
spl_autoload_register(function (string $class): void {
    $paths = [
        BASE_DIR . '/model/' . $class . '.php',
        BASE_DIR . '/model/Repos/' . $class . '.php',
        BASE_DIR . '/model/Services/' . $class . '.php',
    ];
    foreach ($paths as $p) {
        if (is_file($p)) { require_once $p; return; }
    }
});

/* ========= Helpers ========= */
function is_post(): bool { return (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'); }
function h(?string $s): string { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

function json_headers(): void {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');
}

function respond_json($payload, int $status = 200): void {
    http_response_code($status);
    json_headers();
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** HTTP GET JSON resiliente (cURL → stream), com timeout e user-agent */
function http_get_json(string $url, int $timeout = 10) {
    // 1) cURL, se disponível
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json, */*;q=0.8',
                'User-Agent: HDS/1.0 (+http://localhost)'
            ],
        ]);
        $raw  = curl_exec($ch);
        $err  = curl_error($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($raw !== false && $raw !== '') {
            $data = json_decode($raw, true);
            if (is_array($data)) return $data;
        }
        return ['__error__' => 'curl', 'http' => $http, 'message' => $err ?: 'empty'];
    }

    // 2) Fallback stream
    $ctx = stream_context_create([
        'http' => [
            'timeout' => $timeout,
            'ignore_errors' => true,
            'header' => "Accept: application/json\r\nUser-Agent: HDS/1.0\r\n",
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false || $raw === '') return ['__error__' => 'stream', 'message' => 'empty'];
    $data = json_decode($raw, true);
    return $data ?: ['__error__' => 'stream', 'message' => 'json_decode_failed'];
}

/* ========= Brute-force basic (session) ========= */
const LOGIN_MAX_ATTEMPTS = 5;
const LOGIN_LOCKOUT_SECONDS = 300; // 5 minutos

if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
}
if (!isset($_SESSION['login_lock_until'])) {
    $_SESSION['login_lock_until'] = 0;
}

/* ========= SWITCH ========= */
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $acao = $_GET['a'] ?? $_POST['a'] ?? 'login';
    switch ($acao) {
        case 'login':                       ac_login();            break;
        case 'logout':                      ac_logout();           break;
        case 'salvardados':                 ac_salvardados();      break;
        case 'dadosTemperatura':            ac_dadosTemperatura(); break;
        case 'salvardados_medianeira':      ac_salvardados_medianeira(); break;
        case 'dadosTemperatura_medianeira': ac_dadosTemperatura_medianeira(); break;
        case 'janelas_aplicacao':           ac_janelas_aplicacao(); break;
        case 'criterios':                   ac_criterios(); break;
        default:
            header('Location: ?a=login');
            exit;
    }
}

/* ========= AÇÕES ========= */

/** LOGIN */
function ac_login(): void
{
    $erro = '';

    // lockout check
    if (time() < (int)($_SESSION['login_lock_until'] ?? 0)) {
        $remaining = (int)($_SESSION['login_lock_until'] - time());
        $erro = 'Muitas tentativas. Tente novamente em ' . $remaining . ' segundos.';
        $qs = http_build_query(['err' => $erro]);
        header('Location: ' . BASE_URI . '/view/interface/login.php?' . $qs);
        exit;
    }

    if (is_post()) {
        $email    = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        // validações básicas
        if ($email === '' || $password === '') {
            $erro = 'Preencha todos os campos.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erro = 'E-mail inválido.';
        } else {
            try {
                if (class_exists('UserService')) {
                    // Se houver UserService, delega (espera-se que service trate hashing corretamente)
                    $svc  = new UserService();
                    $user = $svc->login($email, $password);
                    if ($user) {
                        // sucesso no service
                        $_SESSION['user_id']   = $user['id'] ?? null;
                        $_SESSION['user_name'] = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
                        session_regenerate_id(true);
                        // reset attempts
                        $_SESSION['login_attempts'] = 0;
                        $_SESSION['login_lock_until'] = 0;
                        header('Location: ' . BASE_URI . '/view/interface/dashboard.php');
                        exit;
                    }
                    $erro = 'E-mail ou senha inválidos.';
                } else {
                    $pdo  = db();
                    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
                    $stmt->execute([$email]);
                    $user = $stmt->fetch();

                    if ($user && !empty($user['password']) && password_verify($password, (string)$user['password'])) {
                        // login ok
                        $_SESSION['user_id']   = $user['id'] ?? null;
                        $_SESSION['user_name'] = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
                        session_regenerate_id(true);

                        // reset attempts
                        $_SESSION['login_attempts'] = 0;
                        $_SESSION['login_lock_until'] = 0;

                        // rehash if needed (muda o hash no banco se o algoritmo/cost for desatualizado)
                        $currentHash = (string)$user['password'];
                        $algoOptions = []; // usa options padrão; você pode adicionar ['cost'=>12] se quiser
                        if (password_needs_rehash($currentHash, PASSWORD_DEFAULT, $algoOptions)) {
                            $newHash = password_hash($password, PASSWORD_DEFAULT, $algoOptions);
                            try {
                                $up = $pdo->prepare('UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?');
                                $up->execute([$newHash, $user['id']]);
                            } catch (Throwable $e) {
                                // não falhar o login por conta do rehash; logue internamente se desejar
                            }
                        }

                        header('Location: ' . BASE_URI . '/view/interface/dashboard.php');
                        exit;
                    }

                    // falha: incrementar contador e possivelmente bloquear
                    $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
                    if (($_SESSION['login_attempts'] ?? 0) >= LOGIN_MAX_ATTEMPTS) {
                        $_SESSION['login_lock_until'] = time() + LOGIN_LOCKOUT_SECONDS;
                    }

                    $erro = 'E-mail ou senha inválidos.';
                }
            } catch (Throwable $e) {
                // Não vaze detalhes técnicos para o usuário
                $erro = 'Falha ao autenticar. Verifique a conexão.';
            }
        }

        $qs = http_build_query(['err' => $erro]);
        header('Location: ' . BASE_URI . '/view/interface/login.php?' . $qs);
        exit;
    }

    require BASE_DIR . '/view/interface/login.php';
}

/** LOGOUT */
function ac_logout(): void
{
    // limpa sessão e cookie com flags corretas
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], true);
    }
    session_unset();
    session_destroy();

    header('Location: ' . BASE_URI . '/index.php');
    exit;
}

/** SALVAR DADOS (JSON -> INSERT em "clima") — OWM */
function ac_salvardados(): void
{
    try {
        $raw = file_get_contents('php://input');
        if ($raw === false || $raw === '') {
            respond_json(['status' => 'error', 'message' => 'Corpo da requisição vazio.'], 400);
        }
        $payload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        // Se houver um service, delega e retorna
        if (class_exists('ClimaService')) {
            $svc  = new ClimaService();
            $id   = $svc->salvarAmostra($payload);
            respond_json(['status' => 'success', 'id' => $id]);
        }

        $pdo = db();

        $cidade = trim((string)($payload['city'] ?? ''));
        $desc   = trim((string)($payload['desc'] ?? ''));

        // Preferir campos numéricos; cair pros legíveis antigos se necessário
        $toFloat = function ($v, $strip = null) {
            if ($v === null || $v === '') return null;
            if (is_numeric($v)) return (float)$v;
            $s = (string)$v;
            if ($strip) $s = str_ireplace($strip, '', $s);
            $s = str_replace(',', '.', $s);
            $s = preg_replace('/[^0-9.\-]/', '', $s);
            return is_numeric($s) ? (float)$s : null;
        };

        // Preferência: numéricos
        $temp     = $toFloat($payload['temp'] ?? null);                 // °C
        $humidity = $toFloat($payload['humidity_pct'] ?? null);         // %
        $wind     = $toFloat($payload['vento_kmh'] ?? null);            // km/h

        // Fallback: strings antigas com símbolos
        if ($humidity === null) $humidity = $toFloat($payload['humidity'] ?? null, '%');
        if ($wind === null)     $wind     = $toFloat($payload['wind'] ?? null, 'km/h');

        // Compat: se vier em m/s, converte pra km/h
        if (isset($payload['wind_unit']) && strtolower((string)$payload['wind_unit']) === 'm/s' && $wind !== null) {
            $wind = $wind * 3.6;
        }

        // Validações
        if ($cidade === '')     respond_json(['status' => 'error', 'message' => 'Cidade ausente.'], 400);
        if ($temp === null)     respond_json(['status' => 'error', 'message' => 'Temperatura ausente ou inválida.'], 400);
        if ($humidity === null) respond_json(['status' => 'error', 'message' => 'Umidade ausente ou inválida.'], 400);
        if ($wind === null)     respond_json(['status' => 'error', 'message' => 'Vento ausente ou inválido.'], 400);

        // INSERT
        $sql = "INSERT INTO clima (cidade, temperatura, descricao, umidade_pct, vento_kmh)
                VALUES (:cidade, :temperatura, :descricao, :umidade, :vento)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':cidade'      => $cidade,
            ':temperatura' => $temp,
            ':descricao'   => ($desc !== '' ? $desc : null),
            ':umidade'     => $humidity,
            ':vento'       => $wind,
        ]);

        respond_json([
            'status' => 'success',
            'id'     => $pdo->lastInsertId(),
            'saved'  => [
                'cidade'      => $cidade,
                'temperatura' => $temp,
                'descricao'   => ($desc !== '' ? $desc : null),
                'umidade_pct' => $humidity,
                'vento_kmh'   => $wind,
            ]
        ]);

    } catch (\JsonException $je) {
        respond_json(['status' => 'error', 'message' => 'JSON inválido: '.$je->getMessage()], 400);
    } catch (\Throwable $e) {
        respond_json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
}

/** DADOS TEMPERATURA (para gráficos) — OWM */
function ac_dadosTemperatura(): void
{
    try {
        if (class_exists('ClimaService')) {
            $svc  = new ClimaService();
            $out  = $svc->listarTemperaturas(20);
            respond_json($out);
        }

        $pdo = db();
        $sql = "
            SELECT DATE_FORMAT(data_hora, '%H:%i') AS horario,
                   temperatura
            FROM clima
            ORDER BY data_hora DESC
            LIMIT 20
        ";
        $rows = $pdo->query($sql)->fetchAll();

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'horario'     => (string)($r['horario'] ?? ''),
                'temperatura' => isset($r['temperatura']) ? (float)$r['temperatura'] : null,
            ];
        }
        respond_json($out);

    } catch (\Throwable $e) {
        respond_json(['error' => 'Erro ao consultar temperaturas.'], 500);
    }
}

/** DADOS TEMPERATURA — Minha Estação (clima_medianeira) */
function ac_dadosTemperatura_medianeira(): void
{
    try {
        if (class_exists('ClimaServiceMedianeira')) {
            $svc = new ClimaServiceMedianeira();
            $out = $svc->listarTemperaturas(20);
            respond_json($out);
        }

        $pdo = db();
        $sql = "
            SELECT DATE_FORMAT(data_hora, '%H:%i') AS horario,
                   temperatura
            FROM clima_medianeira
            ORDER BY data_hora DESC
            LIMIT 20
        ";
        $rows = $pdo->query($sql)->fetchAll();

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'horario'     => (string)($r['horario'] ?? ''),
                'temperatura' => isset($r['temperatura']) ? (float)$r['temperatura'] : null,
            ];
        }
        respond_json($out);

    } catch (\Throwable $e) {
        respond_json(['error' => 'Erro ao consultar temperaturas (Medianeira).'], 500);
    }
}

/** SALVAR DADOS (JSON -> INSERT em "clima_medianeira") — Minha Estação (Medianeira) */
function ac_salvardados_medianeira(): void
{
    try {
        $raw = file_get_contents('php://input');
        if (!$raw) respond_json(['status'=>'error','message'=>'Corpo vazio.'], 400);
        $payload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        if (class_exists('ClimaServiceMedianeira')) {
            $svc = new ClimaServiceMedianeira();
            $id  = $svc->salvarAmostra($payload);
            respond_json(['status'=>'success','id'=>$id]);
        }

        $pdo = db();

        $cidade = trim((string)($payload['city'] ?? 'Medianeira'));
        $desc   = trim((string)($payload['desc'] ?? ''));

        // Preferir numéricos; cair pros legíveis se necessário
        $toFloat = function ($v, $strip = null) {
            if ($v === null || $v === '') return null;
            if (is_numeric($v)) return (float)$v;
            $s = (string)$v;
            if ($strip) $s = str_ireplace($strip, '', $s);
            $s = str_replace(',', '.', $s);
            $s = preg_replace('/[^0-9.\-]/', '', $s);
            return is_numeric($s) ? (float)$s : null;
        };

        $temp     = $toFloat($payload['temp'] ?? null);
        $humidity = $toFloat($payload['humidity_pct'] ?? null);
        $wind     = $toFloat($payload['vento_kmh'] ?? null);

        if ($humidity === null) $humidity = $toFloat($payload['humidity'] ?? null, '%');
        if ($wind === null)     $wind     = $toFloat($payload['wind'] ?? null, 'km/h');

        if (isset($payload['wind_unit']) && strtolower((string)$payload['wind_unit']) === 'm/s' && $wind !== null) {
            $wind = $wind * 3.6; // m/s -> km/h
        }

        if ($cidade === '')     respond_json(['status' => 'error', 'message' => 'Cidade ausente.'], 400);
        if ($temp === null)     respond_json(['status' => 'error', 'message' => 'Temperatura ausente ou inválida.'], 400);
        if ($humidity === null) respond_json(['status' => 'error', 'message' => 'Umidade ausente ou inválida.'], 400);
        if ($wind === null)     respond_json(['status' => 'error', 'message' => 'Vento ausente ou inválido.'], 400);

        $sql = "INSERT INTO clima_medianeira (cidade, temperatura, descricao, umidade_pct, vento_kmh)
                VALUES (:cidade, :temperatura, :descricao, :umidade, :vento)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':cidade'      => $cidade,
            ':temperatura' => $temp,
            ':descricao'   => ($desc !== '' ? $desc : null),
            ':umidade'     => $humidity,
            ':vento'       => $wind,
        ]);

        respond_json([
            'status' => 'success',
            'id'     => $pdo->lastInsertId(),
            'saved'  => [
                'cidade'      => $cidade,
                'temperatura' => $temp,
                'descricao'   => ($desc !== '' ? $desc : null),
                'umidade_pct' => $humidity,
                'vento_kmh'   => $wind,
            ]
        ]);

    } catch (\JsonException $je) {
        respond_json(['status'=>'error','message'=>'JSON inválido: '.$je->getMessage()], 400);
    } catch (\Throwable $e) {
        respond_json(['status'=>'error','message'=>$e->getMessage()], 500);
    }
}

/* ====== Critérios + Decisão compartilhada ====== */
function classe_por_bandas(?float $valor, array $bandas): string {
    if ($valor === null || !is_finite($valor)) return 'RED';
    foreach ($bandas as $b) {
        $from = $b['from']; $to = $b['to'];
        $fi = $b['from_inclusive']; $ti = $b['to_inclusive'];
        $minOk = ($from === null) ? true : ($fi ? $valor >= $from : $valor > $from);
        $maxOk = ($to   === null) ? true : ($ti ? $valor <= $to   : $valor < $to);
        if ($minOk && $maxOk) return $b['classe'];
    }
    return 'RED';
}
function decide_estado_por_criterios(?float $temp, ?float $umi, ?float $ventoKmh, array $C): string {
    $w = classe_por_bandas($ventoKmh, $C['vento_kmh']);
    $u = classe_por_bandas($umi,       $C['umidade_pct']);
    $t = classe_por_bandas($temp,      $C['temperatura_c']);
    if ($w === 'RED' || $u === 'RED' || $t === 'RED') return 'STOP';
    if ($w === 'GREEN' && $u === 'GREEN' && $t === 'GREEN') return 'OK';
    return 'ATENCAO';
}

/* ====== Janelas de Aplicação (sem previsão; BD) ====== */
function ac_janelas_aplicacao() {
    if (ob_get_length()) { ob_clean(); }
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');

    try {
        $pdo = db();

        // 1) Carrega critérios
        $critPath = __DIR__ . '/../config/criterios.json';
        if (!file_exists($critPath)) { http_response_code(500); echo json_encode(['erro' => 'criterios.json não encontrado']); return; }
        $C = json_decode(file_get_contents($critPath), true);
        if (!is_array($C)) { http_response_code(500); echo json_encode(['erro' => 'criterios.json inválido']); return; }
        $duracaoMinJanela = isset($C['duracao_min_janela']) ? (int)$C['duracao_min_janela'] : 60;
        $MAX_GAP_MIN = 10;

        // 2) Parâmetros
        $h = isset($_GET['h']) ? (int)$_GET['h'] : 24;
        if ($h < 1) $h = 1;
        if ($h > 168) $h = 168;

        // 3) Estado atual (registro mais recente)
        $sqlOne = "
            SELECT 
              temperatura      AS temp,
              umidade_pct      AS humidity,
              vento_kmh        AS wind,
              DATE_FORMAT(data_hora, '%Y-%m-%d %H:%i:%s') AS created_at
            FROM clima_medianeira
            ORDER BY data_hora DESC
            LIMIT 1
        ";
        $one = $pdo->query($sqlOne)->fetch();

        $estado_atual = null;
        if ($one) {
            $temp = is_numeric($one['temp']) ? (float)$one['temp'] : null;
            $umi  = is_numeric($one['humidity']) ? (float)$one['humidity'] : null;
            $vel  = is_numeric($one['wind']) ? (float)$one['wind'] : null; // km/h

            $classe = decide_estado_por_criterios($temp, $umi, $vel, $C);
            $estado_atual = [
                't_local'         => $one['created_at'],
                'classe'          => ($classe === 'OK' ? 'VERDE' : ($classe === 'ATENCAO' ? 'AMARELO' : 'VERMELHO')),
                'em_janela_verde' => ($classe === 'OK')
            ];
        }

        // 4) Histórico (últimas h horas) em ordem ASC
        $stmt = $pdo->prepare("
            SELECT 
              temperatura AS temp,
              umidade_pct AS humidity,
              vento_kmh   AS wind,
              DATE_FORMAT(data_hora, '%Y-%m-%d %H:%i:%s') AS created_at
            FROM clima_medianeira
            WHERE data_hora >= (NOW() - INTERVAL :h HOUR)
            ORDER BY data_hora ASC
        ");
        $stmt->bindValue(':h', $h, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        // -------- 5) Classificar slots --------
        $slots = [];
        foreach ($rows as $r) {
            $temp = is_numeric($r['temp']) ? (float)$r['temp'] : null;
            $umi  = is_numeric($r['humidity']) ? (float)$r['humidity'] : null;
            $vel  = is_numeric($r['wind']) ? (float)$r['wind'] : null; // km/h

            // classes por variável (debug)
            $cw = classe_por_bandas($vel,  $C['vento_kmh']);     // 'GREEN' | 'YELLOW' | 'RED'
            $cu = classe_por_bandas($umi,  $C['umidade_pct']);
            $ct = classe_por_bandas($temp, $C['temperatura_c']);

            // decisão final
            $dec  = decide_estado_por_criterios($temp, $umi, $vel, $C); // 'STOP' | 'ATENCAO' | 'OK'
            $cls  = ($dec === 'OK') ? 'VERDE' : (($dec === 'ATENCAO') ? 'AMARELO' : 'VERMELHO');

            $slots[] = [
                't_local'    => $r['created_at'],
                'classe'     => $cls,
                'temp'       => $temp,
                'umi'        => $umi,
                'vento_kmh'  => $vel,
                // -------- debug de transparência --------
                'cls_temp'   => $ct,
                'cls_umi'    => $cu,
                'cls_vento'  => $cw
            ];
        }
        // 6) Agrupar janelas VERDES (gap tolerado de 10 min)
        $janelas = [];
        $jan_ini = null;
        $t_anterior = null;

        $toDT = function(string $s) { return DateTime::createFromFormat('Y-m-d H:i:s', $s); };

        foreach ($slots as $s) {
            $cls = $s['classe'];
            $t   = $toDT($s['t_local']);
            if (!$t) continue;

            if ($cls === 'VERDE') {
                if ($jan_ini === null) {
                    $jan_ini = $t;
                } else if ($t_anterior) {
                    $diffMin = (int)round(($t->getTimestamp() - $t_anterior->getTimestamp()) / 60);
                    if ($diffMin > $MAX_GAP_MIN) {
                        $durMin = (int)round(($t_anterior->getTimestamp() - $jan_ini->getTimestamp()) / 60);
                        if ($durMin >= $duracaoMinJanela) {
                            $janelas[] = [
                                'inicio'          => $jan_ini->format(DateTime::ATOM),
                                'fim'             => $t_anterior->format(DateTime::ATOM),
                                'duracao_min'     => $durMin,
                                'confianca_media' => 0.8,
                                'fatores_chave'   => []
                            ];
                        }
                        $jan_ini = $t;
                    }
                }
            } else {
                if ($jan_ini !== null && $t_anterior) {
                    $durMin = (int)round(($t_anterior->getTimestamp() - $jan_ini->getTimestamp()) / 60);
                    if ($durMin >= $duracaoMinJanela) {
                        $janelas[] = [
                            'inicio'          => $jan_ini->format(DateTime::ATOM),
                            'fim'             => $t_anterior->format(DateTime::ATOM),
                            'duracao_min'     => $durMin,
                            'confianca_media' => 0.8,
                            'fatores_chave'   => []
                        ];
                    }
                    $jan_ini = null;
                }
            }

            $t_anterior = $t;
        }
        // fecha se terminou em VERDE
        if ($jan_ini !== null && $t_anterior !== null) {
            $durMin = (int)round(($t_anterior->getTimestamp() - $jan_ini->getTimestamp()) / 60);
            if ($durMin >= $duracaoMinJanela) {
                $janelas[] = [
                    'inicio'          => $jan_ini->format(DateTime::ATOM),
                    'fim'             => $t_anterior->format(DateTime::ATOM),
                    'duracao_min'     => $durMin,
                    'confianca_media' => 0.8,
                    'fatores_chave'   => []
                ];
            }
        }

        // 7) Saída
        $out = [
            'estacao'             => 'Medianeira,PR',
            'horizonte_horas'     => $h,
            'duracao_min_janela'  => $duracaoMinJanela,
            'estado_atual'        => $estado_atual,
            'janelas_verdes'      => $janelas,
            'slots'               => $slots
        ];

        echo json_encode($out, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['erro' => 'Falha ao montar janelas', 'detalhe' => $e->getMessage()]);
    }
}

/* ====== criterios.json passthrough ====== */
function ac_criterios() {
    if (ob_get_length()) { ob_clean(); }
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    $path = __DIR__ . '/../config/criterios.json';
    if (!file_exists($path)) { http_response_code(404); echo json_encode(['erro'=>'criterios.json não encontrado']); exit; }
    echo file_get_contents($path);
    exit;
}

/* ========= Conexão com o banco (fallback) ========= */
function db(): \PDO
{
    static $pdo = null;
    if ($pdo instanceof \PDO) return $pdo;

    $dsn     = 'mysql:host=localhost;dbname=hds;charset=utf8mb4';
    $db_user = 'root';
    $db_pass = '';

    $options = [
        \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        \PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    $pdo = new \PDO($dsn, $db_user, $db_pass, $options);
    return $pdo;
}
