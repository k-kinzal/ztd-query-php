<?php

declare(strict_types=1);

require_once __DIR__ . '/mysql_index_observation.php';

const BTREE_TARGET = 'ir_fixture.ir_btree_subject';
const BTREE_MARKER = 'ir_fixture.ir_btree_marker';

function initBtree(PDO $catalog): void
{
    $catalog->exec('DROP TABLE IF EXISTS ' . BTREE_MARKER);
    $catalog->exec('CREATE TABLE ' . BTREE_MARKER . ' (n INT NOT NULL) ENGINE=InnoDB');
    $catalog->exec('INSERT INTO ' . BTREE_MARKER . ' VALUES (0)');
}

function admitBtreeIndex(string $sql): bool
{
    if (strlen($sql) > 4500) {
        return false;
    }
    $name = '`(?:[^`\x00]|\x60\x60){1,68}`';
    $field = '`(?:f(?:[0-9]|1[0-7])|rid|unknown)`(?: ASC| DESC)?';
    $table = '(?:`ir_fixture`\.)?`ir_btree_subject`';
    return preg_match('/\ACREATE (?:UNIQUE )?INDEX ' . $name . '(?: USING BTREE)? ON ' . $table . ' \('
        . $field . '(?:, ' . $field . '){0,16}\)(?: USING BTREE)?(?: (?:VISIBLE|INVISIBLE)){0,2}' . MYSQL_INDEX_POLICY . '\z/Du', $sql) === 1;
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

function btreeState(PDO $connection, string $table = BTREE_TARGET): ?array
{
    if (mysqlFixtureTableDefinition($connection, $table) === null) {
        return null;
    }
    $rows = $connection->query('SELECT * FROM ' . $table . ' ORDER BY rid')->fetchAll(PDO::FETCH_NUM);
    $indexes = array_map(
        static fn (array $part): array => [$part['Key_name'], $part['Non_unique'], $part['Seq_in_index'],
            $part['Column_name'], $part['Collation'], $part['Null'] === 'YES', $part['Index_type'],
            ($part['Visible'] ?? 'YES') === 'YES', $part['Sub_part']],
        $connection->query('SHOW INDEX FROM ' . $table)->fetchAll(PDO::FETCH_ASSOC)
    );
    usort($indexes, static fn (array $left, array $right): int => [$left[0], $left[2]] <=> [$right[0], $right[2]]);

    return ['rows' => $rows, 'indexes' => $indexes];
}

function btreeResult(array $effect): array
{
    return $effect[0] === 'executed' ? ['executed'] : $effect;
}

function physicalBtreeIndexRoles(PDO $catalog): array
{
    [$indexes, $tables] = str_starts_with($catalog->getAttribute(PDO::ATTR_SERVER_VERSION), '5.')
        ? ['INNODB_SYS_INDEXES', 'INNODB_SYS_TABLES']
        : ['INNODB_INDEXES', 'INNODB_TABLES'];
    return $catalog->query('SELECT i.NAME,i.TYPE FROM information_schema.' . $indexes . ' i JOIN information_schema.' . $tables
        . " t ON i.TABLE_ID=t.TABLE_ID WHERE t.NAME='ir_fixture/ir_btree_subject' ORDER BY BINARY i.NAME")
        ->fetchAll(PDO::FETCH_NUM);
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
    $physicalBefore = $temporary ? null : physicalBtreeIndexRoles($catalog);
    $effect = observeMysqlIndexEffect($catalog, $owner, BTREE_MARKER, $request['sql']);
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

    return ['status' => 'mysql-create-btree', 'initial' => $before, ...$effect,
        'after' => btreeState($owner), 'catalog' => btreeState($catalog), 'write' => $write,
        'physicalRoles' => $temporary ? null : ['before' => $physicalBefore, 'after' => physicalBtreeIndexRoles($catalog)]];
}

if (realpath($argv[0] ?? '') === __FILE__) {
    runMysqlIndexObserver($argv, initBtree(...), observeBtree(...));
}
