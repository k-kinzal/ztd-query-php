<?php

declare(strict_types=1);

require_once __DIR__ . '/mysql_index_observation.php';

const FK_SCALAR_C = 'ir_fixture.ir_fk_scalar_child';
const FK_SCALAR_P = 'ir_fixture.ir_fk_scalar_parent';
const FK_SCALAR_MARKER = 'ir_fixture.ir_fk_scalar_marker';
const FK_SCALAR_C_RX = '`ir_fixture`\.`ir_fk_scalar_child`';
const FK_SCALAR_P_RX = '`ir_fixture`\.`ir_fk_scalar_parent`';

function initForeignKeyScalars(PDO $catalog): void
{
    $catalog->exec("SET time_zone='+00:00'");
    $catalog->exec('DROP TABLE IF EXISTS ' . FK_SCALAR_MARKER);
    $catalog->exec('CREATE TABLE ' . FK_SCALAR_MARKER . ' (n INT NOT NULL) ENGINE=InnoDB');
    $catalog->exec('INSERT INTO ' . FK_SCALAR_MARKER . ' VALUES (0)');
}

function admitScalarFixtureRows(array $request): bool
{
    $width = $request['width'];
    $data = $request['inputs'];
    if (count($data['parentRows']) !== 2 || count($data['probes']) !== 3) {
        return false;
    }
    foreach ([$request['childHex'], $request['parentHex']] as $projection) {
        if (count($projection) !== $width || array_filter($projection, static fn ($term): bool => !is_bool($term)) !== []) {
            return false;
        }
    }
    $literal = "/\A(?:NULL|[0-9]{1,12}(?:\.[0-9]{1,30})?|X'[0-9A-F]{2,60}'|'[0-9: .-]{8,26}')\z/D";
    foreach ([...$data['parentRows'], ...$data['probes'], $data['eventChild']] as $row) {
        if (!array_is_list($row) || count($row) !== $width) {
            return false;
        }
        foreach ($row as $field) {
            if (!is_string($field) || preg_match($literal, $field) !== 1) {
                return false;
            }
        }
    }

    return true;
}

function admitForeignKeyScalars(array $request): bool
{
    $width = $request['width'];
    if (!is_int($width) || $width < 1 || $width > 3 || count($request['declarations']) !== 2 || !admitScalarFixtureRows($request)) {
        return false;
    }
    $type = '(?:(?:DECIMAL|BIT|TIME|DATETIME|TIMESTAMP|FLOAT|DOUBLE|REAL|DATE|YEAR|TINYINT|MEDIUMINT|VARBINARY)'
        . '(?:\([0-9]{1,10}(?:,[0-9]{1,2})?\))?(?: UNSIGNED| ZEROFILL)?)';
    $children = $parents = $quotedChildren = $quotedParents = $plainParents = [];
    for ($position = 0; $position < $width; ++$position) {
        $parents[] = 'p' . $position . ' ' . $type . ' NOT NULL';
        $children[] = 'x' . $position . ' ' . $type . ' NULL';
        $plainParents[] = 'p' . $position;
        $quotedParents[] = '`p' . $position . '`';
        $quotedChildren[] = '`x' . $position . '`';
    }
    $parent = '/\ACREATE TABLE ' . FK_SCALAR_P_RX . ' \(' . implode(', ', $parents)
        . ', PRIMARY KEY \(' . implode(', ', $plainParents) . '\)\) ENGINE=InnoDB\z/D';
    $child = '/\ACREATE TABLE ' . FK_SCALAR_C_RX . ' \(' . implode(', ', $children)
        . ', z INT PRIMARY KEY\) ENGINE=InnoDB\z/D';
    $query = '/\AALTER TABLE ' . FK_SCALAR_C_RX . ' ADD CONSTRAINT `Native_Scalar` FOREIGN KEY \('
        . implode(', ', $quotedChildren) . '\) REFERENCES (?:' . FK_SCALAR_P_RX . '|`ir_fk_scalar_parent`) \('
        . implode(', ', $quotedParents) . '\) ON UPDATE (?:CASCADE|NO ACTION) ON DELETE (?:SET NULL|NO ACTION)\z/D';

    return preg_match($parent, $request['declarations'][0]) === 1 && preg_match($child, $request['declarations'][1]) === 1
        && preg_match($query, $request['sql']) === 1;
}

function foreignScalarIndexes(PDO $pdo): array
{
    $indexes = [];
    foreach ($pdo->query('SHOW INDEX FROM ' . FK_SCALAR_C)->fetchAll(PDO::FETCH_ASSOC) as $key) {
        $indexes[] = [$key['Key_name'], $key['Seq_in_index'], $key['Column_name'], $key['Non_unique'], $key['Collation']];
    }
    usort($indexes, static fn (array $a, array $b): int => strcmp($a[0], $b[0]) ?: $a[1] <=> $b[1]);

    return $indexes;
}

function foreignScalarColumns(string $prefix, array $hex): string
{
    $fields = [];
    foreach ($hex as $position => $useHex) {
        $field = $prefix . $position;
        $fields[] = $useHex ? 'HEX(' . $field . ')' : 'CAST(' . $field . ' AS CHAR)';
    }

    return implode(',', $fields);
}

function foreignScalarProbes(PDO $pdo, array $probes, string $columns): array
{
    $outcomes = [];
    foreach ($probes as $row) {
        $pdo->beginTransaction();
        try {
            $statement = 'INSERT INTO ' . FK_SCALAR_C . ' VALUES (' . implode(',', $row) . ',31)';
            $effect = applyMysqlFixtureWrite($pdo, $statement);
            $notes = mysqlFixtureDiagnostics($pdo)['diagnostics'];
            $observed = $effect[0] === 'executed'
                ? $pdo->query('SELECT ' . $columns . ' FROM ' . FK_SCALAR_C . ' WHERE z=31')->fetch(PDO::FETCH_NUM)
                : null;
            $outcomes[] = [$effect, $observed, $notes];
        } finally {
            $pdo->rollBack();
        }
    }

    return $outcomes;
}

function foreignScalarEvents(PDO $pdo, array $inputs, string $childColumns, string $parentColumns): array
{
    $child = 'INSERT INTO ' . FK_SCALAR_C . ' VALUES (' . implode(',', $inputs['eventChild']) . ',32)';
    $assignments = [];
    foreach ($inputs['parentRows'][1] as $position => $field) {
        $assignments[] = 'p' . $position . '=' . $field;
    }
    $statements = ['update' => 'UPDATE ' . FK_SCALAR_P . ' SET ' . implode(',', $assignments), 'delete' => 'DELETE FROM ' . FK_SCALAR_P];
    $result = [];
    foreach ($statements as $event => $statement) {
        $pdo->beginTransaction();
        try {
            $pdo->exec($child);
            $effect = applyMysqlFixtureWrite($pdo, $statement);
            $notes = mysqlFixtureDiagnostics($pdo)['diagnostics'];
            $parent = $pdo->query('SELECT ' . $parentColumns . ' FROM ' . FK_SCALAR_P)->fetchAll(PDO::FETCH_NUM);
            $observed = $pdo->query('SELECT ' . $childColumns . ' FROM ' . FK_SCALAR_C . ' ORDER BY z')->fetchAll(PDO::FETCH_NUM);
            $result[] = [$event, $effect, $parent, $observed, $notes];
        } finally {
            $pdo->rollBack();
        }
    }

    return $result;
}

function foreignScalarCatalog(PDO $catalog): array
{
    $types = $catalog->query("SELECT TABLE_NAME,COLUMN_NAME,COLUMN_TYPE FROM information_schema.COLUMNS "
        . "WHERE TABLE_SCHEMA='ir_fixture' AND TABLE_NAME IN ('ir_fk_scalar_child','ir_fk_scalar_parent') AND COLUMN_NAME!='z' "
        . 'ORDER BY TABLE_NAME,ORDINAL_POSITION')->fetchAll(PDO::FETCH_NUM);
    $edges = $catalog->query("SELECT CONSTRAINT_NAME,ORDINAL_POSITION,COLUMN_NAME,REFERENCED_TABLE_SCHEMA,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME "
        . "FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA='ir_fixture' AND TABLE_NAME='ir_fk_scalar_child' "
        . "AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY BINARY CONSTRAINT_NAME,ORDINAL_POSITION")->fetchAll(PDO::FETCH_NUM);
    $rules = $catalog->query("SELECT CONSTRAINT_NAME,UPDATE_RULE,DELETE_RULE,MATCH_OPTION FROM information_schema.REFERENTIAL_CONSTRAINTS "
        . "WHERE CONSTRAINT_SCHEMA='ir_fixture' AND TABLE_NAME='ir_fk_scalar_child' ORDER BY BINARY CONSTRAINT_NAME")->fetchAll(PDO::FETCH_NUM);

    return ['types' => $types, 'edges' => $edges, 'rules' => $rules, 'after' => foreignScalarIndexes($catalog)];
}

function observeForeignKeyScalars(PDO $catalog, PDO $owner, array $request): array
{
    if (!admitForeignKeyScalars($request)) {
        return ['status' => 'outside-observer-scope'];
    }
    $owner->exec("SET time_zone='+00:00'");
    $catalog->exec('DROP TABLE IF EXISTS ' . FK_SCALAR_C . ', ' . FK_SCALAR_P);
    $setupWarnings = [];
    foreach ($request['declarations'] as $statement) {
        $catalog->exec($statement);
        $setupWarnings[] = mysqlFixtureDiagnostics($catalog)['diagnostics'];
    }
    $inputs = $request['inputs'];
    $catalog->exec('INSERT INTO ' . FK_SCALAR_P . ' VALUES (' . implode(',', $inputs['parentRows'][0]) . ')');
    $catalog->exec('UPDATE ' . FK_SCALAR_MARKER . ' SET n=0');
    $before = foreignScalarIndexes($catalog);
    $profile = [
        $catalog->query('SELECT @@foreign_key_checks,@@time_zone')->fetch(PDO::FETCH_NUM),
        $owner->query('SELECT @@foreign_key_checks,@@time_zone')->fetch(PDO::FETCH_NUM),
    ];
    $execution = observeMysqlIndexEffect($catalog, $owner, FK_SCALAR_MARKER, $request['sql']);
    $data = [[], []];
    $events = [];
    if ($execution['effect'] === ['executed']) {
        $parent = foreignScalarColumns('p', $request['parentHex']);
        $child = foreignScalarColumns('x', $request['childHex']);
        $data = [$catalog->query('SELECT ' . $parent . ' FROM ' . FK_SCALAR_P)->fetch(PDO::FETCH_NUM), foreignScalarProbes($catalog, $inputs['probes'], $child)];
        $events = foreignScalarEvents($catalog, $inputs, $child, $parent);
    }

    return ['status' => 'mysql-foreign-key-scalar-fields', 'before' => $before, 'data' => $data, 'events' => $events,
        'setupWarnings' => $setupWarnings, 'profile' => $profile, ...foreignScalarCatalog($catalog), ...$execution];
}

runMysqlIndexObserver($argv, initForeignKeyScalars(...), observeForeignKeyScalars(...));
