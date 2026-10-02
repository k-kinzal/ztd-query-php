#!/usr/bin/env php
<?php

declare(strict_types=1);

use SqlSemantics\Facade\Semantics;
use Tests\Contract\TransactionCorpus;

require dirname(__DIR__) . '/vendor/autoload.php';

$options = getopt('', ['dialect:', 'grammar:']);
$kind = $options['dialect'] ?? 'pg';
$dialect = match ($kind) {
    'mysql' => SqlSemantics\Platform\MySql\Dialect::MySql,
    'pg' => SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql,
    default => throw new RuntimeException('Use --dialect=mysql or pg.'),
};
$dsn = getenv('SEMANTICS_DSN');
if ($dsn === false || $dsn === '') {
    throw new RuntimeException('Set SEMANTICS_DSN to a test server.');
}
$username = getenv('SEMANTICS_USERNAME');
$password = getenv('SEMANTICS_PASSWORD');
$grammar = $options['grammar'] ?? null;
if ($grammar !== null && !is_string($grammar)) {
    throw new RuntimeException('Supply --grammar once with a grammar release tag.');
}
$semantics = new Semantics($dialect, $grammar);
$connect = static fn (): PDO => new PDO($dsn, $username === false ? null : $username, $password === false ? null : $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

/**
 * @param list<string> $setup
 * @param list<string> $probes
 * @return array{observations: list<array<mixed>>, error: list<int|string|null>}
 */
function observeTransaction(PDO $pdo, array $setup, string $request, array $probes): array
{
    foreach ($setup as $sql) {
        $pdo->exec($sql);
    }
    $observations = [];
    try {
        $pdo->exec($request);
        foreach ($probes as $sql) {
            $result = $pdo->query($sql);
            assert($result instanceof PDOStatement, 'A successful observation has a result set.');
            $observations[] = $result->fetchAll(PDO::FETCH_NUM);
        }
        return ['observations' => $observations, 'error' => []];
    } catch (PDOException $error) {
        $vendorCode = $error->errorInfo[1] ?? null;
        if ($vendorCode !== null && !is_int($vendorCode) && !is_string($vendorCode)) {
            throw new RuntimeException('The PDO driver returned an invalid vendor error code.', previous: $error);
        }
        return ['observations' => $observations, 'error' => [$error->getCode(), $vendorCode]];
    }
}

if ($kind === 'mysql') {
    $connection = $connect();
    $connection->exec('START TRANSACTION');
    $instrumentation = $connection->query('SELECT t.STATE FROM performance_schema.events_transactions_current t JOIN performance_schema.threads h ON t.THREAD_ID = h.THREAD_ID WHERE h.PROCESSLIST_ID = CONNECTION_ID()');
    assert($instrumentation instanceof PDOStatement, 'The transaction instrumentation query has a result set.');
    if ($instrumentation->fetchColumn() !== 'ACTIVE') {
        throw new RuntimeException('Enable performance_schema transaction instrumentation before comparing transaction requests.');
    }
    $connection->exec('ROLLBACK');
    unset($connection);
}

$checked = 0;
foreach (TransactionCorpus::cases($kind) as [$setup, $request, $probes]) {
    $rebuilt = $semantics->analyze($request)->toString();
    $original = observeTransaction($connect(), $setup, $request, $probes);
    $reconstructed = observeTransaction($connect(), $setup, $rebuilt, $probes);
    if ($original !== $reconstructed) {
        throw new RuntimeException(json_encode(['request' => $request, 'rebuilt' => $rebuilt, 'original' => $original, 'reconstructed' => $reconstructed], JSON_THROW_ON_ERROR));
    }
    ++$checked;
}
echo json_encode(['grammar' => $semantics->language()->version, 'cases' => $checked, 'result' => 'transaction observations agree'], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
