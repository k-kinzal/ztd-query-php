<?php

declare(strict_types=1);

require __DIR__ . '/server_connection.php';

if ($argv[1] !== 'mysql') {
    throw new RuntimeException('The MySQL truncation observer requires MySQL.');
}
[$pdo, $environment] = connectFixture('mysql');
if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== 'ir_fixture') {
    throw new RuntimeException('The MySQL truncation observer requires the disposable ir_fixture database.');
}
$pdo->exec('CREATE DATABASE IF NOT EXISTS ir_mtr_decoy');
if (version_compare($environment[0], '8.0.0', '>=')) {
    $pdo->exec('SET SESSION information_schema_stats_expiry = 0');
}

function truncateIdentifier(string $identifier): string
{
    $tick = chr(96);
    return $tick . str_replace($tick, $tick . $tick, $identifier) . $tick;
}

function truncateName(string $schema, string $table): string
{
    return truncateIdentifier($schema) . '.' . truncateIdentifier($table);
}

function truncateRelations(string $fixture): array
{
    $relations = [
        ['ir_fixture', 'ir_mtr_a', 11, 8],
        ['ir_fixture', 'ir_mtr_A', 21, 12],
        ['ir_fixture', 'ir_mtr_b', 31, 13],
        ['ir_fixture', 'ir_mtr_' . chr(96) . 'quote', 41, 16],
        ['ir_mtr_decoy', 'ir_mtr_a', 81, 14],
    ];
    if (str_starts_with($fixture, 'foreign-key-')) {
        $relations[] = ['ir_fixture', 'ir_mtr_ref', 91, 9];
    }
    return $relations;
}

function resetMysqlTruncate(PDO $pdo, string $fixture, int $variant): void
{
    $pdo->exec('DROP TABLE IF EXISTS ir_fixture.ir_mtr_ref');
    foreach (truncateRelations('plain') as [$schema, $table]) {
        $pdo->exec('DROP TABLE IF EXISTS ' . truncateName($schema, $table));
    }
    $pdo->exec('DROP TABLE IF EXISTS ir_fixture.ir_mtr_marker, ir_fixture.ir_mtr_events');
    $pdo->exec('CREATE TABLE ir_fixture.ir_mtr_marker (step INT) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE ir_fixture.ir_mtr_events (row_id INT) ENGINE=InnoDB');
    $pdo->exec('INSERT INTO ir_fixture.ir_mtr_marker VALUES (0)');
    foreach (truncateRelations($fixture) as [$schema, $table, $initial, $width]) {
        $name = truncateName($schema, $table);
        $foreignKey = '';
        if ($table === 'ir_mtr_ref' || ($fixture === 'self-reference' && $initial === 11)) {
            $foreignKey = ', KEY parent_index (parent_id), CONSTRAINT ir_mtr_parent_fk FOREIGN KEY (parent_id)'
                . ' REFERENCES ir_fixture.ir_mtr_a (id)';
        }
        $pdo->exec('CREATE TABLE ' . $name . ' (id INT NOT NULL AUTO_INCREMENT, label VARCHAR(' . $width . '),'
            . ' parent_id INT, PRIMARY KEY (id)' . $foreignKey . ') ENGINE=InnoDB'
            . ' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin AUTO_INCREMENT=' . $initial);
        if ($table === 'ir_mtr_ref' && $fixture === 'foreign-key-empty') {
            continue;
        }
        $insert = $pdo->prepare('INSERT INTO ' . $name . ' (label, parent_id) VALUES (?, ?)');
        $label = $initial . ':' . $variant;
        $insert->execute([$label, $table === 'ir_mtr_ref' ? 11 : null]);
        if ($initial === 11) {
            $insert->execute([$label, $fixture === 'self-reference' ? 11 : null]);
            $insert->execute([null, null]);
        }
    }
}

function mysqlTruncateTriggers(PDO $pdo, string $fixture): void
{
    foreach (truncateRelations($fixture) as $position => [$schema, $table]) {
        $trigger = truncateName($schema, 'ir_mtr_after_delete_' . $position);
        $pdo->exec('CREATE TRIGGER ' . $trigger . ' AFTER DELETE ON ' . truncateName($schema, $table)
            . ' FOR EACH ROW INSERT INTO ir_fixture.ir_mtr_events VALUES (OLD.id)');
    }
}

function mysqlTruncateState(PDO $pdo, string $fixture): array
{
    $result = [];
    $columns = $pdo->prepare('SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, CHARACTER_MAXIMUM_LENGTH, COLLATION_NAME, EXTRA'
        . ' FROM information_schema.COLUMNS WHERE BINARY TABLE_SCHEMA = ? AND BINARY TABLE_NAME = ? ORDER BY ORDINAL_POSITION');
    $indices = $pdo->prepare('SELECT INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME'
        . ' FROM information_schema.STATISTICS WHERE BINARY TABLE_SCHEMA = ? AND BINARY TABLE_NAME = ? ORDER BY INDEX_NAME, SEQ_IN_INDEX');
    $references = $pdo->prepare('SELECT CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_SCHEMA, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME'
        . ' FROM information_schema.KEY_COLUMN_USAGE WHERE BINARY TABLE_SCHEMA = ? AND BINARY TABLE_NAME = ?'
        . ' AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY CONSTRAINT_NAME, ORDINAL_POSITION');
    $triggers = $pdo->prepare('SELECT ACTION_TIMING, EVENT_MANIPULATION, TRIGGER_NAME FROM information_schema.TRIGGERS'
        . ' WHERE BINARY EVENT_OBJECT_SCHEMA = ? AND BINARY EVENT_OBJECT_TABLE = ? ORDER BY TRIGGER_NAME');
    foreach (truncateRelations($fixture) as [$schema, $table]) {
        $key = [$schema, $table];
        foreach ([$columns, $indices, $references, $triggers] as $catalog) {
            $catalog->execute($key);
        }
        $fields = $columns->fetchAll(PDO::FETCH_NUM);
        foreach ($fields as &$field) {
            $field[3] = $field[3] === null ? null : (int) $field[3];
        }
        unset($field);
        $index = $indices->fetchAll(PDO::FETCH_NUM);
        foreach ($index as &$part) {
            $part[1] = (int) $part[1];
            $part[2] = (int) $part[2];
        }
        unset($part);
        $name = truncateName($schema, $table);
        $rows = $pdo->query('SELECT * FROM ' . $name)->fetchAll(PDO::FETCH_NUM);
        $status = $pdo->query('SHOW TABLE STATUS FROM ' . truncateIdentifier($schema)
            . ' WHERE BINARY Name = ' . $pdo->quote($table))->fetch(PDO::FETCH_ASSOC);
        $result[$schema . '.' . $table] = ['fields' => $fields, 'indices' => $index, 'references' => $references->fetchAll(PDO::FETCH_NUM),
            'triggers' => $triggers->fetchAll(PDO::FETCH_NUM), 'engine' => $status['Engine'],
            'nextId' => $status['Auto_increment'] === null ? null : (int) $status['Auto_increment'], 'rows' => $rows];
    }
    return ['relations' => $result, 'marker' => $pdo->query('SELECT step FROM ir_fixture.ir_mtr_marker ORDER BY step')->fetchAll(PDO::FETCH_COLUMN),
        'deleteEvents' => $pdo->query('SELECT row_id FROM ir_fixture.ir_mtr_events ORDER BY row_id')->fetchAll(PDO::FETCH_COLUMN)];
}

function allowMysqlTruncate(string $sql): bool
{
    $tick = chr(96);
    $schema = '(?:ir_fixture|ir_mtr_decoy|' . $tick . '(?:ir_fixture|ir_mtr_decoy)' . $tick . ')';
    $relation = '(?:ir_mtr_[0-9A-Za-z_]+|' . $tick . 'ir_mtr_(?:[0-9A-Za-z_]|' . $tick . $tick . ')+' . $tick . ')';
    return preg_match('/\ATRUNCATE (?:TABLE )?(?:' . $schema . '\.)?' . $relation . '\z/D', $sql) === 1;
}

function observeMysqlTruncate(PDO $pdo, array $request): array
{
    $sql = $request['sql'];
    $fixture = $request['fixture'];
    $variant = $request['variant'];
    if (!allowMysqlTruncate($sql) || !in_array($fixture, ['plain', 'foreign-key-filled', 'foreign-key-empty', 'self-reference'], true)
        || !is_int($variant) || $variant < 0 || $variant > 99) {
        return ['status' => 'outside-observer-scope'];
    }
    resetMysqlTruncate($pdo, $fixture, $variant);
    mysqlTruncateTriggers($pdo, $fixture);
    $before = mysqlTruncateState($pdo, $fixture);
    $settings = $pdo->query('SELECT DATABASE(), @@foreign_key_checks, @@auto_increment_increment, @@auto_increment_offset')->fetch(PDO::FETCH_NUM);
    $pdo->beginTransaction();
    $pdo->exec('INSERT INTO ir_fixture.ir_mtr_marker VALUES (7)');
    try {
        $affected = $pdo->exec($sql);
        $effect = ['executed', $affected];
    } catch (PDOException $error) {
        $effect = ['db-error', $error->errorInfo[0], $error->errorInfo[1]];
    }
    $pdo->query('SELECT 1')->fetchColumn();
    $active = $pdo->inTransaction();
    $after = mysqlTruncateState($pdo, $fixture);
    if ($active) {
        $pdo->rollBack();
    } else {
        $pdo->exec('ROLLBACK');
    }
    $afterRollback = mysqlTruncateState($pdo, $fixture);
    $allocated = [];
    foreach (truncateRelations($fixture) as [$schema, $table]) {
        $pdo->exec('INSERT INTO ' . truncateName($schema, $table) . ' (label) VALUES (NULL)');
        $allocated[$schema . '.' . $table] = (int) $pdo->lastInsertId();
    }
    return ['status' => 'mysql-truncate-effect', 'settings' => $settings, 'before' => $before,
        'effect' => $effect, 'active' => $active, 'after' => $after, 'afterRollback' => $afterRollback, 'allocated' => $allocated];
}

resetMysqlTruncate($pdo, 'plain', 0);
mysqlTruncateTriggers($pdo, 'plain');
echo json_encode(['database' => $environment, 'php' => PHP_VERSION, 'initial' => mysqlTruncateState($pdo, 'plain')], JSON_THROW_ON_ERROR) . "\n";
while (($line = fgets(STDIN)) !== false) {
    $request = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
    $batch = array_is_list($request);
    $results = array_map(static fn (array $item): array => observeMysqlTruncate($pdo, $item), $batch ? $request : [$request]);
    echo json_encode($batch ? $results : $results[0], JSON_THROW_ON_ERROR) . "\n";
}
