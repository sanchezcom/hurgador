<?php
declare(strict_types=1);

require_once __DIR__ . '\..\config.php';

/**
 * Consulta la última cotización de Finnhub.
 * El endpoint devuelve el último precio y volumen disponible para el ticker.
 */
function getQuote(string $ticker): array
{
    $query = http_build_query([
        'symbol' => $ticker,
        'token'  => FINNHUB_API_KEY,
    ]);

    $url = FINNHUB_URL . '/quote?' . $query;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => API_TIMEOUT_SECONDS,
        CURLOPT_USERAGENT      => 'stock-monitor/1.0',
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);

    $body = curl_exec($ch);

    if ($body === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException("cURL: $error");
    }

    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new RuntimeException("Finnhub HTTP $httpCode");
    }

    $data = json_decode($body, true);

    if (!is_array($data)) {
        throw new RuntimeException("Respuesta JSON inválida para $ticker");
    }

    if (isset($data['error'])) {
        throw new RuntimeException("API: " . $data['error']);
    }

    if (!array_key_exists('c', $data) || !is_numeric($data['c'])) {
        throw new RuntimeException("No se recibió precio para $ticker");
    }

    return [
        'price'  => (float)$data['c'],
        'volume' => isset($data['v']) && is_numeric($data['v']) ? (int)$data['v'] : null,
        'date'   => isset($data['t']) && is_numeric($data['t']) ? date('Y-m-d', (int)$data['t']) : date('Y-m-d'),
    ];
}

function getPreviousPrice(PDO $pdo, string $ticker, int $tradingDaysBack): ?float
{
    // OFFSET 0 = última observación guardada; por eso usamos N-1.
    $offset = max(0, $tradingDaysBack - 1);

    $stmt = $pdo->prepare(
        'SELECT price
         FROM prices
         WHERE ticker = :ticker
         ORDER BY trading_date DESC
         LIMIT 1 OFFSET :offset'
    );
    $stmt->bindValue(':ticker', $ticker, PDO::PARAM_STR);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $value = $stmt->fetchColumn();

    return $value === false ? null : (float)$value;
}

function percentageDrop(?float $current, ?float $previous): ?float
{
    if ($current === null || $previous === null || $previous <= 0) {
        return null;
    }

    // Resultado negativo = caída.
    return ($current / $previous) - 1.0;
}

function isTriggered(?float $drop5, ?float $drop10, ?float $drop20): array
{
    $reasons = [];

    if ($drop5 !== null && $drop5 <= -DROP_5D) {
        $reasons[] = '5d';
    }

    if ($drop10 !== null && $drop10 <= -DROP_10D) {
        $reasons[] = '10d';
    }

    if ($drop20 !== null && $drop20 <= -DROP_20D) {
        $reasons[] = '20d';
    }

    return $reasons;
}

function isReset(?float $drop5, ?float $drop10, ?float $drop20): bool
{
    $checks = [];

    if ($drop5 !== null)  $checks[] = $drop5 > -RESET_5D;
    if ($drop10 !== null) $checks[] = $drop10 > -RESET_10D;
    if ($drop20 !== null) $checks[] = $drop20 > -RESET_20D;

    return !empty($checks) && !in_array(false, $checks, true);
}
