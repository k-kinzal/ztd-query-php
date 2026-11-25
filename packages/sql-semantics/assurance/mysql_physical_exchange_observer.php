<?php

declare(strict_types=1);

require_once __DIR__ . '/mysql_index_observation.php';

const PHYSICAL_EXCHANGE_TABLES = ['affected', 'reference_values', 'fk_test_child', 'fk_test_parent', 'fk_test_root'];
const PHYSICAL_EXCHANGE_MARKER = 'ir_fixture.ir_exchange_marker';

function initializePhysicalExchange(PDO $catalog): void
{
    $catalog->exec('DROP TABLE IF EXISTS ' . PHYSICAL_EXCHANGE_MARKER);
    $catalog->exec('CREATE TABLE ' . PHYSICAL_EXCHANGE_MARKER . ' (n INT NOT NULL) ENGINE=InnoDB');
    $catalog->exec('INSERT INTO ' . PHYSICAL_EXCHANGE_MARKER . ' VALUES (0)');
}

/** Admits the fixture field and key clauses emitted by the fixed case catalog. */
function admissiblePhysicalExchange(string $sql): bool
{
    $name = '(?:`[A-Za-z_][A-Za-z_0-9]{0,63}`|[A-Za-z_][A-Za-z_0-9]{0,63})';
    $table = '(?:`ir_fixture`\.)?(?:`?(?:' . implode('|', PHYSICAL_EXCHANGE_TABLES) . ')`?)';
    $term = $name . '(?: DESC)?';
    $list = '\( *' . $term . '(?: *, *' . $term . '){0,3} *\)';
    $edge = 'ADD CONSTRAINT ' . $name . ' FOREIGN KEY *' . $list . ' REFERENCES ' . $table
        . ' *' . $list . '(?: ON UPDATE (?:CASCADE|NO ACTION|RESTRICT) ON DELETE (?:CASCADE|NO ACTION|RESTRICT))?';
    $key = 'ADD (?:UNIQUE )?(?:KEY|INDEX) ' . $name . ' *' . $list;
    $drop = 'DROP (?:(?:FOREIGN KEY|KEY|INDEX) ' . $name . '|PRIMARY KEY)';
    $action = '(?:' . $edge . '|' . $key . '|' . $drop . ')';

    return strlen($sql) <= 2500 && preg_match('/\AALTER TABLE ' . $table . ' ' . $action . '(?:, ' . $action . '){1,7}\z/D', $sql) === 1;
}

/** Rebuilds the same isolated data state before each reference or rendered statement. */
function preparePhysicalExchange(PDO $catalog, array $request): array
{
    $catalog->exec('SET FOREIGN_KEY_CHECKS=0');
    try {
        $catalog->exec('DROP TABLE IF EXISTS ' . implode(', ', PHYSICAL_EXCHANGE_TABLES));
    } finally {
        $catalog->exec('SET FOREIGN_KEY_CHECKS=1');
    }
    $tables = [];
    foreach ($request['declarations'] as $declaration) {
        if (preg_match('/\ACREATE TABLE ([a-z_]+) \([a-zA-Z_0-9 ,()]+\) ENGINE=InnoDB\z/D', $declaration, $parts) !== 1
            || !in_array($parts[1], PHYSICAL_EXCHANGE_TABLES, true) || isset($tables[$parts[1]])) {
            throw new RuntimeException('Invalid fixed physical exchange declaration.');
        }
        $tables[$parts[1]] = true;
        $catalog->exec($declaration);
    }
    foreach ($request['rows'] as $row) {
        if (preg_match('/\AINSERT INTO ([a-z_]+)(?:\([a-z,]+\))? VALUES [A-Z0-9,() ]+\z/D', $row, $parts) !== 1 || !isset($tables[$parts[1]])) {
            throw new RuntimeException('Invalid fixed physical exchange rows.');
        }
        $catalog->exec($row);
    }
    $catalog->exec('UPDATE ' . PHYSICAL_EXCHANGE_MARKER . ' SET n=0');

    return array_keys($tables);
}

/** Captures exact dictionary relationships, the generating counter and all affected rows. */
function physicalExchangeState(PDO $catalog, array $tables): array
{
    $owners = [];
    foreach ($tables as $table) {
        $fields = $catalog->query('SHOW COLUMNS FROM `' . $table . '`')->fetchAll(PDO::FETCH_ASSOC);
        $slots = range(1, count($fields));
        $keys = array_map(
            static fn (array $key): array => [$key['Key_name'], $key['Non_unique'], $key['Seq_in_index'], $key['Column_name'],
                $key['Collation'], $key['Sub_part'], $key['Index_type']],
            $catalog->query('SHOW INDEX FROM `' . $table . '`')->fetchAll(PDO::FETCH_ASSOC),
        );
        usort($keys, static fn (array $left, array $right): int => strcmp($left[0], $right[0]) ?: $left[2] <=> $right[2]);
        $owners[$table] = [
            'columns' => $fields,
            'keys' => $keys,
            'rows' => $catalog->query('SELECT * FROM `' . $table . '` ORDER BY ' . implode(',', $slots))->fetchAll(PDO::FETCH_NUM),
            'next' => $catalog->query("SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA='ir_fixture'"
                . " AND TABLE_NAME='$table'")->fetchColumn(),
        ];
    }
    $constraints = $catalog->query('SELECT TABLE_NAME, CONSTRAINT_NAME, ORDINAL_POSITION, COLUMN_NAME, REFERENCED_TABLE_SCHEMA,'
        . ' REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE'
        . " WHERE TABLE_SCHEMA='ir_fixture' AND REFERENCED_TABLE_NAME IS NOT NULL "
        . 'ORDER BY BINARY TABLE_NAME, BINARY CONSTRAINT_NAME, ORDINAL_POSITION')->fetchAll(PDO::FETCH_NUM);
    $rules = $catalog->query('SELECT TABLE_NAME, CONSTRAINT_NAME, UPDATE_RULE, DELETE_RULE, MATCH_OPTION '
        . "FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA='ir_fixture' "
        . 'ORDER BY BINARY TABLE_NAME, BINARY CONSTRAINT_NAME')->fetchAll(PDO::FETCH_NUM);

    return ['tables' => $owners, 'constraints' => $constraints, 'rules' => $rules];
}

function physicalExchangeStatement(PDO $catalog, PDO $owner, array $request, string $sql): array
{
    $tables = preparePhysicalExchange($catalog, $request);
    $before = physicalExchangeState($catalog, $tables);
    $effect = observeMysqlIndexEffect($catalog, $owner, PHYSICAL_EXCHANGE_MARKER, $sql);
    $after = physicalExchangeState($catalog, $tables);
    $probe = $request['probe'];
    if (preg_match('/\A(?:UPDATE ([a-z_]+) SET [a-z_]+=[a-z_+0-9]+ WHERE [a-z_]+=[0-9]+|INSERT INTO affected\(id,pid\) VALUES \(33,3\))\z/D', $probe, $parts) !== 1
        || isset($parts[1]) && !in_array($parts[1], $tables, true)) {
        throw new RuntimeException('Invalid fixed physical exchange write.');
    }
    $probed = applyMysqlFixtureWrite($catalog, $probe);
    $follow = mysqlFixtureDiagnostics($catalog)['diagnostics'];

    return ['before' => $before, ...$effect,
        'after' => $after, 'probe' => $probed, 'probeDiagnostics' => $follow, 'result' => physicalExchangeState($catalog, $tables)];
}

function observePhysicalExchange(PDO $catalog, PDO $owner, array $request): array
{
    $sql = $request['sql'];
    $referenceSql = $request['referenceSql'];
    if (!admissiblePhysicalExchange($sql) || !admissiblePhysicalExchange($referenceSql)) {
        return ['status' => 'outside-observer-scope'];
    }
    $observed = physicalExchangeStatement($catalog, $owner, $request, $sql);
    $reference = $sql === $referenceSql ? $observed : physicalExchangeStatement($catalog, $owner, $request, $referenceSql);

    return ['status' => 'mysql-physical-exchange', 'observed' => $observed, 'reference' => $reference];
}

runMysqlIndexObserver($argv, initializePhysicalExchange(...), observePhysicalExchange(...));
