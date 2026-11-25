<?php

declare(strict_types=1);

require __DIR__ . '/server_connection.php';
require __DIR__ . '/mysql_varchar_write.php';
require __DIR__ . '/mysql_table_effects.php';

const DROP_INDEX_OWNER = 'ir_fixture.ir_index_removal';
const DROP_INDEX_MARKER = 'ir_fixture.ir_index_remove_marker';
const DROP_INDEX_NAME = '`(?:[^`\x00]|\x60\x60){1,68}`';

if (($argv[1] ?? '') !== 'mysql') {
    throw new RuntimeException('The native index removal observer requires a MySQL fixture.');
}
[$catalog, $environment] = connectFixture('mysql');
if ($catalog->query('SELECT DATABASE()')->fetchColumn() !== 'ir_fixture') {
    throw new RuntimeException('Use the disposable ir_fixture database.');
}
$owner = mysqlFixtureOwner();
$catalog->exec('DROP TABLE IF EXISTS ' . DROP_INDEX_MARKER);
$catalog->exec('CREATE TABLE ' . DROP_INDEX_MARKER . ' (n INT NOT NULL) ENGINE=InnoDB');
$catalog->exec('INSERT INTO ' . DROP_INDEX_MARKER . ' VALUES (0)');

function admitDropIndex(string $sql): bool
{
    return strlen($sql) <= 600 && preg_match('/\ADROP INDEX ' . DROP_INDEX_NAME . ' ON (?:`ir_fixture`\.)?`ir_index_removal`'
        . '(?: (?:ALGORITHM|LOCK)(?:=| )DEFAULT){0,2}\z/Du', $sql) === 1;
}

function admitIndexFixture(string $sql): bool
{
    $name = DROP_INDEX_NAME;
    $columnList = '\(' . $name . '(?:, ' . $name . ')?\)';
    $constraint = '(?:PRIMARY KEY|(?:UNIQUE )?INDEX(?: ' . $name . ')?|CONSTRAINT ' . $name . ' UNIQUE) ' . $columnList;

    return strlen($sql) <= 1400 && preg_match('/\ACREATE (?:TEMPORARY )?TABLE `ir_fixture`.`ir_index_removal` \('
        . $name . ' INT NOT NULL(?: PRIMARY KEY| UNIQUE)?, `b` INT NULL, `c` INT NOT NULL'
        . '(?:, ' . $constraint . '){2,4}\) ENGINE=InnoDB\z/Du', $sql) === 1;
}

function dropIndexQuote(string $name): string
{
    return '`' . str_replace('`', '``', $name) . '`';
}

function dropIndexRows(PDO $target, array $rows): void
{
    $insert = $target->prepare('INSERT INTO ' . DROP_INDEX_OWNER . ' VALUES (?, ?, ?)');
    foreach ($rows as $row) {
        $insert->execute($row);
    }
}

function dropIndexState(PDO $target, string $layout): ?array
{
    if (mysqlFixtureTableDefinition($target, DROP_INDEX_OWNER) === null) {
        return null;
    }
    $fields = $target->query('SHOW FULL COLUMNS FROM ' . DROP_INDEX_OWNER)->fetchAll(PDO::FETCH_ASSOC);
    $columns = array_map(static fn (array $field): array => [$field['Field'], $field['Type'], $field['Null'] === 'YES',
        $field['Default'], $field['Extra'], $field['Collation']], $fields);
    $rows = $target->query('SELECT * FROM ' . DROP_INDEX_OWNER . ' ORDER BY ' . dropIndexQuote($layout) . ', b')->fetchAll(PDO::FETCH_NUM);
    $indexes = array_map(static fn (array $part): array => [$part['Key_name'], $part['Non_unique'], $part['Seq_in_index'],
        $part['Column_name'], $part['Collation'], $part['Null'] === 'YES', $part['Index_type'],
        ($part['Visible'] ?? 'YES') === 'YES', $part['Sub_part']], $target->query('SHOW INDEX FROM ' . DROP_INDEX_OWNER)->fetchAll(PDO::FETCH_ASSOC));
    usort($indexes, static fn (array $left, array $right): int => [$left[0], $left[2]] <=> [$right[0], $right[2]]);

    return ['rows' => $rows, 'columns' => $columns, 'indexes' => $indexes];
}

function dropIndexEffect(array $effect): array
{
    return $effect[0] === 'executed' ? ['executed'] : $effect;
}

function observeDropIndex(PDO $catalog, PDO $owner, array $request): array
{
    $life = $request['lifetime'];
    if (!admitDropIndex($request['sql']) || !in_array($life, ['persistent', 'temporary', 'temporary-shadow'], true)
        || !is_bool($request['probe']) || preg_match('/\A' . DROP_INDEX_NAME . '\z/Du', dropIndexQuote($request['field'])) !== 1) {
        return ['status' => 'outside-observer-scope'];
    }
    foreach ($request['declarations'] as $ddl) {
        if (!admitIndexFixture($ddl)) {
            return ['status' => 'outside-observer-scope'];
        }
    }
    $owner->exec('DROP TEMPORARY TABLE IF EXISTS ' . DROP_INDEX_OWNER);
    $catalog->exec('DROP TABLE IF EXISTS ' . DROP_INDEX_OWNER);
    $catalog->exec('UPDATE ' . DROP_INDEX_MARKER . ' SET n = 0');
    foreach ($request['declarations'] as $ddl) {
        $temporary = str_starts_with($ddl, 'CREATE TEMPORARY');
        $target = $temporary ? $owner : $catalog;
        $target->exec($ddl);
        dropIndexRows($target, $request['rows'][$temporary ? 'temporary' : 'persistent']);
    }
    $before = dropIndexState($owner, $request['field']);
    $owner->beginTransaction();
    $owner->exec('UPDATE ' . DROP_INDEX_MARKER . ' SET n = 7');
    $effect = applyMysqlFixtureWrite($owner, $request['sql']);
    $diagnostics = mysqlFixtureDiagnostics($owner);
    $owner->query('SELECT 1')->fetchColumn();
    $active = $owner->inTransaction();
    $marker = $catalog->query('SELECT n FROM ' . DROP_INDEX_MARKER)->fetchColumn();
    if ($active) {
        $owner->rollBack();
    }
    $released = $retained = null;
    if ($request['probe']) {
        [$first, , $last] = $before['rows'][0];
        $released = dropIndexEffect(applyMysqlFixtureWrite($owner, 'INSERT INTO ' . DROP_INDEX_OWNER
            . ' VALUES (' . (int) $first . ', 63, ' . (int) $last . ')'));
        $retained = dropIndexEffect(applyMysqlFixtureWrite($owner, 'INSERT INTO ' . DROP_INDEX_OWNER . ' VALUES (700, 3, 97)'));
    }

    return ['status' => 'mysql-drop-index', 'initial' => $before, 'effect' => dropIndexEffect($effect),
        'affected' => $effect[0] === 'executed' ? $effect[1] : null, 'diagnostics' => $diagnostics['diagnostics'],
        'after' => dropIndexState($owner, $request['field']), 'catalog' => dropIndexState($catalog, $request['field']),
        'released' => $released, 'retained' => $retained, 'marker' => $marker, 'active' => $active];
}

echo json_encode(['database' => $environment, 'php' => PHP_VERSION], JSON_THROW_ON_ERROR) . "\n";
while (($line = fgets(STDIN)) !== false) {
    $request = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
    $batch = array_is_list($request);
    $observed = array_map(static fn (array $entry): array => observeDropIndex($catalog, $owner, $entry), $batch ? $request : [$request]);
    echo json_encode($batch ? $observed : $observed[0], JSON_THROW_ON_ERROR) . "\n";
}
