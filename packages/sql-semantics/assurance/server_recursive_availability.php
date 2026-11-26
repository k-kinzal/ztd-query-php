<?php

declare(strict_types=1);

require __DIR__ . '/server_connection.php';

[$pdo, $environment] = connectFixture('mysql');
$query = 'WITH RECURSIVE t(n) AS (SELECT 1 UNION ALL SELECT n + 1 FROM t WHERE n < 3) SELECT n AS value FROM t';
$pdo->exec('START TRANSACTION READ ONLY');
$control = $pdo->query('SELECT 7 AS control')->fetchColumn();
try {
    $rows = $pdo->query($query)->fetchAll(PDO::FETCH_NUM);
    $observation = ['status' => 'rows', 'rows' => $rows];
} catch (PDOException $exception) {
    $observation = ['status' => 'database-rejection', 'state' => $exception->errorInfo[0], 'error' => $exception->errorInfo[1]];
}
$pdo->exec('ROLLBACK');

echo json_encode([
    'database' => $environment, 'php' => PHP_VERSION, 'control' => $control,
    'query' => $query, 'observation' => $observation,
], JSON_THROW_ON_ERROR) . "\n";
