<?php
declare(strict_types=1);

class ClimaMedianeira
{
    public ?int $id = null;
    public string $cidade;
    public ?float $temperatura = null;
    public ?string $descricao = null;
    public ?float $umidade_pct = null;
    public ?float $vento_kmh = null;
    public ?string $data_hora = null; // Y-m-d H:i:s

    public function __construct(
        string $cidade,
        ?float $temperatura,
        ?string $descricao,
        ?float $umidade_pct,
        ?float $vento_kmh
    ) {
        $this->cidade       = $cidade;
        $this->temperatura  = $temperatura;
        $this->descricao    = $descricao;
        $this->umidade_pct  = $umidade_pct;
        $this->vento_kmh    = $vento_kmh;
    }
}
