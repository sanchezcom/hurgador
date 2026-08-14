<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

$dataDir = dirname(DB_FILE);
if (!is_dir($dataDir) && !mkdir($dataDir, 0755, true)) {
    throw new RuntimeException("No se pudo crear el directorio: $dataDir");
}

$pdo = new PDO('sqlite:' . DB_FILE);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('PRAGMA journal_mode=WAL');

$pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS stocks (
    ticker TEXT PRIMARY KEY,
    active INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS prices (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ticker TEXT NOT NULL,
    trading_date TEXT NOT NULL,
    price REAL NOT NULL,
    volume INTEGER NULL,
    fetched_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(ticker, trading_date),
    FOREIGN KEY(ticker) REFERENCES stocks(ticker)
);

CREATE INDEX IF NOT EXISTS idx_prices_ticker_date
ON prices(ticker, trading_date);

CREATE TABLE IF NOT EXISTS signals (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ticker TEXT NOT NULL,
    trading_date TEXT NOT NULL,
    price REAL NOT NULL,
    drop_5d REAL NULL,
    drop_10d REAL NULL,
    drop_20d REAL NULL,
    triggered_by TEXT NOT NULL,
    notified INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(ticker, trading_date, triggered_by),
    FOREIGN KEY(ticker) REFERENCES stocks(ticker)
);
SQL);

if (!file_exists(TICKERS_FILE)) {
    file_put_contents(TICKERS_FILE, "KO,PEP,PG,MCD,JNJ,WMT,CL,KMB,MMM,GIS\n");
}

$tickers = preg_split('/\s*,\s*/', trim((string)file_get_contents(TICKERS_FILE)));
$tickers = array_values(array_unique(array_filter(array_map(
    fn($t) => strtoupper(trim($t)),
    $tickers
))));

$stmt = $pdo->prepare(
    'INSERT INTO stocks (ticker, active) VALUES (:ticker, 1)
     ON CONFLICT(ticker) DO UPDATE SET active = 1'
);

foreach ($tickers as $ticker) {
    $stmt->execute([':ticker' => $ticker]);
}

echo "Base de datos creada/actualizada: " . DB_FILE . PHP_EOL;
echo "Tickers cargados: " . count($tickers) . PHP_EOL;
