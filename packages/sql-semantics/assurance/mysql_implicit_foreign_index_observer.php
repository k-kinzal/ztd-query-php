<?php

declare(strict_types=1);

require_once __DIR__ . '/mysql_index_observation.php';

const IMPLICIT_FK_PARENT = 'ir_fixture.ir_implicit_p';
const IMPLICIT_FK_CHILD = 'ir_fixture.ir_implicit_c';
const IMPLICIT_FK_COPY = 'ir_fixture.ir_implicit_copy';
const IMPLICIT_FK_MARKER = 'ir_fixture.ir_implicit_marker';
const IMPLICIT_FK_P_RX = '\x60ir_fixture\x60\.\x60ir_implicit_p\x60';
const IMPLICIT_FK_C_RX = '\x60ir_fixture\x60\.\x60ir_implicit_c\x60';
const IMPLICIT_FK_O_RX = '\x60ir_fixture\x60\.\x60ir_implicit_copy\x60';
const IMPLICIT_FK_I_RX = '\x60(?:[XY]|protect_y|(?:symbol|written|alternate)_[0-9]{1,2})\x60';

function initializeImplicitForeignIndexes(PDO $catalog): void
{
    $catalog->exec('DROP TABLE IF EXISTS ' . IMPLICIT_FK_MARKER);
    $catalog->exec('CREATE TABLE ' . IMPLICIT_FK_MARKER . ' (n INT NOT NULL) ENGINE=InnoDB');
    $catalog->exec('INSERT INTO ' . IMPLICIT_FK_MARKER . ' VALUES (0)');
}

function admitImplicitForeignIndex(array $request): bool
{
    $ddl = $request['declarations'];
    if (count($ddl) !== 3
        || preg_match('/\ACREATE TABLE ' . IMPLICIT_FK_P_RX . ' \(a INT NOT NULL PRIMARY KEY\) ENGINE=InnoDB\z/D', $ddl[0]) !== 1
        || preg_match('/\ACREATE TABLE ' . IMPLICIT_FK_O_RX . ' LIKE ' . IMPLICIT_FK_C_RX . '\z/D', $ddl[2]) !== 1) {
        return false;
    }
    $field = '\x60[XY]\x60';
    $explicit = ', KEY \x60alternate_[0-9]{1,2}\x60 \(' . $field . '(?: DESC|, ' . $field . ')?\)';
    $constraint = ', (?:CONSTRAINT \x60symbol_[0-9]{1,2}\x60 )?FOREIGN KEY(?: \x60written_[0-9]{1,2}\x60)? \(' . $field
        . '\) REFERENCES ' . IMPLICIT_FK_P_RX . ' \(\x60a\x60\)';
    $columns = '\(\x60X\x60 INT NOT NULL, \x60Y\x60 INT NOT NULL, z INT NOT NULL, PRIMARY KEY \(z\), KEY \x60protect_y\x60 \(' . $field . '\)';
    $child = '/\ACREATE TABLE ' . IMPLICIT_FK_C_RX . ' ' . $columns . '(?:' . $explicit . ')?' . $constraint . '(?:' . $explicit . ')?\) ENGINE=InnoDB\z/Di';
    if (preg_match($child, $ddl[1]) !== 1) {
        return false;
    }
    $drop = 'DROP INDEX ' . IMPLICIT_FK_I_RX . ' ON (?:' . IMPLICIT_FK_C_RX . '|' . IMPLICIT_FK_O_RX . ')';
    $hide = 'ALTER TABLE ' . IMPLICIT_FK_C_RX . ' ALTER INDEX ' . IMPLICIT_FK_I_RX . ' INVISIBLE';

    return preg_match('/\A(?:' . $drop . '|' . $hide . ')\z/Di', $request['sql']) === 1;
}

function resetImplicitForeignIndexes(PDO $catalog, array $request): void
{
    foreach ([IMPLICIT_FK_COPY, IMPLICIT_FK_CHILD, IMPLICIT_FK_PARENT] as $table) {
        $catalog->exec('DROP TABLE IF EXISTS ' . $table);
    }
    foreach ($request['declarations'] as $sql) {
        $catalog->exec($sql);
    }
    $catalog->exec('INSERT INTO ' . IMPLICIT_FK_PARENT . ' VALUES (7)');
    $catalog->exec('INSERT INTO ' . IMPLICIT_FK_CHILD . ' VALUES (7,7,31)');
    $catalog->exec('INSERT INTO ' . IMPLICIT_FK_COPY . ' VALUES (7,7,47)');
    $catalog->exec('UPDATE ' . IMPLICIT_FK_MARKER . ' SET n=0');
}

function readImplicitForeignIndexKeys(PDO $catalog, string $table): array
{
    $rows = [];
    foreach ($catalog->query('SHOW INDEX FROM ' . $table)->fetchAll(PDO::FETCH_ASSOC) as $term) {
        $rows[] = [$term['Key_name'], $term['Non_unique'], $term['Seq_in_index'], $term['Column_name'],
                   $term['Collation'], $term['Sub_part'], $term['Index_type'], $term['Visible'] ?? null];
    }
    usort($rows, static fn (array $a, array $b): int => strcmp($a[0], $b[0]) ?: $a[2] <=> $b[2]);

    return $rows;
}

function readImplicitForeignIndexEdges(PDO $catalog, string $relation): array
{
    $read = $catalog->prepare("SELECT CONSTRAINT_NAME,ORDINAL_POSITION,COLUMN_NAME,REFERENCED_TABLE_SCHEMA,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME "
        . "FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA='ir_fixture' AND TABLE_NAME=? "
        . "AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY CONSTRAINT_NAME,ORDINAL_POSITION");
    $read->execute([$relation]);

    return $read->fetchAll(PDO::FETCH_NUM);
}

function snapshotImplicitForeignIndexes(PDO $catalog): array
{
    return [
        'source' => readImplicitForeignIndexKeys($catalog, IMPLICIT_FK_CHILD),
        'copy' => readImplicitForeignIndexKeys($catalog, IMPLICIT_FK_COPY),
        'foreignKeys' => readImplicitForeignIndexEdges($catalog, 'ir_implicit_c'),
        'copiedForeignKeys' => readImplicitForeignIndexEdges($catalog, 'ir_implicit_copy'),
    ];
}

function observeImplicitForeignIndexes(PDO $catalog, PDO $owner, array $request): array
{
    if (!admitImplicitForeignIndex($request)) {
        return ['status' => 'outside-observer-scope'];
    }
    resetImplicitForeignIndexes($catalog, $request);
    $checks = $owner->query('SELECT @@foreign_key_checks')->fetchColumn();
    $before = snapshotImplicitForeignIndexes($catalog);
    $effect = observeMysqlIndexEffect($catalog, $owner, IMPLICIT_FK_MARKER, $request['sql']);
    $after = snapshotImplicitForeignIndexes($catalog);
    $enforcement = applyMysqlFixtureWrite($owner, 'INSERT INTO ' . IMPLICIT_FK_CHILD . ' VALUES (99,99,69)');

    return ['status' => 'mysql-implicit-foreign-index', 'before' => $before, 'after' => $after, ...$effect,
            'foreign_key_checks' => $checks, 'foreignKeyEnforcement' => $enforcement,
            'rows' => [
                'parent' => $catalog->query('SELECT a FROM ' . IMPLICIT_FK_PARENT . ' ORDER BY a')->fetchAll(PDO::FETCH_NUM),
                'source' => $catalog->query('SELECT X,Y,z FROM ' . IMPLICIT_FK_CHILD . ' ORDER BY z')->fetchAll(PDO::FETCH_NUM),
                'copy' => $catalog->query('SELECT X,Y,z FROM ' . IMPLICIT_FK_COPY . ' ORDER BY z')->fetchAll(PDO::FETCH_NUM),
            ]];
}

runMysqlIndexObserver($argv, initializeImplicitForeignIndexes(...), observeImplicitForeignIndexes(...));
