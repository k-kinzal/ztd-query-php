<?php

declare(strict_types=1);

require_once __DIR__ . '/mysql_index_observation.php';

const ENUM_FK_CHILD = 'ir_fixture.ir_fk_enumeration_child';
const ENUM_FK_PARENT = 'ir_fixture.ir_fk_enumeration_parent';
const ENUM_FK_MARKER = 'ir_fixture.ir_fk_enumeration_marker';

function initEnumerationReferences(PDO $pdo): void
{
    $pdo->exec('DROP TABLE IF EXISTS ' . ENUM_FK_MARKER);
    $pdo->exec('CREATE TABLE ' . ENUM_FK_MARKER . ' (n INT NOT NULL) ENGINE=InnoDB');
    $pdo->exec('INSERT INTO ' . ENUM_FK_MARKER . ' VALUES (0)');
}

function admitsEnumerationValues(array $inputs, int $width): bool
{
    if (count($inputs['probes']) !== 3 || count($inputs['parentRows']) !== 2) {
        return false;
    }
    $literal = "/\A(?:[0-9]{1,20}|NULL|'(?:RED|2020-01-02)')\z/D";
    foreach ([...$inputs['probes'], ...$inputs['parentRows'], $inputs['eventChild']] as $row) {
        if (!array_is_list($row) || count($row) !== $width) {
            return false;
        }
        foreach ($row as $value) {
            if (!is_string($value) || preg_match($literal, $value) !== 1) {
                return false;
            }
        }
    }

    return true;
}

function admitsEnumerationReference(array $request): bool
{
    $width = $request['width'];
    if (!is_int($width) || $width < 1 || $width > 3 || count($request['declarations']) !== 2
        || !admitsEnumerationValues($request['inputs'], $width)) {
        return false;
    }
    $child = '`ir_fixture`\.`ir_fk_enumeration_child`';
    $parent = '`ir_fixture`\.`ir_fk_enumeration_parent`';
    $member = "'(?:[^\\\\'\x00]|''|\\\\0|\\\\\\\\)*'";
    $enumeration = '(?:ENUM|SET)\(' . $member . '(?:,' . $member . ')*\)(?: CHARACTER SET utf8mb4 COLLATE utf8mb4_(?:bin|general_ci|unicode_ci))?';
    $type = '(?:' . $enumeration . '|YEAR|DATE|BIT\(8\)|(?:TINYINT|SMALLINT|MEDIUMINT|INT|BIGINT)(?: UNSIGNED)?)';
    $suffix = ' ENGINE=InnoDB(?: DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin)?\z/Du';
    $xs = $ps = $left = $right = $fields = [];
    for ($index = 0; $index < $width; ++$index) {
        $xs[] = '`x' . $index . '`';
        $ps[] = '`p' . $index . '`';
        $fields[] = 'p' . $index;
        $left[] = 'x' . $index . ' ' . $type . ' NULL';
        $right[] = 'p' . $index . ' ' . $type . ' NOT NULL';
    }
    $parentDdl = '/\ACREATE TABLE ' . $parent . ' \(' . implode(', ', $right)
        . ', PRIMARY KEY \(' . implode(', ', $fields) . '\)\)' . $suffix;
    $childDdl = '/\ACREATE TABLE ' . $child . ' \(' . implode(', ', $left) . ', z INT PRIMARY KEY\)' . $suffix;
    $alter = '/\AALTER TABLE ' . $child . ' ADD CONSTRAINT `Native_Enumeration` FOREIGN KEY \(' . implode(', ', $xs)
        . '\) REFERENCES (?:' . $parent . '|`ir_fk_enumeration_parent`) \(' . implode(', ', $ps)
        . '\) ON UPDATE (?:CASCADE|NO ACTION) ON DELETE (?:SET NULL|NO ACTION)\z/D';

    return preg_match($parentDdl, $request['declarations'][0]) === 1
        && preg_match($childDdl, $request['declarations'][1]) === 1 && preg_match($alter, $request['sql']) === 1;
}

function enumerationFieldProjection(string $prefix, int $count): string
{
    $fields = [];
    for ($index = 0; $index < $count; ++$index) {
        $field = $prefix . $index;
        $fields[] = 'HEX(' . $field . '),CAST(CAST(' . $field . ' AS UNSIGNED) AS CHAR)';
    }

    return implode(',', $fields);
}

function nativeEnumerationIndexes(PDO $pdo): array
{
    $fields = [];
    foreach ($pdo->query('SHOW INDEX FROM ' . ENUM_FK_CHILD)->fetchAll(PDO::FETCH_ASSOC) as $key) {
        $fields[] = [$key['Key_name'], $key['Seq_in_index'], $key['Column_name'], $key['Non_unique'], $key['Collation']];
    }
    usort($fields, static fn (array $left, array $right): int => strcmp($left[0], $right[0]) ?: $left[1] <=> $right[1]);

    return $fields;
}

function nativeEnumerationDictionary(PDO $pdo): array
{
    $where = " WHERE TABLE_SCHEMA='ir_fixture' AND TABLE_NAME='ir_fk_enumeration_child'";
    $edges = $pdo->query('SELECT CONSTRAINT_NAME,ORDINAL_POSITION,COLUMN_NAME,REFERENCED_TABLE_SCHEMA,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME '
        . 'FROM information_schema.KEY_COLUMN_USAGE' . $where . ' AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY BINARY CONSTRAINT_NAME,ORDINAL_POSITION');
    $rules = $pdo->query('SELECT CONSTRAINT_NAME,UPDATE_RULE,DELETE_RULE,MATCH_OPTION FROM information_schema.REFERENTIAL_CONSTRAINTS '
        . "WHERE CONSTRAINT_SCHEMA='ir_fixture' AND TABLE_NAME='ir_fk_enumeration_child' ORDER BY BINARY CONSTRAINT_NAME");
    $types = $pdo->query("SELECT TABLE_NAME,COLUMN_NAME,COLUMN_TYPE,COLLATION_NAME FROM information_schema.COLUMNS "
        . "WHERE TABLE_SCHEMA='ir_fixture' AND TABLE_NAME IN ('ir_fk_enumeration_child','ir_fk_enumeration_parent') AND COLUMN_NAME!='z' "
        . 'ORDER BY TABLE_NAME,ORDINAL_POSITION');

    return ['edges' => $edges->fetchAll(PDO::FETCH_NUM), 'rules' => $rules->fetchAll(PDO::FETCH_NUM), 'types' => $types->fetchAll(PDO::FETCH_NUM)];
}

function probeEnumerationRows(PDO $pdo, array $probes, string $childQuery): array
{
    $observed = [];
    foreach ($probes as $probe) {
        $pdo->beginTransaction();
        try {
            $effect = applyMysqlFixtureWrite($pdo, 'INSERT INTO ' . ENUM_FK_CHILD . ' VALUES (' . implode(',', $probe) . ',31)');
            $notes = mysqlFixtureDiagnostics($pdo)['diagnostics'];
            $row = $effect[0] === 'executed' ? $pdo->query($childQuery . ' WHERE z=31')->fetch(PDO::FETCH_NUM) : null;
            $observed[] = [$effect, $row, $notes];
        } finally {
            $pdo->rollBack();
        }
    }

    return $observed;
}

function observeEnumerationEvents(PDO $pdo, array $inputs, string $childQuery, string $parentQuery): array
{
    $assignments = [];
    foreach ($inputs['parentRows'][1] as $index => $value) {
        $assignments[] = 'p' . $index . '=' . $value;
    }
    $writes = ['update' => 'UPDATE ' . ENUM_FK_PARENT . ' SET ' . implode(',', $assignments), 'delete' => 'DELETE FROM ' . ENUM_FK_PARENT];
    $row = 'INSERT INTO ' . ENUM_FK_CHILD . ' VALUES (' . implode(',', $inputs['eventChild']) . ',32)';
    $results = [];
    foreach ($writes as $event => $statement) {
        $pdo->beginTransaction();
        try {
            $pdo->exec($row);
            $effect = applyMysqlFixtureWrite($pdo, $statement);
            $notes = mysqlFixtureDiagnostics($pdo)['diagnostics'];
            $results[] = [$event, $effect, $pdo->query($parentQuery)->fetchAll(PDO::FETCH_NUM),
                $pdo->query($childQuery . ' ORDER BY z')->fetchAll(PDO::FETCH_NUM), $notes];
        } finally {
            $pdo->rollBack();
        }
    }

    return $results;
}

function observeEnumerationReference(PDO $catalog, PDO $owner, array $request): array
{
    if (!admitsEnumerationReference($request)) {
        return ['status' => 'outside-observer-scope'];
    }
    $catalog->exec('DROP TABLE IF EXISTS ' . ENUM_FK_CHILD . ', ' . ENUM_FK_PARENT);
    $setupWarnings = [];
    foreach ($request['declarations'] as $ddl) {
        $catalog->exec($ddl);
        $setupWarnings[] = mysqlFixtureDiagnostics($catalog)['diagnostics'];
    }
    $inputs = $request['inputs'];
    $catalog->exec('INSERT INTO ' . ENUM_FK_PARENT . ' VALUES (' . implode(',', $inputs['parentRows'][0]) . ')');
    $catalog->exec('UPDATE ' . ENUM_FK_MARKER . ' SET n=0');
    $before = nativeEnumerationIndexes($catalog);
    $checks = [$catalog->query('SELECT @@foreign_key_checks')->fetchColumn(), $owner->query('SELECT @@foreign_key_checks')->fetchColumn()];
    $effect = observeMysqlIndexEffect($catalog, $owner, ENUM_FK_MARKER, $request['sql']);
    $after = nativeEnumerationIndexes($catalog);
    $dictionary = nativeEnumerationDictionary($catalog);
    $data = [[], []];
    $events = [];
    if ($effect['effect'] === ['executed']) {
        $width = $request['width'];
        $parent = 'SELECT ' . enumerationFieldProjection('p', $width) . ' FROM ' . ENUM_FK_PARENT;
        $child = 'SELECT ' . enumerationFieldProjection('x', $width) . ' FROM ' . ENUM_FK_CHILD;
        $data = [$catalog->query($parent)->fetch(PDO::FETCH_NUM), probeEnumerationRows($catalog, $inputs['probes'], $child)];
        $events = observeEnumerationEvents($catalog, $inputs, $child, $parent);
    }

    return ['status' => 'mysql-foreign-key-enumeration-fields', 'before' => $before, 'after' => $after,
        'setupWarnings' => $setupWarnings, 'checks' => $checks, 'data' => $data, 'events' => $events,
        ...$dictionary, ...$effect];
}

runMysqlIndexObserver($argv, initEnumerationReferences(...), observeEnumerationReference(...));
