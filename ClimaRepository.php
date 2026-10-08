<?php
declare(strict_types=1);

class ClimaRepository
{
    private \PDO $pdo;

    public function __construct(?\PDO $pdo = null)
    {
        $this->pdo = $pdo ?? self::newPDO();
    }

    private static function newPDO(): \PDO
    {
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

    /** Inserção direta (já com valores prontos/validados). */
    public function inserir(
        string $cidade,
        float $temperatura,
        ?string $descricao,
        float $umidade_pct,
        float $vento_kmh,
        ?string $data_hora = null
    ): int {
        if ($data_hora) {
            $sql = "INSERT INTO clima (cidade, temperatura, descricao, umidade_pct, vento_kmh, data_hora)
                    VALUES (:cidade, :temperatura, :descricao, :umidade_pct, :vento_kmh, :data_hora)";
        } else {
            $sql = "INSERT INTO clima (cidade, temperatura, descricao, umidade_pct, vento_kmh)
                    VALUES (:cidade, :temperatura, :descricao, :umidade_pct, :vento_kmh)";
        }

        $stmt = $this->pdo->prepare($sql);
        $params = [
            ':cidade'       => $cidade,
            ':temperatura'  => $temperatura,
            ':descricao'    => $descricao,
            ':umidade_pct'  => $umidade_pct,
            ':vento_kmh'    => $vento_kmh,
        ];
        if ($data_hora) {
            $params[':data_hora'] = $data_hora; // "YYYY-mm-dd HH:ii:ss"
        }

        $stmt->execute($params);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Inserção a partir do payload bruto do seu front (igual ao usado em ac_salvardados).
     * Aceita: city, desc, temp, humidity, wind, wind_unit (m/s|km/h), pressure (opcional).
     * Converte e chama inserir(...).
     */
    public function salvarAmostraBruta(array $payload): int
    {
        $cidade = trim((string)($payload['city'] ?? ''));
        $desc   = trim((string)($payload['desc'] ?? ''));

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
        $humidity = $toFloat($payload['humidity'] ?? null, '%');
        $wind     = $toFloat($payload['wind'] ?? null, 'km/h');

        if (isset($payload['wind_unit']) && strtolower((string)$payload['wind_unit']) === 'm/s' && $wind !== null) {
            $wind = $wind * 3.6; // m/s -> km/h
        }

        if ($cidade === '' || $temp === null || $humidity === null || $wind === null) {
            throw new \InvalidArgumentException('Payload incompleto para salvar clima.');
        }

        return $this->inserir(
            $cidade,
            (float)$temp,
            ($desc !== '' ? $desc : null),
            (float)$humidity,
            (float)$wind
        );
    }

    /**
     * Retorna dados para o gráfico de temperatura (formato do seu ac_dadosTemperatura).
     * Saída: [ ['horario' => 'HH:MM', 'temperatura' => 00.0], ... ]
     */
    public function listarTemperaturas(int $limit = 20): array
    {
        $limit = max(1, min($limit, 200)); // sanidade
        $sql = "
            SELECT DATE_FORMAT(data_hora, '%H:%i') AS horario,
                   temperatura
            FROM clima
            ORDER BY data_hora DESC
            LIMIT {$limit}
        ";
        $rows = $this->pdo->query($sql)->fetchAll();

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'horario'     => (string)($r['horario'] ?? ''),
                'temperatura' => isset($r['temperatura']) ? (float)$r['temperatura'] : null,
            ];
        }
        return $out;
    }

    /** Busca objetos Clima completos (se precisar em telas futuras). */
    public function ultimosRegistros(int $limit = 50): array
    {
        $limit = max(1, min($limit, 500));
        $stmt = $this->pdo->query("
            SELECT id, cidade, temperatura, descricao, umidade_pct, vento_kmh, data_hora
            FROM clima
            ORDER BY data_hora DESC
            LIMIT {$limit}
        ");
        $rows = $stmt->fetchAll();
        return array_map(fn($r) => Clima::fromArray($r), $rows);
    }
}
