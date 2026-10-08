<?php
declare(strict_types=1);

class UserRepository
{
    private \PDO $pdo;

    public function __construct(?\PDO $pdo = null)
    {
        $this->pdo = $pdo ?? self::newPDO();
    }

    private static function newPDO(): \PDO
    {
        // Mantém compatível com seu controle.php
        $dsn     = 'mysql:host=localhost;dbname=hds;charset=utf8mb4';
        $db_user = 'root';
        $db_pass = '';
        $options = [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        return new \PDO($dsn, $db_user, $db_pass, $options);
    }

    /** Cria usuário com senha em TEXTO PURO (compatibilidade) ou já com hash (recomendado). */
    public function create(User $u, string $passwordPlain, bool $hashPassword = true): int
    {
        $passwordToStore = $hashPassword ? password_hash($passwordPlain, PASSWORD_BCRYPT) : $passwordPlain;

        $sql = "INSERT INTO users (first_name, last_name, email, password)
                VALUES (:fn, :ln, :em, :pw)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':fn' => $u->getFirstName(),
            ':ln' => $u->getLastName(),
            ':em' => $u->getEmail(),
            ':pw' => $passwordToStore,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /** Localiza por e-mail. */
    public function findByEmail(string $email): ?User
    {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        return $row ? User::fromArray($row) : null;
    }

    /**
     * Verifica credenciais:
     * - Se a senha armazenada parecer hash → usa password_verify.
     * - Caso contrário → compara texto puro.
     * Retorna User em caso de sucesso, ou null se falhar.
     */
    public function verifyCredentials(string $email, string $passwordPlain): ?User
    {
        $user = $this->findByEmail($email);
        if (!$user) return null;

        $stored = $user->getPassword();
        $ok = $user->isPasswordHashed()
            ? password_verify($passwordPlain, $stored)
            : hash_equals($stored, $passwordPlain);

        return $ok ? $user : null;
    }

    /** Atualiza senha para HASH (recomendado). */
    public function updatePasswordHashed(int $userId, string $newPlain): bool
    {
        $hash = password_hash($newPlain, PASSWORD_BCRYPT);
        $stmt = $this->pdo->prepare("UPDATE users SET password = :pw WHERE id = :id");
        return $stmt->execute([':pw' => $hash, ':id' => $userId]);
    }

    /** Lista simples (sem senha). */
    public function all(): array
    {
        $rows = $this->pdo->query("SELECT id, first_name, last_name, email, password FROM users ORDER BY id DESC")->fetchAll();
        return array_map(fn($r) => User::fromArray($r), $rows);
    }
}
