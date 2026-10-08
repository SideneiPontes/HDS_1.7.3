<?php
declare(strict_types=1);

class ClimaMedianeiraRepository
{
    /** Insere payload “bruto” vindo do front (converte/limpa tipos) */
    public function salvarAmostraBruta(array $payload): int
    {
        $pdo = db();

        $cidade = trim((string)($payload['city'] ?? 'Medianeira'));
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
        $umid     = $toFloat($payload['humidity'] ?? null, '%');
        $vento    = $toFloat($payload['wind'] ?? null, 'km/h');

        // aceita m/s via campo opcional
        if (isset($payload['wind_unit']) && strtolower((string)$payload['wind_unit']) === 'm/s' && $vento !== null) {
            $vento = $vento * 3.6;
        }

        $sql = "INSERT INTO clima_medianeira (cidade, temperatura, descricao, umidade_pct, vento_kmh)
                VALUES (:cidade, :temperatura, :descricao, :umidade, :vento)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':cidade'      => $cidade,
            ':temperatura' => $temp,
            ':descricao'   => ($desc !== '' ? $desc : null),
            ':umidade'     => $umid,
            ':vento'       => $vento,
        ]);

        return (int)$pdo->lastInsertId();
    }

    /** Lista {horario, temperatura} para gráfico (últimos N) */
    public function listarTemperaturas(int $limit = 20): array
    {
        $pdo = db();
        $sql = "
            SELECT DATE_FORMAT(data_hora, '%H:%i') AS horario,
                   temperatura
            FROM clima_medianeira
            ORDER BY data_hora DESC
            LIMIT :limite
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limite', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** Últimos registros completos (para tabela/detalhes) */
    public function ultimosRegistros(int $limit = 50): array
    {
        $pdo = db();
        $sql = "
            SELECT id, cidade, temperatura, descricao, umidade_pct, vento_kmh, data_hora
            FROM clima_medianeira
            ORDER BY data_hora DESC
            LIMIT :limite
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limite', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
