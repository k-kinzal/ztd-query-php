<?php

declare(strict_types=1);

require __DIR__ . '/server_connection.php';

if ($argv[1] !== 'mysql') {
    throw new RuntimeException('Session table removal requires MySQL.');
}
[$pdo, $environment] = connectFixture('mysql');
[$catalog] = connectFixture('mysql');
if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== 'ir_fixture'
    || $catalog->query('SELECT DATABASE()')->fetchColumn() !== 'ir_fixture') {
    throw new RuntimeException('Session table removal requires the disposable ir_fixture database.');
}
$pdo->exec('CREATE DATABASE IF NOT EXISTS ir_sdrop_decoy');

const DROP_PRIMARY = 'ir_fixture.ir_sdrop_data';
const DROP_DECOY = 'ir_sdrop_decoy.ir_sdrop_data';

function resetSessionDrop(PDO $pdo, string $fixture, int $variant): void
{
    $pdo->exec('DROP TEMPORARY TABLE IF EXISTS ' . DROP_PRIMARY . ', ' . DROP_DECOY);
    $pdo->exec('DROP TABLE IF EXISTS ' . DROP_PRIMARY . ', ' . DROP_DECOY . ', ir_fixture.ir_sdrop_marker');
    $pdo->exec('CREATE TABLE ir_fixture.ir_sdrop_marker (n INT) ENGINE=InnoDB');
    $pdo->exec('INSERT INTO ir_fixture.ir_sdrop_marker VALUES (0)');
    foreach ([DROP_PRIMARY => 20, DROP_DECOY => 40] as $name => $base) {
        if ($name === DROP_PRIMARY && $fixture === 'temporary-only-primary') {
            continue;
        }
        $value = (string) ($base + $variant);
        $pdo->exec('CREATE TABLE ' . $name . ' (n INT) ENGINE=InnoDB');
        $pdo->exec('INSERT INTO ' . $name . ' VALUES (NULL), (' . $value . '), (' . $value . ')');
    }
    $temporaries = match ($fixture) {
        'shadow-primary', 'temporary-only-primary' => [DROP_PRIMARY],
        'shadow-decoy' => [DROP_DECOY],
        'shadow-both' => [DROP_PRIMARY, DROP_DECOY],
    };
    foreach ($temporaries as $name) {
        $value = (string) (($name === DROP_PRIMARY ? 120 : 140) + $variant);
        $pdo->exec('CREATE TEMPORARY TABLE ' . $name . ' (n INT) ENGINE=InnoDB');
        $pdo->exec('INSERT INTO ' . $name . ' VALUES (NULL), (' . $value . '), (' . $value . ')');
    }
}

function sessionDropRelation(PDO $pdo, string $name): ?array
{
    try {
        $definition = $pdo->query('SHOW CREATE TABLE ' . $name)->fetch(PDO::FETCH_NUM)[1];
    } catch (PDOException $error) {
        if ($error->errorInfo[0] !== '42S02' || (int) $error->errorInfo[1] !== 1146) {
            throw $error;
        }
        return null;
    }
    $kind = str_starts_with($definition, 'CREATE TEMPORARY TABLE ') ? 'temporary-table' : 'table';
    return [$kind, $pdo->query('SELECT n FROM ' . $name . ' ORDER BY n')->fetchAll(PDO::FETCH_COLUMN)];
}

function sessionDropState(PDO $pdo, PDO $catalog): array
{
    $visible = $permanent = [];
    foreach ([DROP_PRIMARY, DROP_DECOY] as $name) {
        $visible[$name] = sessionDropRelation($pdo, $name);
        $permanent[$name] = sessionDropRelation($catalog, $name);
    }
    $marker = 'SELECT n FROM ir_fixture.ir_sdrop_marker ORDER BY n';
    return ['visible' => $visible, 'permanent' => $permanent,
        'marker' => $pdo->query($marker)->fetchAll(PDO::FETCH_COLUMN),
        'committedMarker' => $catalog->query($marker)->fetchAll(PDO::FETCH_COLUMN)];
}

function allowSessionDrop(string $sql): bool
{
    $tick = chr(96);
    $database = '(?:ir_fixture|ir_sdrop_decoy|' . $tick . '(?:ir_fixture|ir_sdrop_decoy)' . $tick . ')';
    $table = '(?:ir_sdrop_data|' . $tick . 'ir_sdrop_data' . $tick . ')';
    $name = '(?:' . $database . '\.)?' . $table;
    return preg_match('/\ADROP (?:TEMPORARY )?TABLE (?:IF EXISTS )?' . $name
        . '(?:, ' . $name . ')?(?: RESTRICT| CASCADE)?\z/D', $sql) === 1;
}

function sessionDropDiagnostics(PDO $pdo): array
{
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
    try {
        return $pdo->query('SHOW WARNINGS')->fetchAll(PDO::FETCH_NUM);
    } finally {
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    }
}

function observeSessionDrop(PDO $pdo, PDO $catalog, array $request): array
{
    $sql = $request['sql'];
    $fixture = $request['fixture'];
    $variant = $request['variant'];
    if (!allowSessionDrop($sql) || !in_array($fixture, ['shadow-primary', 'shadow-decoy', 'shadow-both', 'temporary-only-primary'], true)
        || !is_int($variant) || $variant < 0 || $variant > 40) {
        return ['status' => 'outside-observer-scope'];
    }
    resetSessionDrop($pdo, $fixture, $variant);
    $initial = sessionDropState($pdo, $catalog);
    $pdo->beginTransaction();
    $pdo->exec('INSERT INTO ir_fixture.ir_sdrop_marker VALUES (7)');
    try {
        $effect = ['dropped', $pdo->exec($sql)];
    } catch (PDOException $error) {
        $effect = ['db-error', $error->errorInfo[0], $error->errorInfo[1]];
    }
    $diagnostics = sessionDropDiagnostics($pdo);
    $pdo->query('SELECT 1')->fetchColumn();
    $active = $pdo->inTransaction();
    $after = sessionDropState($pdo, $catalog);
    if ($active) {
        $pdo->rollBack();
    } else {
        $pdo->exec('ROLLBACK');
    }
    return ['status' => 'mysql-session-drop', 'initial' => $initial, 'effect' => $effect, 'diagnostics' => $diagnostics,
        'active' => $active, 'after' => $after, 'restored' => sessionDropState($pdo, $catalog)];
}

resetSessionDrop($pdo, 'shadow-both', 0);
echo json_encode(['database' => $environment, 'php' => PHP_VERSION, 'initial' => sessionDropState($pdo, $catalog)], JSON_THROW_ON_ERROR) . "\n";
while (($line = fgets(STDIN)) !== false) {
    $request = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
    $batch = array_is_list($request);
    $results = array_map(static fn (array $item): array => observeSessionDrop($pdo, $catalog, $item), $batch ? $request : [$request]);
    echo json_encode($batch ? $results : $results[0], JSON_THROW_ON_ERROR) . "\n";
}
