<?php

declare(strict_types=1);

require __DIR__ . '/server_connection.php';

if ($argv[1] !== 'mysql') {
    throw new RuntimeException('Session table observation requires MySQL.');
}
[$pdo, $environment] = connectFixture('mysql');
[$catalog] = connectFixture('mysql');
if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== 'ir_fixture'
    || $catalog->query('SELECT DATABASE()')->fetchColumn() !== 'ir_fixture') {
    throw new RuntimeException('Session table observation requires the disposable ir_fixture database.');
}
$pdo->exec('CREATE DATABASE IF NOT EXISTS ir_session_decoy');

function sessionCoordinate(string $schema, string $table): string
{
    $tick = chr(96);
    return $tick . $schema . $tick . '.' . $tick . $table . $tick;
}

function sessionTableRows(PDO $pdo, string $name): ?array
{
    try {
        $query = $pdo->query('SELECT n FROM ' . $name);
        return $query->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $error) {
        if ($error->errorInfo[0] !== '42S02' || (int) $error->errorInfo[1] !== 1146) {
            throw $error;
        }
        return null;
    }
}

function sessionRows(PDO $connection): array
{
    $visible = [];
    foreach (['ir_fixture', 'ir_session_decoy'] as $schema) {
        $visible[$schema] = sessionTableRows($connection, sessionCoordinate($schema, 'ir_session_data'));
    }
    $visible['renamed'] = sessionTableRows($connection, sessionCoordinate('ir_fixture', 'ir_session_shifted'));
    return $visible;
}

function sessionState(PDO $pdo, PDO $catalog): array
{
    return [
        'visible' => sessionRows($pdo),
        'permanent' => sessionRows($catalog),
        'marker' => $pdo->query('SELECT n FROM ir_fixture.ir_session_marker ORDER BY n')->fetchAll(PDO::FETCH_COLUMN),
        'committedMarker' => $catalog->query('SELECT n FROM ir_fixture.ir_session_marker ORDER BY n')->fetchAll(PDO::FETCH_COLUMN),
    ];
}

function insertSessionRows(PDO $pdo, string $name, array $rows): void
{
    $insert = $pdo->prepare('INSERT INTO ' . $name . ' (n) VALUES (?)');
    foreach ($rows as $value) {
        $insert->execute([$value]);
    }
}

function resetMysqlSession(PDO $pdo, string $fixture, int $variant): void
{
    $main = sessionCoordinate('ir_fixture', 'ir_session_data');
    $decoy = sessionCoordinate('ir_session_decoy', 'ir_session_data');
    $pdo->exec('DROP TEMPORARY TABLE IF EXISTS ' . $main . ', ' . $decoy);
    $pdo->exec('DROP TABLE IF EXISTS ' . $main . ', ' . $decoy . ', ir_fixture.ir_session_shifted, ir_fixture.ir_session_marker');
    $pdo->exec('CREATE TABLE ir_fixture.ir_session_marker (n INT) ENGINE=InnoDB');
    $pdo->exec('INSERT INTO ir_fixture.ir_session_marker VALUES (0)');
    if ($fixture !== 'temporary-only') {
        $pdo->exec('CREATE TABLE ' . $main . ' (n INT) ENGINE=InnoDB');
        insertSessionRows($pdo, $main, [-4 - $variant, 2, 8 + $variant, null]);
    }
    $pdo->exec('CREATE TABLE ' . $decoy . ' (n INT) ENGINE=InnoDB');
    insertSessionRows($pdo, $decoy, [30 + $variant, null]);
    if ($fixture !== 'persistent') {
        $temporary = $fixture === 'cross-schema-shadowed' ? $decoy : $main;
        $pdo->exec('CREATE TEMPORARY TABLE ' . $temporary . ' (n INT) ENGINE=InnoDB');
        insertSessionRows($pdo, $temporary, [-2 - $variant, 2, 2, 4 + $variant, null]);
    }
}

function allowMysqlSession(string $sql): bool
{
    $tick = chr(96);
    $scope = '(?:ir_fixture|ir_session_decoy|' . $tick . '(?:ir_fixture|ir_session_decoy)' . $tick . ')';
    $name = '(?:' . $scope . '\.)?(?:ir_session_data|' . $tick . 'ir_session_data' . $tick . ')';
    $rename = 'RENAME TABLE ' . $name . ' TO (?:' . $scope . '\.)?(?:ir_session_shifted|' . $tick . 'ir_session_shifted' . $tick . ')';
    $alias = '(?:__ztd_source(?:0)?|ir_session_data|' . $tick . '(?:__ztd_source(?:0)?|ir_session_data)' . $tick . ')';
    $field = '(?:' . $alias . '\.)?(?:n|' . $tick . 'n' . $tick . ')';
    $bind = '(?: AS ' . $alias . ')?';
    $integer = '-?[0-9]{1,3}';
    $label = '(?:' . $tick . '(?:value|again|p0)' . $tick . '|value|again|p0)';
    $predicate = $field . ' > ' . $integer;
    $filter = ' WHERE (?:' . $predicate . '|\(' . $predicate . '\))';
    $query = 'SELECT ' . $field . ' AS ' . $label . ', ' . $field . ' AS ' . $label . ' FROM ' . $name . $bind . $filter;
    $writes = ['INSERT INTO ' . $name . ' \(' . $field . '\) (?:VALUES \(' . $integer . '\)|SELECT '
        . $integer . ' AS ' . $label . '(?: FROM DUAL)?)',
        'UPDATE ' . $name . $bind . ' SET ' . $field . ' = ' . $integer . $filter,
        'DELETE FROM ' . $name . '(?:' . $filter . ')?'];
    return preg_match('/\A(?:' . implode('|', [$query, ...$writes, $rename]) . ')\z/D', $sql) === 1;
}

function mysqlSessionResult(PDO $pdo, string $sql): array
{
    $statement = $pdo->query($sql);
    if (!str_starts_with($sql, 'SELECT ')) {
        return ['kind' => 'effect', 'count' => $statement->rowCount()];
    }
    $fields = [];
    for ($index = 0; $index < $statement->columnCount(); $index++) {
        $metadata = $statement->getColumnMeta($index);
        $fields[] = [$metadata['name'], $metadata['native_type']];
    }
    return ['kind' => 'query', 'fields' => $fields, 'rows' => $statement->fetchAll(PDO::FETCH_NUM)];
}

function observeMysqlSession(PDO $pdo, PDO $catalog, array $request): array
{
    $sql = $request['sql'];
    $fixture = $request['fixture'];
    $variant = $request['variant'];
    if (!allowMysqlSession($sql) || !in_array($fixture, ['persistent', 'temporary-only', 'shadowed', 'cross-schema-shadowed'], true)
        || !is_int($variant) || $variant < 0 || $variant > 40) {
        return ['status' => 'outside-observer-scope'];
    }
    resetMysqlSession($pdo, $fixture, $variant);
    $initial = sessionState($pdo, $catalog);
    $pdo->beginTransaction();
    $pdo->exec('INSERT INTO ir_fixture.ir_session_marker VALUES (7)');
    $result = mysqlSessionResult($pdo, $sql);
    $pdo->query('SELECT 1')->fetchColumn();
    $active = $pdo->inTransaction();
    $effect = sessionState($pdo, $catalog);
    if ($active) {
        $pdo->rollBack();
    } else {
        $pdo->exec('ROLLBACK');
    }
    return ['status' => 'mysql-session-effect', 'initial' => $initial, 'result' => $result,
        'effect' => $effect, 'active' => $active, 'restored' => sessionState($pdo, $catalog)];
}

resetMysqlSession($pdo, 'shadowed', 0);
echo json_encode(['database' => $environment, 'php' => PHP_VERSION, 'initial' => sessionState($pdo, $catalog)], JSON_THROW_ON_ERROR) . "\n";
while (($line = fgets(STDIN)) !== false) {
    $request = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
    $batch = array_is_list($request);
    $results = array_map(static fn (array $item): array => observeMysqlSession($pdo, $catalog, $item), $batch ? $request : [$request]);
    echo json_encode($batch ? $results : $results[0], JSON_THROW_ON_ERROR) . "\n";
}
