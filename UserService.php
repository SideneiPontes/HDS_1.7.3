<?php
declare(strict_types=1);

require_once BASE_DIR . '/model/User.php';
require_once BASE_DIR . '/model/Repos/UserRepository.php';

class UserService
{
    public function __construct(private ?UserRepository $repo = null)
    {
        $this->repo = $repo ?? new UserRepository();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /** Tenta autenticar. Aceita senhas em texto (legado) ou hash. */
    public function login(string $email, string $passwordPlain): ?User
    {
        $email = trim($email);
        if ($email === '' || $passwordPlain === '') {
            return null;
        }

        $user = $this->repo->verifyCredentials($email, $passwordPlain);
        if (!$user) return null;

        // Sobe sessão
        $_SESSION['user_id']   = $user->getId();
        $_SESSION['user_name'] = $user->fullName();

        // Opcional: “auto-upgrade” de senha de texto para hash
        if (!$user->isPasswordHashed()) {
            // silenciosamente migra para hash para melhorar segurança
            $this->repo->updatePasswordHashed((int)$user->getId(), $passwordPlain);
        }

        return $user;
    }

    /** Desloga e limpa sessão. */
    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_unset();
        session_destroy();
    }

    /** Cadastro simples (por padrão já salva em hash). */
    public function register(string $first, string $last, string $email, string $passwordPlain, bool $hash = true): int
    {
        $first = trim($first);
        $last  = trim($last);
        $email = trim($email);

        if ($first === '' || $last === '' || $email === '' || $passwordPlain === '') {
            throw new InvalidArgumentException('Preencha todos os campos.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('E-mail inválido.');
        }
        if (!$this->isStrongPassword($passwordPlain)) {
            // ajuste a política conforme desejar
            throw new InvalidArgumentException('A senha deve ter pelo menos 6 caracteres.');
        }

        $user = new User(null, $first, $last, $email, '');
        return $this->repo->create($user, $passwordPlain, $hash);
    }

    /** Altera a senha (sempre em hash). */
    public function changePassword(int $userId, string $newPlain): bool
    {
        if (!$this->isStrongPassword($newPlain)) {
            throw new InvalidArgumentException('A nova senha deve ter pelo menos 6 caracteres.');
        }
        return $this->repo->updatePasswordHashed($userId, $newPlain);
    }

    /** Busca usuário logado (ou null). */
    public function currentUser(): ?array
    {
        if (empty($_SESSION['user_id'])) return null;
        // Poderíamos buscar do BD; por ora, devolvemos o básico da sessão:
        return [
            'id'   => $_SESSION['user_id'],
            'name' => $_SESSION['user_name'] ?? '',
        ];
    }

    /** Middleware simples: exige login; retorna true/false. */
    public function requireAuth(): bool
    {
        return !empty($_SESSION['user_id']);
    }

    /** Regra mínima de força de senha (ajuste à vontade). */
    private function isStrongPassword(string $pw): bool
    {
        return mb_strlen($pw) >= 6;
    }
}
