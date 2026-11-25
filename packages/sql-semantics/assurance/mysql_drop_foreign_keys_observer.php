<?php

declare(strict_types=1);

require_once __DIR__ . '/mysql_index_observation.php';

const REMOVAL_FK_CHILD = 'ir_fixture.ir_rc_child';
const REMOVAL_FK_PARENT = 'ir_fixture.ir_rc_parent';
const REMOVAL_FK_INCOMING = 'ir_rc_aux.ir_rc_other';
const REMOVAL_FK_MARKER = 'ir_fixture.ir_rc_marker';
const REMOVAL_FK_C_RX = '\x60ir_fixture\x60\.\x60ir_rc_child\x60';
const REMOVAL_FK_P_RX = '\x60ir_fixture\x60\.\x60ir_rc_parent\x60';
const REMOVAL_FK_O_RX = '\x60ir_rc_aux\x60\.\x60ir_rc_other\x60';
const REMOVAL_FK_LABEL = '\x60(?:Fk_[AB][0-9]{1,2}|ir_rc_child_ibfk_[12]|missing_key|A |a\x60\x60b|é{64}|a{255})?\x60';
const REMOVAL_FK_INDEX = '\x60by_[xy][0-9]{1,2}\x60';

function initForeignKeyRemoval(PDO $catalog): void
{
    $catalog->exec('CREATE DATABASE IF NOT EXISTS ir_rc_aux');
    $catalog->exec('DROP TABLE IF EXISTS ' . REMOVAL_FK_MARKER);
    $catalog->exec('CREATE TABLE ' . REMOVAL_FK_MARKER . ' (n INT NOT NULL) ENGINE=InnoDB');
    $catalog->exec('INSERT INTO ' . REMOVAL_FK_MARKER . ' VALUES (0)');
}

function admitForeignKeyRemoval(array $request): bool
{
    if (!is_bool($request['temporary']) || !is_bool($request['incoming'])
        || count($request['declarations']) !== 2 + (int) $request['temporary'] + (int) $request['incoming']) {
        return false;
    }
    $ddl = $request['declarations'];
    $parent = '/\ACREATE TABLE ' . REMOVAL_FK_P_RX . ' \(a INT PRIMARY KEY, b INT NOT NULL UNIQUE\) ENGINE=InnoDB\z/Du';
    $fields = '\(x INT NOT NULL, y INT NOT NULL, z INT PRIMARY KEY';
    $keys = ', (?:UNIQUE )?KEY ' . REMOVAL_FK_INDEX . ' \(x\), KEY ' . REMOVAL_FK_INDEX . ' \(y\)';
    $foreign = ', (?:CONSTRAINT ' . REMOVAL_FK_LABEL . ' )?FOREIGN KEY \([xy]\) REFERENCES ' . REMOVAL_FK_P_RX . ' \([ab]\)';
    $child = '/\ACREATE TABLE ' . REMOVAL_FK_C_RX . ' ' . $fields . $keys . '(?:' . $foreign . '){2}\) ENGINE=InnoDB\z/Du';
    if (preg_match($parent, $ddl[0]) !== 1 || preg_match($child, $ddl[1]) !== 1) {
        return false;
    }
    if ($request['incoming']) {
        $other = '/\ACREATE TABLE ' . REMOVAL_FK_O_RX . ' \(w INT, KEY \x60supports_w\x60 \(w\), CONSTRAINT '
            . REMOVAL_FK_LABEL . ' FOREIGN KEY \(w\) REFERENCES ' . REMOVAL_FK_C_RX . ' \(x\)\) ENGINE=InnoDB\z/Du';
        if (preg_match($other, $ddl[2]) !== 1) {
            return false;
        }
    }
    $temporary = '/\ACREATE TEMPORARY TABLE ' . REMOVAL_FK_C_RX . ' ' . $fields . $keys . '\) ENGINE=InnoDB\z/Du';
    if ($request['temporary'] && preg_match($temporary, $ddl[count($ddl) - 1]) !== 1) {
        return false;
    }
    $operand = '(?:DROP FOREIGN KEY ' . REMOVAL_FK_LABEL . '|DROP (?:INDEX|KEY) ' . REMOVAL_FK_INDEX
        . '|ALGORITHM=(?:DEFAULT|COPY|INPLACE|INSTANT)|LOCK=(?:DEFAULT|NONE|SHARED|EXCLUSIVE))';

    return preg_match('/\AALTER (?:IGNORE )?TABLE ' . REMOVAL_FK_C_RX . ' ' . $operand . '(?:, ' . $operand . '){0,8}\z/Diu', $request['sql']) === 1;
}

function resetForeignKeyRemoval(PDO $catalog, PDO $owner, array $request): void
{
    $owner->exec('DROP TEMPORARY TABLE IF EXISTS ' . REMOVAL_FK_CHILD);
    foreach ([REMOVAL_FK_INCOMING, REMOVAL_FK_CHILD, REMOVAL_FK_PARENT] as $table) {
        $catalog->exec('DROP TABLE IF EXISTS ' . $table);
    }
    foreach ($request['declarations'] as $sql) {
        (str_starts_with($sql, 'CREATE TEMPORARY') ? $owner : $catalog)->exec($sql);
    }
    $catalog->exec('INSERT INTO ' . REMOVAL_FK_PARENT . ' VALUES (7,11), (13,19)');
    $catalog->exec('INSERT INTO ' . REMOVAL_FK_CHILD . ' VALUES (7,11,31)');
    if ($request['incoming']) {
        $catalog->exec('INSERT INTO ' . REMOVAL_FK_INCOMING . ' VALUES (7)');
    }
    if ($request['temporary']) {
        $owner->exec('INSERT INTO ' . REMOVAL_FK_CHILD . ' VALUES (33,44,67)');
    }
    $catalog->exec('UPDATE ' . REMOVAL_FK_MARKER . ' SET n=0');
}

function foreignKeyRemovalIndexes(PDO $pdo): array
{
    $keys = [];
    foreach ($pdo->query('SHOW INDEX FROM ' . REMOVAL_FK_CHILD)->fetchAll(PDO::FETCH_ASSOC) as $key) {
        $keys[] = [$key['Key_name'], $key['Non_unique'], $key['Seq_in_index'], $key['Column_name'], $key['Collation'], $key['Index_type']];
    }
    usort($keys, static fn (array $a, array $b): int => strcmp($a[0], $b[0]) ?: $a[2] <=> $b[2]);

    return $keys;
}

function inspectForeignKeyRemoval(PDO $catalog, PDO $owner, bool $temporary): array
{
    $edges = $catalog->query("SELECT TABLE_SCHEMA,TABLE_NAME,CONSTRAINT_NAME,ORDINAL_POSITION,COLUMN_NAME,"
        . "REFERENCED_TABLE_SCHEMA,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE "
        . "WHERE TABLE_SCHEMA IN ('ir_fixture','ir_rc_aux') AND TABLE_NAME IN ('ir_rc_child','ir_rc_other') "
        . "AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY TABLE_SCHEMA,TABLE_NAME,BINARY CONSTRAINT_NAME,ORDINAL_POSITION")->fetchAll(PDO::FETCH_NUM);

    return ['base' => foreignKeyRemovalIndexes($catalog), 'temporary' => $temporary ? foreignKeyRemovalIndexes($owner) : null, 'edges' => $edges];
}

function observeForeignKeyRemoval(PDO $catalog, PDO $owner, array $request): array
{
    if (!admitForeignKeyRemoval($request)) {
        return ['status' => 'outside-observer-scope'];
    }
    resetForeignKeyRemoval($catalog, $owner, $request);
    $temporary = $request['temporary'];
    $settings = [$catalog->query('SELECT @@foreign_key_checks')->fetchColumn(), $owner->query('SELECT @@foreign_key_checks')->fetchColumn()];
    $before = inspectForeignKeyRemoval($catalog, $owner, $temporary);
    $action = observeMysqlIndexEffect($catalog, $owner, REMOVAL_FK_MARKER, $request['sql']);
    $after = inspectForeignKeyRemoval($catalog, $owner, $temporary);
    $x = applyMysqlFixtureWrite($catalog, 'INSERT INTO ' . REMOVAL_FK_CHILD . ' VALUES (87,11,98)');
    $y = applyMysqlFixtureWrite($catalog, 'INSERT INTO ' . REMOVAL_FK_CHILD . ' VALUES (13,89,97)');
    $state = 'SELECT x,y,z FROM ' . REMOVAL_FK_CHILD . ' ORDER BY z';

    return ['status' => 'mysql-drop-foreign-keys', 'before' => $before, 'after' => $after, ...$action, 'settings' => $settings,
            'probeX' => $x, 'probeY' => $y, 'rows' => $catalog->query($state)->fetchAll(PDO::FETCH_NUM),
            'temporaryRows' => $temporary ? $owner->query($state)->fetchAll(PDO::FETCH_NUM) : null];
}

runMysqlIndexObserver($argv, initForeignKeyRemoval(...), observeForeignKeyRemoval(...));
