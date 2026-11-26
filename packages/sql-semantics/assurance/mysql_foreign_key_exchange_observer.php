<?php

declare(strict_types=1);

require_once __DIR__ . '/mysql_index_observation.php';

const FK_EX_CHILD = 'ir_fixture.ir_fk_exchange_child';
const FK_EX_SOURCE = 'ir_fixture.ir_fk_exchange_child_source';
const FK_EX_PARENT = 'ir_fk_exchange_parent';
const FK_EX_OTHER = 'ir_fk_exchange_alt';
const FK_EX_MARKER = 'ir_fixture.ir_fk_exchange_marker';

function initializeForeignKeyExchange(PDO $catalog): void
{
    $catalog->exec('CREATE DATABASE IF NOT EXISTS ' . FK_EX_OTHER);
    $catalog->exec('DROP TABLE IF EXISTS ' . FK_EX_MARKER);
    $catalog->exec('CREATE TABLE ' . FK_EX_MARKER . ' (n INT NOT NULL) ENGINE=InnoDB');
    $catalog->exec('INSERT INTO ' . FK_EX_MARKER . ' VALUES (0)');
}

function admitForeignKeyExchange(array $request): bool
{
    $identifier = '`[A-Za-z][A-Za-z0-9_]{0,63}`';
    $child = '`ir_fixture`\.`' . 'ir_fk_exchange_child' . '`';
    $parent = '`(?:ir_fixture|ir_fk_exchange_alt)`\.`ir_fk_exchange_parent`';
    $fields = '`(?:pid|code_id|peer_id|spare_id|tag_id)`';
    $targets = '`(?:id|code)`';
    $action = '(?:DROP FOREIGN KEY ' . $identifier . '|ADD (?:CONSTRAINT ' . $identifier . ' )?FOREIGN KEY \('
        . $fields . '(?:, ' . $fields . ')?\) REFERENCES ' . $parent . ' \(' . $targets . '(?:, ' . $targets . ')?\)'
        . ' ON UPDATE (?:CASCADE|SET NULL) ON DELETE (?:CASCADE|SET NULL))';
    $policy = '(?:, ALGORITHM=(?:DEFAULT|COPY|INPLACE))?(?:, LOCK=(?:DEFAULT|NONE|SHARED|EXCLUSIVE))?';
    return strlen($request['sql']) <= 4000 && preg_match('/\AALTER TABLE ' . $child . ' ' . $action
        . '(?:, ' . $action . '){0,5}' . $policy . '\z/D', $request['sql']) === 1
        && in_array($request['parent'], ['ir_fixture', FK_EX_OTHER], true) && is_bool($request['partial'])
        && is_int($request['padding']) && $request['padding'] >= 0 && $request['padding'] <= 62
        && in_array(count($request['declarations']), [2, 3], true);
}

function prepareForeignKeyExchange(PDO $catalog, array $request): string
{
    $parent = $request['parent'] . '.' . FK_EX_PARENT;
    $catalog->exec('DROP TABLE IF EXISTS ' . FK_EX_CHILD . ', ' . FK_EX_SOURCE);
    $catalog->exec('DROP TABLE IF EXISTS ir_fixture.' . FK_EX_PARENT . ', ' . FK_EX_OTHER . '.' . FK_EX_PARENT);
    // The independent fixture plan supplies these declarations. Only the checked statement enters through the candidate.
    foreach ($request['declarations'] as $declaration) {
        $catalog->exec($declaration);
    }
    $catalog->exec('INSERT INTO ' . $parent . ' VALUES (1,10), (2,20)');
    $columns = ' (rid,pid,code_id,peer_id,spare_id,tag_id)';
    $peer = $request['partial'] ? 'NULL' : '1';
    $catalog->exec('INSERT INTO ' . FK_EX_CHILD . $columns . ' VALUES (1,1,10,' . $peer . ',2,2), (2,NULL,NULL,NULL,NULL,NULL)');
    $catalog->exec('UPDATE ' . FK_EX_MARKER . ' SET n=0');

    return $parent;
}

function exchangeRows(PDO $catalog): array
{
    return $catalog->query('SELECT rid,pid,code_id,peer_id,spare_id,tag_id FROM ' . FK_EX_CHILD . ' ORDER BY rid')
        ->fetchAll(PDO::FETCH_NUM);
}

function foreignKeyExchangeCatalog(PDO $catalog): array
{
    $keys = array_map(
        static fn (array $key): array => [$key['Key_name'], $key['Non_unique'], $key['Seq_in_index'],
            $key['Column_name'], $key['Collation'], $key['Index_type']],
        $catalog->query('SHOW INDEX FROM ' . FK_EX_CHILD)->fetchAll(PDO::FETCH_ASSOC),
    );
    usort($keys, static fn (array $left, array $right): int => strcmp($left[0], $right[0]) ?: $left[2] <=> $right[2]);
    $tables = "TABLE_NAME='ir_fk_exchange_child'";
    $fields = $catalog->query('SELECT CONSTRAINT_NAME,ORDINAL_POSITION,COLUMN_NAME,REFERENCED_TABLE_SCHEMA,'
        . 'REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE '
        . "WHERE TABLE_SCHEMA='ir_fixture' AND " . $tables
        . ' AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY BINARY CONSTRAINT_NAME,ORDINAL_POSITION')->fetchAll(PDO::FETCH_NUM);
    $rules = $catalog->query('SELECT CONSTRAINT_NAME,UPDATE_RULE,DELETE_RULE,MATCH_OPTION '
        . "FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA='ir_fixture' AND " . $tables
        . ' ORDER BY BINARY CONSTRAINT_NAME')->fetchAll(PDO::FETCH_NUM);

    return ['keys' => $keys, 'fields' => $fields, 'rules' => $rules, 'rows' => exchangeRows($catalog)];
}

function foreignKeyExchangeEffects(PDO $catalog, string $parent): array
{
    $queries = [
        'update-primary' => 'UPDATE ' . $parent . ' SET code=11 WHERE id=1',
        'delete-primary' => 'DELETE FROM ' . $parent . ' WHERE id=1',
        'delete-secondary' => 'DELETE FROM ' . $parent . ' WHERE id=2',
        'removed-reference' => 'INSERT INTO ' . FK_EX_CHILD
            . ' (rid,pid,code_id,peer_id,spare_id,tag_id) VALUES (91,99,NULL,NULL,NULL,NULL)',
        'added-reference' => 'INSERT INTO ' . FK_EX_CHILD
            . ' (rid,pid,code_id,peer_id,spare_id,tag_id) VALUES (92,NULL,99,1,NULL,NULL)',
        'nil-reference' => 'INSERT INTO ' . FK_EX_CHILD
            . ' (rid,pid,code_id,peer_id,spare_id,tag_id) VALUES (93,NULL,NULL,NULL,NULL,NULL)',
    ];
    $effects = [];
    foreach ($queries as $label => $query) {
        $catalog->beginTransaction();
        try {
            $result = applyMysqlFixtureWrite($catalog, $query);
            $effects[$label] = [$result, exchangeRows($catalog),
                $catalog->query('SELECT id,code FROM ' . $parent . ' ORDER BY id')->fetchAll(PDO::FETCH_NUM)];
        } finally {
            $catalog->rollBack();
        }
    }

    return $effects;
}

function observeForeignKeyExchange(PDO $catalog, PDO $owner, array $request): array
{
    if (!admitForeignKeyExchange($request)) {
        return ['status' => 'outside-observer-scope'];
    }
    $parent = prepareForeignKeyExchange($catalog, $request);
    $initial = foreignKeyExchangeCatalog($catalog);
    $execution = observeMysqlIndexEffect($catalog, $owner, FK_EX_MARKER, $request['sql']);
    $settings = [$catalog->query('SELECT @@foreign_key_checks')->fetchColumn(), $owner->query('SELECT @@foreign_key_checks')->fetchColumn()];

    return ['status' => 'mysql-foreign-key-exchange', 'before' => $initial, 'settings' => $settings, ...$execution,
        'after' => foreignKeyExchangeCatalog($catalog), 'probes' => foreignKeyExchangeEffects($catalog, $parent)];
}

runMysqlIndexObserver($argv, initializeForeignKeyExchange(...), observeForeignKeyExchange(...));
