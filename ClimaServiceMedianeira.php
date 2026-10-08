<?php
declare(strict_types=1);

require_once BASE_DIR . '/model/ClimaMedianeira.php';
require_once BASE_DIR . '/model/Repos/ClimaMedianeiraRepository.php';

class ClimaServiceMedianeira
{
    private ClimaMedianeiraRepository $repo;

    public function __construct(?ClimaMedianeiraRepository $repo = null)
    {
        $this->repo = $repo ?? new ClimaMedianeiraRepository();
    }

    /** Regras de negócio antes de persistir (se quiser validar limites, etc.) */
    public function salvarAmostra(array $payload): int
    {
        // Aqui você pode validar ranges, descartar leituras ruins, etc.
        return $this->repo->salvarAmostraBruta($payload);
    }

    public function listarTemperaturas(int $limit = 20): array
    {
        $dados = $this->repo->listarTemperaturas($limit);
        foreach ($dados as &$row) {
            if (isset($row['temperatura'])) {
                $row['temperatura'] = round((float)$row['temperatura'], 1);
            }
        }
        return $dados;
    }

    public function ultimosRegistros(int $limit = 50): array
    {
        return $this->repo->ultimosRegistros($limit);
    }
}
