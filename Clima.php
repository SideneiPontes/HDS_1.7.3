<?php
declare(strict_types=1);

class Clima
{
    private ?int $id;
    private string $cidade;
    private float $temperatura;      // °C
    private ?string $descricao;      // texto curto (ex.: "céu limpo")
    private float $umidade_pct;      // %
    private float $vento_kmh;        // km/h
    private ?string $data_hora;      // "YYYY-mm-dd HH:ii:ss"

    public function __construct(
        ?int $id,
        string $cidade,
        float $temperatura,
        ?string $descricao,
        float $umidade_pct,
        float $vento_kmh,
        ?string $data_hora = null
    ) {
        $this->id           = $id;
        $this->cidade       = $cidade;
        $this->temperatura  = $temperatura;
        $this->descricao    = $descricao;
        $this->umidade_pct  = $umidade_pct;
        $this->vento_kmh    = $vento_kmh;
        $this->data_hora    = $data_hora;
    }

    public static function fromArray(array $r): self
    {
        return new self(
            isset($r['id']) ? (int)$r['id'] : null,
            (string)($r['cidade'] ?? ''),
            (float)($r['temperatura'] ?? 0),
            isset($r['descricao']) ? (string)$r['descricao'] : null,
            (float)($r['umidade_pct'] ?? 0),
            (float)($r['vento_kmh'] ?? 0),
            isset($r['data_hora']) ? (string)$r['data_hora'] : null
        );
    }

    public function toArray(): array
    {
        return [
            'id'           => $this->id,
            'cidade'       => $this->cidade,
            'temperatura'  => $this->temperatura,
            'descricao'    => $this->descricao,
            'umidade_pct'  => $this->umidade_pct,
            'vento_kmh'    => $this->vento_kmh,
            'data_hora'    => $this->data_hora,
        ];
    }

    // Getters (adicione setters se precisar)
    public function getId(): ?int { return $this->id; }
    public function getCidade(): string { return $this->cidade; }
    public function getTemperatura(): float { return $this->temperatura; }
    public function getDescricao(): ?string { return $this->descricao; }
    public function getUmidadePct(): float { return $this->umidade_pct; }
    public function getVentoKmh(): float { return $this->vento_kmh; }
    public function getDataHora(): ?string { return $this->data_hora; }
}
