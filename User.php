<?php
declare(strict_types=1);

class User
{
    private ?int $id;
    private string $first_name;
    private string $last_name;
    private string $email;
    private string $password; // pode ser texto puro ou hash (bcrypt)

    public function __construct(
        ?int $id,
        string $first_name,
        string $last_name,
        string $email,
        string $password
    ) {
        $this->id         = $id;
        $this->first_name = $first_name;
        $this->last_name  = $last_name;
        $this->email      = $email;
        $this->password   = $password;
    }

    public static function fromArray(array $row): self
    {
        return new self(
            isset($row['id']) ? (int)$row['id'] : null,
            (string)($row['first_name'] ?? ''),
            (string)($row['last_name'] ?? ''),
            (string)($row['email'] ?? ''),
            (string)($row['password'] ?? '')
        );
    }

    public function toArray(bool $includePassword = false): array
    {
        $out = [
            'id'         => $this->id,
            'first_name' => $this->first_name,
            'last_name'  => $this->last_name,
            'email'      => $this->email,
        ];
        if ($includePassword) {
            $out['password'] = $this->password;
        }
        return $out;
    }

    public function fullName(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    public function isPasswordHashed(): bool
    {
        // Heurística simples para bcrypt/argon
        return str_starts_with($this->password, '$2y$')
            || str_starts_with($this->password, '$2a$')
            || str_starts_with($this->password, '$argon2');
    }

    // Getters/Setters
    public function getId(): ?int { return $this->id; }
    public function getFirstName(): string { return $this->first_name; }
    public function getLastName(): string { return $this->last_name; }
    public function getEmail(): string { return $this->email; }
    public function getPassword(): string { return $this->password; }

    public function setId(?int $id): void { $this->id = $id; }
    public function setFirstName(string $v): void { $this->first_name = $v; }
    public function setLastName(string $v): void { $this->last_name = $v; }
    public function setEmail(string $v): void { $this->email = $v; }
    public function setPassword(string $v): void { $this->password = $v; }
}
