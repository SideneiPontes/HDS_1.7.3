<?php
declare(strict_types=1);

require_once BASE_DIR . '/model/Clima.php';
require_once BASE_DIR . '/model/Repos/ClimaRepository.php';

class ClimaService
{
    private ClimaRepository $repo;

    public function __construct(?ClimaRepository $repo = null)
    {
        $this->repo = $repo ?? new ClimaRepository();
    }

    /**
     * Salva uma nova amostra a partir do payload bruto (JSON vindo do front).
     * Aqui você pode embutir regras de negócio:
     *  - Verificar limites aceitáveis de temperatura/umidade/vento
     *  - Decidir se registra ou descarta
     *  - Retornar mensagens específicas
     */
    public function salvarAmostra(array $payload): int
    {
        // Exemplo: se quiser aplicar regras antes de persistir
        $id = $this->repo->salvarAmostraBruta($payload);

        // Futuramente: podemos disparar eventos, logs, cálculo do "semáforo" etc.
        return $id;
    }

    /**
     * Lista dados de temperatura para gráficos.
     * Pode aplicar tratamentos antes de devolver (ex.: inverter ordem, arredondar).
     */
    public function listarTemperaturas(int $limit = 20): array
    {
        $dados = $this->repo->listarTemperaturas($limit);

        // Exemplo: arredondar valores
        foreach ($dados as &$row) {
            if (isset($row['temperatura'])) {
                $row['temperatura'] = round((float)$row['temperatura'], 1);
            }
        }

        return $dados;
    }

    /**
     * Últimos registros completos (se precisar exibir tabela detalhada no dashboard).
     */
    public function ultimosRegistros(int $limit = 50): array
    {
        return $this->repo->ultimosRegistros($limit);
    }
}
