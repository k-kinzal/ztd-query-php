<?php

declare(strict_types=1);

require __DIR__ . '/server_connection.php';

if ($argv[1] !== 'mysql') {
    throw new RuntimeException('The ordered rename observer requires MySQL.');
}
[$pdo, $environment] = connectFixture('mysql');
if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== 'ir_fixture') {
    throw new RuntimeException('The ordered rename observer requires the disposable ir_fixture database.');
}
$pdo->exec('CREATE DATABASE IF NOT EXISTS ir_rm_archive');

function renameIdentifier(string $identifier): string
{
    $tick = chr(96);
    return $tick . str_replace($tick, $tick . $tick, $identifier) . $tick;
}

function mysqlRenameRelations(PDO $pdo): array
{
    return $pdo->query(
        "SELECT TABLE_SCHEMA, TABLE_NAME, ENGINE FROM information_schema.TABLES"
        . " WHERE TABLE_SCHEMA IN ('ir_fixture','ir_rm_archive') AND LEFT(TABLE_NAME, 6) = 'ir_rm_'"
        . ' ORDER BY TABLE_SCHEMA, TABLE_NAME'
    )->fetchAll(PDO::FETCH_NUM);
}

function resetMysqlRename(PDO $pdo, string $fixture): void
{
    $targets = [];
    foreach (mysqlRenameRelations($pdo) as [$schema, $name]) {
        $targets[] = renameIdentifier($schema) . '.' . renameIdentifier($name);
    }
    if ($targets !== []) {
        $pdo->exec('DROP TABLE IF EXISTS ' . implode(', ', $targets));
    }
    $pdo->exec('CREATE TABLE ir_fixture.ir_rm_marker (step INT) ENGINE=InnoDB');
    $pdo->exec('INSERT INTO ir_fixture.ir_rm_marker VALUES (0)');
    $pdo->exec('CREATE TABLE ir_fixture.ir_rm_a (id INT, label VARCHAR(8)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin');
    $pdo->exec("INSERT INTO ir_fixture.ir_rm_a VALUES (1,'a'),(NULL,'empty'),(1,'a')");
    $pdo->exec('CREATE TABLE ir_rm_archive.ir_rm_a (id INT NOT NULL, label VARCHAR(16)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin');
    $pdo->exec("INSERT INTO ir_rm_archive.ir_rm_a VALUES (91,'decoy')");
    if ($fixture !== 'absent-second') {
        $pdo->exec('CREATE TABLE ir_fixture.ir_rm_b (id INT NOT NULL, label VARCHAR(12)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin');
        $pdo->exec("INSERT INTO ir_fixture.ir_rm_b VALUES (7,'b')");
    }
    if ($fixture === 'blocked') {
        $pdo->exec('CREATE TABLE ir_fixture.ir_rm_temp (id INT NOT NULL, label VARCHAR(10)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin');
        $pdo->exec("INSERT INTO ir_fixture.ir_rm_temp VALUES (50,'occupied')");
    }
}

function mysqlRenameState(PDO $pdo): array
{
    $relations = [];
    $heading = $pdo->prepare(
        'SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, CHARACTER_MAXIMUM_LENGTH, COLLATION_NAME'
        . ' FROM information_schema.COLUMNS WHERE BINARY TABLE_SCHEMA = ? AND BINARY TABLE_NAME = ? ORDER BY ORDINAL_POSITION'
    );
    foreach (mysqlRenameRelations($pdo) as [$schema, $table, $engine]) {
        if ($table === 'ir_rm_marker') {
            continue;
        }
        $heading->execute([$schema, $table]);
        $columns = $heading->fetchAll(PDO::FETCH_NUM);
        foreach ($columns as &$column) {
            $column[3] = $column[3] === null ? null : (int) $column[3];
        }
        unset($column);
        $name = renameIdentifier($schema) . '.' . renameIdentifier($table);
        $rows = $pdo->query('SELECT * FROM ' . $name)->fetchAll(PDO::FETCH_NUM);
        $relations[$schema . '.' . $table] = ['engine' => $engine, 'fields' => $columns, 'rows' => $rows];
    }

    return ['tables' => $relations, 'marker' => $pdo->query('SELECT step FROM ir_fixture.ir_rm_marker ORDER BY step')->fetchAll(PDO::FETCH_COLUMN)];
}

function allowMysqlRename(string $sql): bool
{
    $tick = chr(96);
    $database = '(?:ir_fixture|ir_rm_archive|' . $tick . '(?:ir_fixture|ir_rm_archive)' . $tick . ')';
    $relation = '(?:ir_rm_[0-9A-Za-z_]+|' . $tick . 'ir_rm_(?:[^' . $tick . ']|' . $tick . $tick . ')+' . $tick . ')';
    $name = '(?:' . $database . '\.)?' . $relation;
    $step = $name . ' TO ' . $name;
    return !str_contains($sql, ';') && preg_match('/\ARENAME TABLE ' . $step . '(?:, ' . $step . '){0,11}\z/D', $sql) === 1;
}

function observeMysqlRename(PDO $pdo, array $request): array
{
    $sql = $request['sql'];
    $fixture = $request['fixture'];
    if (!allowMysqlRename($sql) || !in_array($fixture, ['two', 'absent-second', 'blocked'], true)) {
        return ['status' => 'outside-observer-scope'];
    }
    resetMysqlRename($pdo, $fixture);
    $before = mysqlRenameState($pdo);
    $namespace = $pdo->query('SELECT DATABASE()')->fetchColumn();
    $pdo->beginTransaction();
    $pdo->exec('INSERT INTO ir_fixture.ir_rm_marker VALUES (7)');
    try {
        $pdo->exec($sql);
        $effect = ['executed'];
    } catch (PDOException $error) {
        $effect = ['db-error', $error->errorInfo[0], $error->errorInfo[1]];
    }
    $pdo->query('SELECT 1')->fetchColumn();
    $active = $pdo->inTransaction();
    $after = mysqlRenameState($pdo);
    if ($active) {
        $pdo->rollBack();
    } else {
        $pdo->exec('ROLLBACK');
    }

    return ['status' => 'ordered-mysql-rename', 'namespace' => $namespace, 'before' => $before, 'effect' => $effect,
        'after' => $after, 'active' => $active, 'afterRollback' => mysqlRenameState($pdo)];
}

resetMysqlRename($pdo, 'two');
echo json_encode(['database' => $environment, 'php' => PHP_VERSION, 'initial' => mysqlRenameState($pdo)], JSON_THROW_ON_ERROR) . "\n";
while (($line = fgets(STDIN)) !== false) {
    $request = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
    $batch = array_is_list($request);
    $results = array_map(static fn (array $item): array => observeMysqlRename($pdo, $item), $batch ? $request : [$request]);
    echo json_encode($batch ? $results : $results[0], JSON_THROW_ON_ERROR) . "\n";
}
