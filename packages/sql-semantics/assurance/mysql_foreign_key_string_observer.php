<?php

declare(strict_types=1);

require_once __DIR__ . '/mysql_index_observation.php';

const FK_STRING_C = 'ir_fixture.ir_fk_string_child';
const FK_STRING_P = 'ir_fixture.ir_fk_string_parent';
const FK_STRING_MARKER = 'ir_fixture.ir_fk_string_marker';
const FK_STRING_C_RX = '`ir_fixture`\.`ir_fk_string_child`';
const FK_STRING_P_RX = '`ir_fixture`\.`ir_fk_string_parent`';
const FK_STRING_VALUES = ['4162', '416220', '6162', '41620000', '4162000000000000', '6165', null];

function initForeignKeyStrings(PDO $catalog): void
{
    $catalog->exec('DROP TABLE IF EXISTS ' . FK_STRING_MARKER);
    $catalog->exec('CREATE TABLE ' . FK_STRING_MARKER . ' (n INT NOT NULL) ENGINE=InnoDB');
    $catalog->exec('INSERT INTO ' . FK_STRING_MARKER . ' VALUES (0)');
}

function admitForeignKeyStrings(array $request): bool
{
    $width = $request['width'];
    if (!is_int($width) || $width < 1 || $width > 5 || count($request['declarations']) !== 2
        || !in_array($request['matching'], FK_STRING_VALUES, true)) {
        return false;
    }
    $type = '(?:(?:BINARY|VARBINARY)\([0-9]{1,4}\)|(?:CHAR|VARCHAR)\([0-9]{1,4}\) CHARACTER SET utf8mb4 COLLATE utf8mb4_(?:bin|general_ci|unicode_ci))';
    $children = $parents = $quotedChildren = $quotedParents = $plainParents = [];
    for ($position = 0; $position < $width; ++$position) {
        $parents[] = 'p' . $position . ' ' . $type . ' NOT NULL';
        $children[] = 'x' . $position . ' ' . $type;
        $plainParents[] = 'p' . $position;
        $quotedParents[] = '`p' . $position . '`';
        $quotedChildren[] = '`x' . $position . '`';
    }
    $parent = '/\ACREATE TABLE ' . FK_STRING_P_RX . ' \(' . implode(', ', $parents)
        . ', PRIMARY KEY \(' . implode(', ', $plainParents) . '\)\) ENGINE=InnoDB\z/D';
    $child = '/\ACREATE TABLE ' . FK_STRING_C_RX . ' \(' . implode(', ', $children)
        . ', z INT PRIMARY KEY\) ENGINE=InnoDB(?: ROW_FORMAT=(?:COMPACT|DYNAMIC))?\z/D';
    $query = '/\AALTER TABLE ' . FK_STRING_C_RX . ' ADD CONSTRAINT `Native_String` FOREIGN KEY \('
        . implode(', ', $quotedChildren) . '\) REFERENCES (?:' . FK_STRING_P_RX . '|`ir_fk_string_parent`) \('
        . implode(', ', $quotedParents) . '\) ON UPDATE (?:CASCADE|NO ACTION) ON DELETE (?:SET NULL|NO ACTION)\z/D';

    return preg_match($parent, $request['declarations'][0]) === 1 && preg_match($child, $request['declarations'][1]) === 1
        && preg_match($query, $request['sql']) === 1;
}

function foreignStringIndexes(PDO $pdo): array
{
    $indexes = [];
    foreach ($pdo->query('SHOW INDEX FROM ' . FK_STRING_C)->fetchAll(PDO::FETCH_ASSOC) as $key) {
        $indexes[] = [$key['Key_name'], $key['Seq_in_index'], $key['Column_name'], $key['Non_unique'], $key['Collation']];
    }
    usort($indexes, static fn (array $a, array $b): int => strcmp($a[0], $b[0]) ?: $a[1] <=> $b[1]);

    return $indexes;
}

function foreignStringHexColumns(string $prefix, int $width): string
{
    $fields = [];
    for ($i = 0; $i < $width; ++$i) {
        $fields[] = 'HEX(' . $prefix . $i . ')';
    }

    return implode(',', $fields);
}

function foreignStringProbe(PDO $pdo, int $width, ?string $hex): array
{
    $pdo->beginTransaction();
    try {
        $datum = $hex === null ? 'NULL' : "X'" . $hex . "'";
        $statement = 'INSERT INTO ' . FK_STRING_C . ' VALUES (' . implode(',', array_fill(0, $width, $datum)) . ',31)';
        $effect = applyMysqlFixtureWrite($pdo, $statement);
        $notes = mysqlFixtureDiagnostics($pdo)['diagnostics'];
        $rows = $effect[0] === 'executed'
            ? $pdo->query('SELECT ' . foreignStringHexColumns('x', $width) . ' FROM ' . FK_STRING_C . ' WHERE z=31')->fetch(PDO::FETCH_NUM)
            : null;

        return [$effect, $rows, $notes];
    } finally {
        $pdo->rollBack();
    }
}

function foreignStringEvents(PDO $pdo, int $width, ?string $matched): array
{
    if ($matched === null) {
        return [];
    }
    $child = 'INSERT INTO ' . FK_STRING_C . ' VALUES (' . implode(',', array_fill(0, $width, "X'" . $matched . "'")) . ',32)';
    $assign = [];
    for ($position = 0; $position < $width; ++$position) {
        $assign[] = 'p' . $position . "=X'4163'";
    }
    $statements = ['update' => 'UPDATE ' . FK_STRING_P . ' SET ' . implode(',', $assign), 'delete' => 'DELETE FROM ' . FK_STRING_P];
    $result = [];
    foreach ($statements as $event => $statement) {
        $pdo->beginTransaction();
        try {
            $pdo->exec($child);
            $effect = applyMysqlFixtureWrite($pdo, $statement);
            $parent = $pdo->query('SELECT ' . foreignStringHexColumns('p', $width) . ' FROM ' . FK_STRING_P)->fetchAll(PDO::FETCH_NUM);
            $observed = $pdo->query('SELECT ' . foreignStringHexColumns('x', $width) . ' FROM ' . FK_STRING_C . ' ORDER BY z')->fetchAll(PDO::FETCH_NUM);
            $result[] = [$event, $effect, $parent, $observed];
        } finally {
            $pdo->rollBack();
        }
    }

    return $result;
}

function observeForeignKeyStrings(PDO $catalog, PDO $owner, array $request): array
{
    if (!admitForeignKeyStrings($request)) {
        return ['status' => 'outside-observer-scope'];
    }
    $catalog->exec('DROP TABLE IF EXISTS ' . FK_STRING_C . ', ' . FK_STRING_P);
    $setupWarnings = [];
    foreach ($request['declarations'] as $statement) {
        $catalog->exec($statement);
        $setupWarnings[] = mysqlFixtureDiagnostics($catalog)['diagnostics'];
    }
    $width = $request['width'];
    $catalog->exec('INSERT INTO ' . FK_STRING_P . ' VALUES (' . implode(',', array_fill(0, $width, "X'4162'")) . ')');
    $catalog->exec('UPDATE ' . FK_STRING_MARKER . ' SET n=0');
    $before = foreignStringIndexes($catalog);
    $checks = [$catalog->query('SELECT @@foreign_key_checks')->fetchColumn(), $owner->query('SELECT @@foreign_key_checks')->fetchColumn()];
    $execution = observeMysqlIndexEffect($catalog, $owner, FK_STRING_MARKER, $request['sql']);
    $types = $catalog->query("SELECT TABLE_NAME,COLUMN_NAME,COLUMN_TYPE,COLLATION_NAME FROM information_schema.COLUMNS "
        . "WHERE TABLE_SCHEMA='ir_fixture' AND TABLE_NAME IN ('ir_fk_string_child','ir_fk_string_parent') AND COLUMN_NAME!='z' "
        . 'ORDER BY TABLE_NAME,ORDINAL_POSITION')->fetchAll(PDO::FETCH_NUM);
    $edges = $catalog->query("SELECT CONSTRAINT_NAME,ORDINAL_POSITION,COLUMN_NAME,REFERENCED_TABLE_SCHEMA,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME "
        . "FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA='ir_fixture' AND TABLE_NAME='ir_fk_string_child' "
        . "AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY BINARY CONSTRAINT_NAME,ORDINAL_POSITION")->fetchAll(PDO::FETCH_NUM);
    $rules = $catalog->query("SELECT CONSTRAINT_NAME,UPDATE_RULE,DELETE_RULE,MATCH_OPTION FROM information_schema.REFERENTIAL_CONSTRAINTS "
        . "WHERE CONSTRAINT_SCHEMA='ir_fixture' AND TABLE_NAME='ir_fk_string_child' ORDER BY BINARY CONSTRAINT_NAME")->fetchAll(PDO::FETCH_NUM);
    $format = $catalog->query("SELECT ROW_FORMAT FROM information_schema.TABLES WHERE TABLE_SCHEMA='ir_fixture' AND TABLE_NAME='ir_fk_string_child'")->fetchColumn();
    $data = [[], []];
    $warnings = [];
    if ($execution['effect'] === ['executed']) {
        $data[0] = $catalog->query('SELECT ' . foreignStringHexColumns('p', $width) . ' FROM ' . FK_STRING_P)->fetch(PDO::FETCH_NUM);
        foreach (FK_STRING_VALUES as $hex) {
            [$effect, $rows, $notes] = foreignStringProbe($catalog, $width, $hex);
            $data[1][] = [$effect, $rows];
            $warnings[] = $notes;
        }
    }

    return ['status' => 'mysql-foreign-key-string-fields', 'before' => $before, 'after' => foreignStringIndexes($catalog),
        'edges' => $edges, 'rules' => $rules, 'data' => $data,
        'events' => $execution['effect'] === ['executed'] ? foreignStringEvents($catalog, $width, $request['matching']) : [], 'types' => $types, 'format' => $format, 'checks' => $checks,
        'setupWarnings' => $setupWarnings, 'probeWarnings' => $warnings, ...$execution];
}

runMysqlIndexObserver($argv, initForeignKeyStrings(...), observeForeignKeyStrings(...));
