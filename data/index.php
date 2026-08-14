<?php
require_once __DIR__ . '/../config.php';

$pdo = new PDO('sqlite:' . DB_FILE);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $ticker = strtoupper(trim((string)($_POST['ticker'] ?? '')));
        if ($ticker !== '') {
            $pdo->prepare(
                'INSERT INTO stocks (ticker, active)
                 VALUES (:ticker, 1)
                 ON CONFLICT(ticker) DO UPDATE SET active = 1'
            )->execute([':ticker' => $ticker]);
        }
    }

    if ($action === 'delete') {
        $ticker = strtoupper(trim((string)($_POST['ticker'] ?? '')));
        if ($ticker !== '') {
            $pdo->beginTransaction();
            try {
                $pdo->prepare('DELETE FROM signals WHERE ticker = :ticker')->execute([':ticker' => $ticker]);
                $pdo->prepare('DELETE FROM prices WHERE ticker = :ticker')->execute([':ticker' => $ticker]);
                $pdo->prepare('DELETE FROM stocks WHERE ticker = :ticker')->execute([':ticker' => $ticker]);
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
        }
    }

    header('Location: index.php');
    exit;
}

$rows = $pdo->query('SELECT ticker FROM stocks ORDER BY ticker')->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRUD Stocks</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 700px; margin: 40px auto; padding: 20px; }
        form { display: flex; gap: 10px; margin-bottom: 20px; }
        input, button { padding: 8px 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 10px; text-align: left; }
        .delete-btn { background: #d9534f; color: white; border: 0; cursor: pointer; }
        .add-btn { background: #4CAF50; color: white; border: 0; cursor: pointer; }
    </style>
</head>
<body>
    <h1>CRUD de tickers</h1>

    <form method="post">
        <input type="hidden" name="action" value="add">
        <input type="text" name="ticker" placeholder="Ej: AAPL" maxlength="10" required>
        <button class="add-btn" type="submit">Añadir</button>
    </form>

    <table>
        <thead>
            <tr>
                <th>Ticker</th>
                <th>Acción</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$rows): ?>
                <tr>
                    <td colspan="2">No hay registros.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($rows as $ticker): ?>
                    <tr>
                        <td><?= htmlspecialchars($ticker, ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <form method="post" onsubmit="return confirm('¿Quieres eliminar este ticker?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="ticker" value="<?= htmlspecialchars($ticker, ENT_QUOTES, 'UTF-8') ?>">
                                <button class="delete-btn" type="submit">Borrar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
