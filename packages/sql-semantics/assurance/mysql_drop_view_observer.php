<?php

declare(strict_types=1);

require __DIR__ . '/server_connection.php';

if ($argv[1] !== 'mysql') {
    throw new RuntimeException('The view removal observer requires MySQL.');
}
[$pdo, $environment] = connectFixture('mysql');
if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== 'ir_fixture') {
    throw new RuntimeException('The view removal observer requires the disposable ir_fixture database.');
}
$pdo->exec('CREATE DATABASE IF NOT EXISTS ir_dv_decoy');

function admitViewRemoval(string $sql): bool
{
    $quoted = static fn (string $name): string => '(?:' . chr(96) . $name . chr(96) . '|' . $name . ')';
    $local = $quoted('ir_fixture') . '\.' . $quoted('ir_dv_(?:a|b|missing)');
    $decoy = $quoted('ir_dv_decoy') . '\.' . $quoted('ir_dv_a');
    $target = '(?:' . $local . '|' . $decoy . ')';

    return preg_match('/\ADROP VIEW (?:IF EXISTS )?' . $target . '(?:, ' . $target . '){0,3}(?: RESTRICT| CASCADE)?\z/D', $sql) === 1;
}

function resetViewRemoval(PDO $pdo, bool $dependent, bool $shadowed): void
{
    $pdo->exec('DROP TEMPORARY TABLE IF EXISTS ir_fixture.ir_dv_a, ir_fixture.ir_dv_missing, ir_dv_decoy.ir_dv_a');
    $pdo->exec('DROP VIEW IF EXISTS ir_fixture.ir_dv_child, ir_fixture.ir_dv_a, ir_fixture.ir_dv_b, ir_dv_decoy.ir_dv_a');
    $pdo->exec('DROP TABLE IF EXISTS ir_fixture.ir_dv_base, ir_fixture.ir_dv_marker');
    $pdo->exec('CREATE TABLE ir_fixture.ir_dv_base (value INT) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE ir_fixture.ir_dv_marker (value INT) ENGINE=InnoDB');
    $pdo->exec('INSERT INTO ir_fixture.ir_dv_base VALUES (31), (7), (7), (NULL)');
    $pdo->exec('INSERT INTO ir_fixture.ir_dv_marker VALUES (0)');
    $pdo->exec('CREATE VIEW ir_fixture.ir_dv_a AS SELECT value FROM ir_fixture.ir_dv_base WHERE value = 7');
    $pdo->exec('CREATE VIEW ir_fixture.ir_dv_b AS SELECT value FROM ir_fixture.ir_dv_base WHERE value = 31');
    $pdo->exec('CREATE VIEW ir_dv_decoy.ir_dv_a AS SELECT value FROM ir_fixture.ir_dv_base WHERE value IS NULL');
    if ($dependent) {
        $pdo->exec('CREATE VIEW ir_fixture.ir_dv_child AS SELECT value FROM ir_fixture.ir_dv_a');
    }
    if ($shadowed) {
        foreach (['ir_fixture.ir_dv_a' => 101, 'ir_fixture.ir_dv_missing' => 201, 'ir_dv_decoy.ir_dv_a' => 301] as $name => $value) {
            $pdo->exec('CREATE TEMPORARY TABLE ' . $name . ' (value INT) ENGINE=InnoDB');
            $pdo->exec('INSERT INTO ' . $name . ' VALUES (NULL), (' . $value . '), (' . $value . ')');
        }
    }
}

function viewRemovalState(PDO $pdo, bool $shadowed): array
{
    $objects = $pdo->query(
        "SELECT TABLE_SCHEMA, TABLE_NAME, TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA IN ('ir_fixture','ir_dv_decoy')"
        . " AND TABLE_NAME IN ('ir_dv_a', 'ir_dv_b', 'ir_dv_base', 'ir_dv_child', 'ir_dv_marker') ORDER BY TABLE_SCHEMA, TABLE_NAME"
    )->fetchAll(PDO::FETCH_NUM);

    $temporary = [];
    if ($shadowed) {
        foreach (['ir_fixture.ir_dv_a', 'ir_fixture.ir_dv_missing', 'ir_dv_decoy.ir_dv_a'] as $name) {
            $definition = $pdo->query('SHOW CREATE TABLE ' . $name)->fetch(PDO::FETCH_NUM);
            $temporary[] = [$name, str_starts_with($definition[1], 'CREATE TEMPORARY TABLE '),
                $pdo->query('SELECT value FROM ' . $name . ' ORDER BY value')->fetchAll(PDO::FETCH_COLUMN)];
        }
    }
    return [
        'temporary' => $temporary,
        'objects' => array_map(static fn (array $kind): array => [$kind[0], $kind[1], $kind[2]], $objects),
        'base' => $pdo->query('SELECT value FROM ir_fixture.ir_dv_base ORDER BY value')->fetchAll(PDO::FETCH_COLUMN),
        'marker' => $pdo->query('SELECT value FROM ir_fixture.ir_dv_marker ORDER BY value')->fetchAll(PDO::FETCH_COLUMN),
    ];
}

function viewRemoval(PDO $pdo, array $request): array
{
    $sql = $request['sql'];
    if (!admitViewRemoval($sql) || !is_bool($request['dependent'] ?? null) || !is_bool($request['shadowed'] ?? null)) {
        return ['status' => 'outside-observer-scope'];
    }
    $shadowed = $request['shadowed'];
    resetViewRemoval($pdo, $request['dependent'], $shadowed);
    $initial = viewRemovalState($pdo, $shadowed);
    $pdo->beginTransaction();
    $pdo->exec('INSERT INTO ir_fixture.ir_dv_marker VALUES (7)');
    try {
        $pdo->exec($sql);
        $status = ['dropped'];
    } catch (PDOException $error) {
        $status = ['db-error', $error->errorInfo[0], $error->errorInfo[1]];
    }
    // Preparing SHOW WARNINGS can overwrite the failure or note it is meant to read.
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
    try {
        $diagnostics = $pdo->query('SHOW WARNINGS')->fetchAll(PDO::FETCH_NUM);
    } finally {
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    }
    // Refresh cached transaction flags after the diagnostic was captured.
    $pdo->query('SELECT 1')->fetchColumn();
    $active = $pdo->inTransaction();
    $after = viewRemovalState($pdo, $shadowed);
    if ($active) {
        $pdo->rollBack();
    } else {
        $pdo->exec('ROLLBACK');
    }

    return ['status' => 'view-removal', 'result' => $status, 'diagnostics' => $diagnostics, 'active' => $active,
        'initial' => $initial, 'after' => $after, 'afterRollback' => viewRemovalState($pdo, $shadowed)];
}

resetViewRemoval($pdo, true, false);
echo json_encode(['database' => $environment, 'php' => PHP_VERSION, 'initial' => viewRemovalState($pdo, false)], JSON_THROW_ON_ERROR) . "\n";
while (($line = fgets(STDIN)) !== false) {
    $request = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
    $batch = array_is_list($request);
    $responses = array_map(static fn (array $item): array => viewRemoval($pdo, $item), $batch ? $request : [$request]);
    echo json_encode($batch ? $responses : $responses[0], JSON_THROW_ON_ERROR) . "\n";
}
