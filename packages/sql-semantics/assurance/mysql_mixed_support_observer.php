<?php

declare(strict_types=1);

require_once __DIR__ . '/mysql_index_observation.php';

const MIXED_CHILD = 'ir_fixture.ir_mixed_child';
const MIXED_PARENT = 'ir_mixed_parent';
const MIXED_OTHER = 'ir_mixed_alt';
const MIXED_MARKER = 'ir_fixture.ir_mixed_marker';

function initializeMixedSupport(PDO $catalog): void
{
    $catalog->exec('CREATE DATABASE IF NOT EXISTS ' . MIXED_OTHER);
    $catalog->exec('DROP TABLE IF EXISTS ' . MIXED_MARKER);
    $catalog->exec('CREATE TABLE ' . MIXED_MARKER . ' (n INT NOT NULL) ENGINE=InnoDB');
    $catalog->exec('INSERT INTO ' . MIXED_MARKER . ' VALUES (0)');
}

function admitMixedSupport(array $request): bool
{
    $identifier = '`[A-Za-z][A-Za-z0-9_]{0,63}`';
    $child = '`ir_fixture`\.`ir_mixed_child`';
    $parent = '`(?:ir_fixture|ir_mixed_alt)`\.`ir_mixed_parent`';
    $field = '`(?:id|pid|extra|spare)`';
    $key = $field . '(?: DESC)?';
    $foreign = '`(?:pid|extra)`';
    $target = '`(?:k|n)`';
    $action = '(?:DROP FOREIGN KEY ' . $identifier . '|ADD (?:UNIQUE )?INDEX(?: ' . $identifier . ')? \('
        . $key . '(?:, ' . $key . '){0,3}\)|ADD (?:CONSTRAINT ' . $identifier . ' )?FOREIGN KEY(?: ' . $identifier . ')? \('
        . $foreign . '(?:, ' . $foreign . ')?\) REFERENCES ' . $parent . ' \(' . $target . '(?:, ' . $target . ')?\)'
        . ' ON UPDATE CASCADE ON DELETE CASCADE)';
    return strlen($request['sql']) <= 2400 && preg_match('/\AALTER TABLE ' . $child . ' ' . $action
        . '(?:, ' . $action . '){0,4}\z/D', $request['sql']) === 1
        && in_array($request['parent'], ['ir_fixture', MIXED_OTHER], true) && count($request['declarations']) === 2;
}

function prepareMixedSupport(PDO $catalog, array $request): string
{
    $catalog->exec('DROP TABLE IF EXISTS ' . MIXED_CHILD);
    $catalog->exec('DROP TABLE IF EXISTS ir_fixture.' . MIXED_PARENT . ', ' . MIXED_OTHER . '.' . MIXED_PARENT);
    foreach ($request['declarations'] as $declaration) {
        $catalog->exec($declaration);
    }
    $parent = $request['parent'] . '.' . MIXED_PARENT;
    $catalog->exec('INSERT INTO ' . $parent . ' VALUES (1,11), (2,21), (3,31)');
    $catalog->exec('INSERT INTO ' . MIXED_CHILD . ' VALUES (1,1,11,99), (2,2,21,88), (3,NULL,NULL,99)');
    $catalog->exec('UPDATE ' . MIXED_MARKER . ' SET n=0');

    return $parent;
}

function mixedSupportRows(PDO $catalog): array
{
    return $catalog->query('SELECT id,pid,extra,spare FROM ' . MIXED_CHILD . ' ORDER BY id')->fetchAll(PDO::FETCH_NUM);
}

function mixedSupportCatalog(PDO $catalog): array
{
    $keys = array_map(
        static fn (array $key): array => [$key['Key_name'], $key['Non_unique'], $key['Seq_in_index'],
            $key['Column_name'], $key['Collation'], $key['Index_type']],
        $catalog->query('SHOW INDEX FROM ' . MIXED_CHILD)->fetchAll(PDO::FETCH_ASSOC),
    );
    usort($keys, static fn (array $left, array $right): int => strcmp($left[0], $right[0]) ?: $left[2] <=> $right[2]);
    $table = "TABLE_NAME='ir_mixed_child'";
    $fields = $catalog->query('SELECT CONSTRAINT_NAME,ORDINAL_POSITION,COLUMN_NAME,REFERENCED_TABLE_SCHEMA,'
        . 'REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE '
        . "WHERE TABLE_SCHEMA='ir_fixture' AND " . $table
        . ' AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY BINARY CONSTRAINT_NAME,ORDINAL_POSITION')->fetchAll(PDO::FETCH_NUM);
    $rules = $catalog->query('SELECT CONSTRAINT_NAME,UPDATE_RULE,DELETE_RULE,MATCH_OPTION '
        . "FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA='ir_fixture' AND " . $table
        . ' ORDER BY BINARY CONSTRAINT_NAME')->fetchAll(PDO::FETCH_NUM);

    return ['keys' => $keys, 'fields' => $fields, 'rules' => $rules, 'rows' => mixedSupportRows($catalog)];
}

function mixedSupportWrites(PDO $catalog, string $parent): array
{
    $operations = [
        'update' => 'UPDATE ' . $parent . ' SET k=12 WHERE k=2',
        'delete' => 'DELETE FROM ' . $parent . ' WHERE k=1',
        'duplicate' => 'INSERT INTO ' . MIXED_CHILD . ' VALUES (4,1,11,99)',
        'missing' => 'INSERT INTO ' . MIXED_CHILD . ' VALUES (4,99,21,77)',
        'nullable' => 'INSERT INTO ' . MIXED_CHILD . ' VALUES (4,NULL,NULL,77)',
    ];
    $witnesses = [];
    foreach ($operations as $label => $command) {
        $catalog->beginTransaction();
        try {
            $result = applyMysqlFixtureWrite($catalog, $command);
            $witnesses[$label] = [$result, mixedSupportRows($catalog),
                $catalog->query('SELECT k,n FROM ' . $parent . ' ORDER BY k')->fetchAll(PDO::FETCH_NUM)];
        } finally {
            $catalog->rollBack();
        }
    }

    return $witnesses;
}

function observeMixedSupport(PDO $catalog, PDO $owner, array $request): array
{
    if (!admitMixedSupport($request)) {
        return ['status' => 'outside-observer-scope'];
    }
    $parent = prepareMixedSupport($catalog, $request);
    $before = mixedSupportCatalog($catalog);
    $execution = observeMysqlIndexEffect($catalog, $owner, MIXED_MARKER, $request['sql']);
    $settings = [$catalog->query('SELECT @@foreign_key_checks')->fetchColumn(), $owner->query('SELECT @@foreign_key_checks')->fetchColumn()];

    return ['status' => 'mysql-mixed-support', 'settings' => $settings, 'before' => $before, ...$execution,
        'after' => mixedSupportCatalog($catalog), 'probes' => mixedSupportWrites($catalog, $parent)];
}

runMysqlIndexObserver($argv, initializeMixedSupport(...), observeMixedSupport(...));
