<?php

declare(strict_types=1);

require __DIR__ . '/server_connection.php';
require __DIR__ . '/mysql_varchar_write.php';
require __DIR__ . '/mysql_table_effects.php';

const BTREE_TARGET = 'ir_fixture.ir_btree_subject';
const BTREE_MARKER = 'ir_fixture.ir_btree_marker';

if (($argv[1] ?? '') !== 'mysql') {
    throw new RuntimeException('The native B-tree observer requires a MySQL fixture.');
}
[$catalog, $environment] = connectFixture('mysql');
if ($catalog->query('SELECT DATABASE()')->fetchColumn() !== 'ir_fixture') {
    throw new RuntimeException('Use the disposable ir_fixture database.');
}
$owner = mysqlFixtureOwner();
$catalog->exec('DROP TABLE IF EXISTS ' . BTREE_MARKER);
$catalog->exec('CREATE TABLE ' . BTREE_MARKER . ' (n INT NOT NULL) ENGINE=InnoDB');
$catalog->exec('INSERT INTO ' . BTREE_MARKER . ' VALUES (0)');

function admitBtreeIndex(string $sql): bool
{
    if (strlen($sql) > 4500) {
        return false;
    }
    $name = '`(?:[^`\x00]|\x60\x60){1,68}`';
    $field = '`(?:f(?:[0-9]|1[0-7])|rid|unknown)`(?: ASC| DESC)?';
    $table = '(?:`ir_fixture`\.)?`ir_btree_subject`';
    return preg_match('/\ACREATE (?:UNIQUE )?INDEX ' . $name . '(?: USING BTREE)? ON ' . $table . ' \('
        . $field . '(?:, ' . $field . '){0,16}\)(?: USING BTREE)?(?: (?:VISIBLE|INVISIBLE)){0,2}\z/Du', $sql) === 1;
}

function populateIndexOwner(PDO $connection, bool $temporary, string $identity, string $data): void
{
    $columns = ['rid INT NOT NULL'];
    for ($slot = 0; $slot < 18; ++$slot) {
        $columns[] = 'f' . $slot . (in_array($slot, [1, 3], true) ? ' INT NULL' : ' INT NOT NULL');
    }
    $key = match ($identity) {
        'explicit' => 'PRIMARY KEY (rid)',
        'required' => 'UNIQUE INDEX prior_identity (f17)',
        'nullable' => 'UNIQUE INDEX prior_nullable (f3)',
        'none' => null,
    };
    if ($key !== null) {
        $columns[] = $key;
    }
    if ($data === 'occupied') {
        $columns[] = 'INDEX occupied_ix (f17)';
    }
    $connection->exec('CREATE ' . ($temporary ? 'TEMPORARY ' : '') . 'TABLE ' . BTREE_TARGET . ' ('
        . implode(', ', $columns) . ') ENGINE=InnoDB');
    $base = $temporary ? 100 : 10;
    $rows = [];
    for ($ordinal = 0; $ordinal < 3; ++$ordinal) {
        $row = [1 + $ordinal];
        for ($slot = 0; $slot < 18; ++$slot) {
            $value = $base + $slot * 7 + $ordinal * 3;
            $row[] = in_array($slot, [1, 3], true) ? 'NULL' : ($data === 'duplicate' && $slot === 0 && $ordinal === 1 ? $base : $value);
        }
        $rows[] = '(' . implode(', ', $row) . ')';
    }
    $connection->exec('INSERT INTO ' . BTREE_TARGET . ' VALUES ' . implode(', ', $rows));
}

function btreeState(PDO $connection): ?array
{
    if (mysqlFixtureTableDefinition($connection, BTREE_TARGET) === null) {
        return null;
    }
    $rows = $connection->query('SELECT * FROM ' . BTREE_TARGET . ' ORDER BY rid')->fetchAll(PDO::FETCH_NUM);
    $indexes = array_map(
        static fn (array $part): array => [$part['Key_name'], $part['Non_unique'], $part['Seq_in_index'],
            $part['Column_name'], $part['Collation'], $part['Null'] === 'YES', $part['Index_type'],
            ($part['Visible'] ?? 'YES') === 'YES', $part['Sub_part']],
        $connection->query('SHOW INDEX FROM ' . BTREE_TARGET)->fetchAll(PDO::FETCH_ASSOC)
    );
    usort($indexes, static fn (array $left, array $right): int => [$left[0], $left[2]] <=> [$right[0], $right[2]]);

    return ['rows' => $rows, 'indexes' => $indexes];
}

function btreeResult(array $effect): array
{
    return $effect[0] === 'executed' ? ['executed'] : $effect;
}

function observeBtree(PDO $catalog, PDO $owner, array $request): array
{
    if (!admitBtreeIndex($request['sql']) || !in_array($request['lifetime'], ['persistent', 'temporary', 'temporary-shadow'], true)
        || !in_array($request['identity'], ['none', 'explicit', 'required', 'nullable'], true)
        || !in_array($request['data'], ['ordinary', 'occupied', 'duplicate'], true) || !is_bool($request['probe'])) {
        return ['status' => 'outside-observer-scope'];
    }
    $owner->exec('DROP TEMPORARY TABLE IF EXISTS ' . BTREE_TARGET);
    $catalog->exec('DROP TABLE IF EXISTS ' . BTREE_TARGET);
    $catalog->exec('UPDATE ' . BTREE_MARKER . ' SET n = 0');
    $temporary = $request['lifetime'] !== 'persistent';
    if ($request['lifetime'] === 'temporary-shadow') {
        populateIndexOwner($catalog, false, 'explicit', 'ordinary');
    }
    populateIndexOwner($temporary ? $owner : $catalog, $temporary, $request['identity'], $request['data']);
    $before = btreeState($owner);
    $owner->beginTransaction();
    $owner->exec('UPDATE ' . BTREE_MARKER . ' SET n = 7');
    $effect = applyMysqlFixtureWrite($owner, $request['sql']);
    $diagnostics = mysqlFixtureDiagnostics($owner);
    $owner->query('SELECT 1')->fetchColumn();
    $active = $owner->inTransaction();
    $marker = $catalog->query('SELECT n FROM ' . BTREE_MARKER)->fetchColumn();
    if ($active) {
        $owner->rollBack();
    }
    $write = null;
    if ($request['probe']) {
        $values = $before['rows'][0];
        $values[0] = 19;
        if ($request['identity'] === 'required') {
            $values[18] = 997;
        }
        $tokens = array_map(static fn (?int $value): string => $value === null ? 'NULL' : (string) $value, $values);
        $write = btreeResult(applyMysqlFixtureWrite($owner, 'INSERT INTO ' . BTREE_TARGET . ' VALUES (' . implode(', ', $tokens) . ')'));
    }

    return ['status' => 'mysql-create-btree', 'initial' => $before, 'effect' => btreeResult($effect),
        'affected' => $effect[0] === 'executed' ? $effect[1] : null, 'diagnostics' => $diagnostics['diagnostics'],
        'after' => btreeState($owner), 'catalog' => btreeState($catalog), 'write' => $write, 'marker' => $marker, 'active' => $active];
}

echo json_encode(['database' => $environment, 'php' => PHP_VERSION], JSON_THROW_ON_ERROR) . "\n";
while (($line = fgets(STDIN)) !== false) {
    $request = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
    $batch = array_is_list($request);
    $observed = array_map(static fn (array $input): array => observeBtree($catalog, $owner, $input), $batch ? $request : [$request]);
    echo json_encode($batch ? $observed : $observed[0], JSON_THROW_ON_ERROR) . "\n";
}
