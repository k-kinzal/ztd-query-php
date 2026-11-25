<?php

declare(strict_types=1);

require_once __DIR__ . '/mysql_index_observation.php';

const FK_ADD_CHILD = 'ir_fixture.ir_fk_add_child';
const FK_ADD_PARENT = 'ir_fk_add_parent';
const FK_ADD_AUX = 'ir_fk_add_alt';
const FK_ADD_MARKER = 'ir_fixture.ir_fk_add_marker';
const FK_ADD_CHILD_RX = '`ir_fixture`\.`ir_fk_add_child`';
const FK_ADD_PARENT_RX = '`(?:ir_fixture|ir_fk_add_alt)`\.`ir_fk_add_parent`';

function initForeignKeyAddition(PDO $catalog): void
{
    $catalog->exec('CREATE DATABASE IF NOT EXISTS ' . FK_ADD_AUX);
    $catalog->exec('DROP TABLE IF EXISTS ' . FK_ADD_MARKER);
    $catalog->exec('CREATE TABLE ' . FK_ADD_MARKER . ' (n INT NOT NULL) ENGINE=InnoDB');
    $catalog->exec('INSERT INTO ' . FK_ADD_MARKER . ' VALUES (0)');
}

function admitForeignKeyAddition(array $request): bool
{
    if (!in_array($request['scope'], ['same', 'cross', 'parent-shadow', 'child-database'], true) || !is_bool($request['wrongRows'])
        || count($request['declarations']) !== (in_array($request['scope'], ['parent-shadow', 'child-database'], true) ? 3 : 2)) {
        return false;
    }
    $parent = '/\ACREATE TABLE ' . FK_ADD_PARENT_RX . ' \(a INT NOT NULL, b INT NOT NULL(?:, (?:'
        . 'PRIMARY KEY \(b,a\), UNIQUE KEY by_a \(a\), UNIQUE KEY by_b \(b\)'
        . '|KEY by_a \(a\), KEY by_b \(b\), KEY by_b_a \(b,a\)))?\) ENGINE=InnoDB\z/D';
    $field = '`[xy]`(?: DESC)?';
    $keys = '(?:, KEY `cover_[12]` \(' . $field . '(?:, ' . $field . ')?\)){0,2}';
    $child = '/\ACREATE TABLE ' . FK_ADD_CHILD_RX . ' \(x INT(?: NOT NULL)?, y INT(?: NOT NULL)?, z INT PRIMARY KEY' . $keys . '\) ENGINE=InnoDB\z/D';
    $ddl = $request['declarations'];
    if (preg_match($parent, $ddl[0]) !== 1 || preg_match($child, $ddl[1]) !== 1) {
        return false;
    }
    $shadow = 'CREATE TEMPORARY TABLE `ir_fixture`.`ir_fk_add_parent` (wrong VARCHAR(9) PRIMARY KEY) ENGINE=InnoDB';
    $decoy = 'CREATE TABLE `ir_fk_add_alt`.`ir_fk_add_parent` (a INT PRIMARY KEY, b INT) ENGINE=InnoDB';
    if (($request['scope'] === 'parent-shadow' && $ddl[2] !== $shadow) || ($request['scope'] === 'child-database' && $ddl[2] !== $decoy)) {
        return false;
    }

    $label = '`(?:Fk|key)_[12]_[0-9]{1,2}`';
    $targets = '`[ab]`(?:, `[ab]`)?';
    $effects = '(?:RESTRICT|NO ACTION|CASCADE|SET NULL|SET DEFAULT)';
    $reference = '(?:' . FK_ADD_PARENT_RX . '|`ir_fk_add_parent`) \(' . $targets . '\)';
    $action = 'ADD (?:CONSTRAINT ' . $label . ' )?FOREIGN KEY (?:' . $label . ' )?\(' . $field . '(?:, ' . $field . ')?\) REFERENCES '
        . $reference . '(?: MATCH (?:SIMPLE|FULL|PARTIAL))?(?: ON UPDATE ' . $effects . ')?(?: ON DELETE ' . $effects . ')?';
    $policy = '(?:, ALGORITHM=(?:DEFAULT|COPY|INPLACE))?(?:, LOCK=(?:DEFAULT|NONE|SHARED|EXCLUSIVE))?';

    return preg_match('/\AALTER (?:IGNORE )?TABLE ' . FK_ADD_CHILD_RX . ' ' . $action . '(?:, ' . $action . ')?' . $policy . '\z/D', $request['sql']) === 1;
}

function resetForeignKeyAddition(PDO $catalog, PDO $owner, array $request): string
{
    $parent = ($request['scope'] === 'cross' ? FK_ADD_AUX : 'ir_fixture') . '.' . FK_ADD_PARENT;
    $owner->exec('DROP TEMPORARY TABLE IF EXISTS ir_fixture.ir_fk_add_parent');
    $catalog->exec('DROP TABLE IF EXISTS ' . FK_ADD_CHILD);
    $catalog->exec('DROP TABLE IF EXISTS ir_fixture.ir_fk_add_parent, ir_fk_add_alt.ir_fk_add_parent');
    foreach ($request['declarations'] as $statement) {
        (str_starts_with($statement, 'CREATE TEMPORARY') ? $owner : $catalog)->exec($statement);
    }
    $catalog->exec('INSERT INTO ' . $parent . ' VALUES (7,11), (13,19)');
    if ($request['scope'] === 'child-database') {
        $catalog->exec('INSERT INTO ir_fk_add_alt.ir_fk_add_parent VALUES (97,99)');
    }
    $catalog->exec('INSERT INTO ' . FK_ADD_CHILD . ($request['wrongRows'] ? ' VALUES (87,89,31)' : ' VALUES (7,11,31)'));
    $catalog->exec('UPDATE ' . FK_ADD_MARKER . ' SET n=0');
    $owner->exec($request['scope'] === 'child-database' ? 'USE ir_fk_add_alt' : 'USE ir_fixture');

    return $parent;
}

function foreignAdditionIndexes(PDO $catalog): array
{
    $indexes = [];
    foreach ($catalog->query('SHOW INDEX FROM ' . FK_ADD_CHILD)->fetchAll(PDO::FETCH_ASSOC) as $key) {
        $indexes[] = [$key['Key_name'], $key['Non_unique'], $key['Seq_in_index'], $key['Column_name'], $key['Collation'], $key['Index_type']];
    }
    usort($indexes, static fn (array $a, array $b): int => strcmp($a[0], $b[0]) ?: $a[2] <=> $b[2]);

    return $indexes;
}

function foreignAdditionRows(PDO $catalog): array
{
    return $catalog->query('SELECT x,y,z FROM ' . FK_ADD_CHILD . ' ORDER BY z')->fetchAll(PDO::FETCH_NUM);
}

function foreignAdditionProbes(PDO $catalog, string $parent): array
{
    $writes = [
        'update' => 'UPDATE ' . $parent . ' SET a=47, b=53 WHERE a=7',
        'delete' => 'DELETE FROM ' . $parent . ' WHERE a=7',
        'unmatched' => 'INSERT INTO ' . FK_ADD_CHILD . ' VALUES (87,89,98)',
        'null' => 'INSERT INTO ' . FK_ADD_CHILD . ' VALUES (NULL,NULL,99)',
        'partial' => 'INSERT INTO ' . FK_ADD_CHILD . ' VALUES (NULL,89,100)',
    ];
    $suite = [];
    foreach ($writes as $action => $sql) {
        $catalog->beginTransaction();
        try {
            $effect = applyMysqlFixtureWrite($catalog, $sql);
            $suite[$action] = $action === 'update' || $action === 'delete'
                ? [$effect, $catalog->query('SELECT a,b FROM ' . $parent . ' ORDER BY a')->fetchAll(PDO::FETCH_NUM), foreignAdditionRows($catalog)]
                : $effect;
        } finally {
            $catalog->rollBack();
        }
    }

    return $suite;
}

function observeForeignKeyAddition(PDO $catalog, PDO $owner, array $request): array
{
    if (!admitForeignKeyAddition($request)) {
        return ['status' => 'outside-observer-scope'];
    }
    $parent = resetForeignKeyAddition($catalog, $owner, $request);
    $before = foreignAdditionIndexes($catalog);
    $profile = [$catalog->query('SELECT @@foreign_key_checks')->fetchColumn(), $owner->query('SELECT @@foreign_key_checks')->fetchColumn()];
    $execution = observeMysqlIndexEffect($catalog, $owner, FK_ADD_MARKER, $request['sql']);
    $references = $catalog->query("SELECT CONSTRAINT_NAME,ORDINAL_POSITION,COLUMN_NAME,REFERENCED_TABLE_SCHEMA,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME "
        . "FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA='ir_fixture' AND TABLE_NAME='ir_fk_add_child' "
        . "AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY BINARY CONSTRAINT_NAME,ORDINAL_POSITION")->fetchAll(PDO::FETCH_NUM);
    $rules = $catalog->query("SELECT CONSTRAINT_NAME,UPDATE_RULE,DELETE_RULE,MATCH_OPTION FROM information_schema.REFERENTIAL_CONSTRAINTS "
        . "WHERE CONSTRAINT_SCHEMA='ir_fixture' AND TABLE_NAME='ir_fk_add_child' ORDER BY BINARY CONSTRAINT_NAME")->fetchAll(PDO::FETCH_NUM);

    return ['status' => 'mysql-add-foreign-keys', 'indexesBefore' => $before, 'indexesAfter' => foreignAdditionIndexes($catalog),
        'references' => $references, 'rules' => $rules, 'settings' => $profile, ...$execution, 'childRows' => foreignAdditionRows($catalog),
        'probes' => $execution['effect'] === ['executed'] ? foreignAdditionProbes($catalog, $parent) : null];
}

runMysqlIndexObserver($argv, initForeignKeyAddition(...), observeForeignKeyAddition(...));
