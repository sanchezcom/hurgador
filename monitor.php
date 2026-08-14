<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/market.php';

if (!extension_loaded('pdo_sqlite')) {
    throw new RuntimeException('La extensión pdo_sqlite de PHP no está instalada.');
}

if (empty(FINNHUB_API_KEY) || FINNHUB_API_KEY === 'PON_AQUI_TU_API_KEY') {
    throw new RuntimeException('Configura FINNHUB_API_KEY en config.php');
}

$pdo = new PDO('sqlite:' . DB_FILE);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('PRAGMA busy_timeout = 5000');

$tickers = $pdo->query(
    'SELECT ticker FROM stocks WHERE active = 1 ORDER BY ticker'
)->fetchAll(PDO::FETCH_COLUMN);

if (!$tickers) {
    throw new RuntimeException('No hay tickers activos. Ejecuta db_create.php.');
}

$insertPrice = $pdo->prepare(
    'INSERT INTO prices (ticker, trading_date, price, volume)
     VALUES (:ticker, :date, :price, :volume)
     ON CONFLICT(ticker, trading_date)
     DO UPDATE SET price = excluded.price,
                   volume = excluded.volume,
                   fetched_at = CURRENT_TIMESTAMP'
);

$insertSignal = $pdo->prepare(
    'INSERT OR IGNORE INTO signals
     (ticker, trading_date, price, drop_5d, drop_10d, drop_20d, triggered_by, notified)
     VALUES (:ticker, :date, :price, :d5, :d10, :d20, :reason, 0)'
);

$signalsForMail = [];

foreach ($tickers as $ticker) {
    try {
        $quote = getQuote($ticker);

        $insertPrice->execute([
            ':ticker' => $ticker,
            ':date'   => $quote['date'],
            ':price'  => $quote['price'],
            ':volume' => $quote['volume'],
        ]);

        $d5  = percentageDrop($quote['price'], getPreviousPrice($pdo, $ticker, 5));
        $d10 = percentageDrop($quote['price'], getPreviousPrice($pdo, $ticker, 10));
        $d20 = percentageDrop($quote['price'], getPreviousPrice($pdo, $ticker, 20));

        $reasons = isTriggered($d5, $d10, $d20);

        // Si se ha recuperado, permitimos futuras alertas.
        if (isReset($d5, $d10, $d20)) {
            $pdo->prepare(
                'UPDATE signals
                 SET notified = 0
                 WHERE ticker = :ticker AND notified = 1'
            )->execute([':ticker' => $ticker]);
        }

        foreach ($reasons as $reason) {
            $insertSignal->execute([
                ':ticker' => $ticker,
                ':date'   => $quote['date'],
                ':price'  => $quote['price'],
                ':d5'     => $d5,
                ':d10'    => $d10,
                ':d20'    => $d20,
                ':reason' => $reason,
            ]);
        }

        // Solo enviamos una nueva alerta si existe señal pendiente.
        $stmt = $pdo->prepare(
            'SELECT id, price, drop_5d, drop_10d, drop_20d, triggered_by
             FROM signals
             WHERE ticker = :ticker AND notified = 0
             ORDER BY id ASC
             LIMIT 1'
        );
        $stmt->execute([':ticker' => $ticker]);
        $pending = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($pending !== false && !empty($reasons)) {
            $signalsForMail[] = [
                'id' => (int)$pending['id'],
                'ticker' => $ticker,
                'price' => (float)$pending['price'],
                'drop_5d' => $pending['drop_5d'],
                'drop_10d' => $pending['drop_10d'],
                'drop_20d' => $pending['drop_20d'],
                'reason' => $pending['triggered_by'],
            ];
        }

        echo sprintf(
            "%s: %.4f | 5d=%s 10d=%s 20d=%s\n",
            $ticker,
            $quote['price'],
            formatPct($d5),
            formatPct($d10),
            formatPct($d20)
        );

    } catch (Throwable $e) {
        // Un ticker con error no debe impedir procesar los demás.
        fwrite(STDERR, "$ticker: ERROR: {$e->getMessage()}\n");
    }
}

if ($signalsForMail) {
    $lines = [];
    $lines[] = "Stock Monitor - señales detectadas";
    $lines[] = str_repeat('=', 50);
    $lines[] = '';
    $lines[] = 'Estas señales NO son recomendaciones de compra.';
    $lines[] = 'Requieren revisión manual.';
    $lines[] = '';

    foreach ($signalsForMail as $signal) {
        $lines[] = sprintf(
            "%s | Precio: %.4f | 5d: %s | 10d: %s | 20d: %s | Umbral: %s",
            $signal['ticker'],
            $signal['price'],
            formatPct($signal['drop_5d']),
            formatPct($signal['drop_10d']),
            formatPct($signal['drop_20d']),
            $signal['reason']
        );
    }

    $subject = '[STOCK ALERT] ' . count($signalsForMail) . ' señal(es) detectada(s)';

    $headers = [
        'From: ' . MAIL_FROM,
        'Content-Type: text/plain; charset=UTF-8',
        'X-Mailer: PHP/' . PHP_VERSION,
    ];

    $sent = mail(
        ALERT_EMAIL,
        $subject,
        implode(PHP_EOL, $lines),
        implode("\r\n", $headers)
    );

    if ($sent) {
        $mark = $pdo->prepare(
            'UPDATE signals SET notified = 1 WHERE id = :id'
        );

        foreach ($signalsForMail as $signal) {
            $mark->execute([':id' => $signal['id']]);
        }

        echo "Email enviado: " . count($signalsForMail) . " señal(es).\n";
    } else {
        fwrite(STDERR, "mail() no pudo aceptar el mensaje.\n");
    }
}

function formatPct(?float $value): string
{
    return $value === null ? 'N/D' : sprintf('%+.2f%%', $value * 100);
}
