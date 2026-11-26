<?php

declare(strict_types=1);

require_once __DIR__ . '/mysql_index_observation.php';

const INDEX_FK_PARENT = 'ir_fixture.ir_ixfk_p';
const INDEX_FK_CHILDREN = ['ir_fixture.ir_ixfk_c', 'ir_ifk_other.ir_ixfk_c'];
const INDEX_FK_MARKER = 'ir_fixture.ir_ixfk_marker';
const INDEX_FK_RX = '\x60(?:ir_fixture|ir_ifk_other)\x60\.\x60ir_ixfk_[pc]\x60';
const INDEX_FK_KEY_RX = '\x60(?:[uvw]|moved)[0-9]{1,2}\x60';
const INDEX_FK_FIELD_RX = '\x60[a-bxyz]\x60';

function initForeignIndexes(PDO $catalog): void
{
    $catalog->exec('CREATE DATABASE IF NOT EXISTS ir_ifk_other');
    $catalog->exec('DROP TABLE IF EXISTS ' . INDEX_FK_MARKER);
    $catalog->exec('CREATE TABLE ' . INDEX_FK_MARKER . ' (n INT NOT NULL) ENGINE=InnoDB');
    $catalog->exec('INSERT INTO ' . INDEX_FK_MARKER . ' VALUES (0)');
}

function nativeForeignIndexRequest(array $request): bool
{
    if (!is_int($request['offset']) || $request['offset'] < 0 || $request['offset'] > 34
        || !in_array($request['child'], INDEX_FK_CHILDREN, true)
        || !in_array($request['shadow'], [null, INDEX_FK_PARENT, $request['child']], true)
        || count($request['declarations']) !== ($request['shadow'] === null ? 2 : 3)) {
        return false;
    }
    $field = INDEX_FK_FIELD_RX;
    $fields = $field . '(?:, ' . $field . '){0,2}';
    $ordered = $field . '(?: DESC)?(?:, ' . $field . '(?: DESC)?){0,2}';
    $columns = '(' . $field . ' INT NOT NULL, ){3}';
    $keyName = '(?:' . INDEX_FK_KEY_RX . '|\x60stable_support\x60)';
    $keys = 'PRIMARY KEY \(' . $field . '\)(?:, (?:UNIQUE )?KEY ' . $keyName . ' \(' . $ordered . '\)(?: INVISIBLE)?){1,3}';
    $edge = '(?:, CONSTRAINT owned_fk FOREIGN KEY \(' . $fields . '\) REFERENCES ' . INDEX_FK_RX . ' \(' . $fields . '\))?';
    foreach ($request['declarations'] as $ddl) {
        if (!is_string($ddl) || preg_match('/\ACREATE (?:TEMPORARY )?TABLE ' . INDEX_FK_RX . ' \(' . $columns . $keys . $edge . '\) ENGINE=InnoDB\z/D', $ddl) !== 1) {
            return false;
        }
    }
    $single = 'DROP INDEX ' . INDEX_FK_KEY_RX . ' ON ' . INDEX_FK_RX;
    $drop = 'DROP (?:INDEX|KEY) ' . INDEX_FK_KEY_RX;
    $move = 'RENAME INDEX ' . INDEX_FK_KEY_RX . ' TO ' . INDEX_FK_KEY_RX;
    $alter = 'ALTER TABLE ' . INDEX_FK_RX . ' ' . $drop . '(?:, (?:' . $drop . '|' . $move . '))?';

    return preg_match('/\A(?:' . $single . '|' . $alter . ')\z/D', $request['sql']) === 1;
}

function resetForeignIndexes(PDO $catalog, PDO $owner, array $request): void
{
    foreach ([...INDEX_FK_CHILDREN, INDEX_FK_PARENT] as $table) {
        $owner->exec('DROP TEMPORARY TABLE IF EXISTS ' . $table);
        $catalog->exec('DROP TABLE IF EXISTS ' . $table);
    }
    foreach (['mysql.innodb_index_stats', 'mysql.innodb_table_stats'] as $stats) {
        $catalog->exec("DELETE FROM " . $stats . " WHERE database_name IN ('ir_fixture','ir_ifk_other') AND table_name IN ('ir_ixfk_p','ir_ixfk_c')");
    }
    foreach ($request['declarations'] as $ddl) {
        (str_starts_with($ddl, 'CREATE TEMPORARY') ? $owner : $catalog)->exec($ddl);
    }
    $catalog->exec('UPDATE ' . INDEX_FK_MARKER . ' SET n=0');
    $n = $request['offset'];
    foreach ([INDEX_FK_PARENT, $request['child']] as $table) {
        $insert = $catalog->prepare('INSERT INTO ' . $table . ' VALUES (?,?,?)');
        $insert->execute([1 + $n, 2 + $n, 11 + $n]);
    }
    if ($request['shadow'] !== null) {
        $insert = $owner->prepare('INSERT INTO ' . $request['shadow'] . ' VALUES (?,?,?)');
        $insert->execute([51 + $n, 52 + $n, 61 + $n]);
    }
}

function foreignIndexKeys(PDO $pdo, string $table): array
{
    $keys = [];
    foreach ($pdo->query('SHOW INDEX FROM ' . $table)->fetchAll(PDO::FETCH_ASSOC) as $key) {
        $keys[] = [$key['Key_name'], $key['Non_unique'], $key['Seq_in_index'], $key['Column_name'], $key['Sub_part'],
            $key['Collation'], $key['Index_type'], $key['Visible'] ?? null];
    }
    usort($keys, static fn (array $a, array $b): int => strcmp($a[0], $b[0]) ?: $a[2] <=> $b[2]);

    return $keys;
}

function foreignIndexCatalog(PDO $catalog, PDO $owner, array $request): array
{
    $edges = $catalog->query("SELECT TABLE_SCHEMA,TABLE_NAME,CONSTRAINT_NAME,ORDINAL_POSITION,COLUMN_NAME,"
        . "REFERENCED_TABLE_SCHEMA,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE "
        . "WHERE TABLE_SCHEMA IN ('ir_fixture','ir_ifk_other') AND TABLE_NAME='ir_ixfk_c' "
        . "AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY TABLE_SCHEMA,TABLE_NAME,CONSTRAINT_NAME,ORDINAL_POSITION")->fetchAll(PDO::FETCH_NUM);
    $shadow = $request['shadow'];

    return ['parents' => foreignIndexKeys($catalog, INDEX_FK_PARENT), 'children' => foreignIndexKeys($catalog, $request['child']),
        'session' => $shadow === null ? null : foreignIndexKeys($owner, $shadow), 'edges' => $edges,
        'data' => [
            $catalog->query('SELECT z FROM ' . INDEX_FK_PARENT)->fetchColumn(),
            $catalog->query('SELECT z FROM ' . $request['child'])->fetchColumn(),
            $shadow === null ? null : $owner->query('SELECT z FROM ' . $shadow)->fetchColumn(),
        ]];
}

function observeForeignIndexRemoval(PDO $catalog, PDO $owner, array $request): array
{
    if (!nativeForeignIndexRequest($request)) {
        return ['status' => 'outside-observer-scope'];
    }
    resetForeignIndexes($catalog, $owner, $request);
    $setting = $owner->query("SHOW VARIABLES LIKE 'restrict_fk_on_non_standard_key'")->fetch(PDO::FETCH_NUM);
    $settings = [$owner->query('SELECT @@foreign_key_checks')->fetchColumn(), $setting === false ? null : $setting[1]];
    $before = foreignIndexCatalog($catalog, $owner, $request);
    $effect = observeMysqlIndexEffect($catalog, $owner, INDEX_FK_MARKER, $request['sql']);
    $after = foreignIndexCatalog($catalog, $owner, $request);

    return ['status' => 'mysql-index-foreign-keys', 'before' => $before, 'after' => $after, ...$effect, 'settings' => $settings];
}

runMysqlIndexObserver($argv, initForeignIndexes(...), observeForeignIndexRemoval(...));
