<?php

declare(strict_types=1);

require_once __DIR__ . '/mysql_index_observation.php';

const DROP_REFS_SCHEMAS = ['ir_fixture', 'ir_drop_fk_decoy'];
const DROP_REFS_LABELS = ['ir_fk_drop_grand', 'ir_fk_drop_child', 'ir_fk_drop_parent'];
const DROP_REFS_MARKER = 'ir_fixture.ir_fk_drop_marker';
const DROP_REFS_NAME = '\x60(?:ir_fixture|ir_drop_fk_decoy)\x60\.\x60ir_fk_drop_(?:parent|child|grand)\x60';

function initDropReferences(PDO $catalog): void
{
    $catalog->exec('CREATE DATABASE IF NOT EXISTS ir_drop_fk_decoy');
    $catalog->exec('DROP TABLE IF EXISTS ' . DROP_REFS_MARKER);
    $catalog->exec('CREATE TABLE ' . DROP_REFS_MARKER . ' (n INT NOT NULL) ENGINE=InnoDB');
    $catalog->exec('INSERT INTO ' . DROP_REFS_MARKER . ' VALUES (0)');
}

function dropReferenceScope(array $request): bool
{
    if (!is_int($request['offset']) || $request['offset'] < 0 || $request['offset'] > 80
        || !is_bool($request['populated']) || count($request['nodes']) < 2 || count($request['nodes']) > 3
        || count($request['declarations']) < 2 || count($request['declarations']) > 4) {
        return false;
    }
    $coordinates = $request['shadow'] === null ? $request['nodes'] : [...$request['nodes'], $request['shadow']];
    foreach ($coordinates as [$schema, $name]) {
        if (!in_array($schema, DROP_REFS_SCHEMAS, true) || !in_array($name, DROP_REFS_LABELS, true)) {
            return false;
        }
    }
    $layout = ' \(id INT PRIMARY KEY, pid INT(?:, FOREIGN KEY \(pid\) REFERENCES ' . DROP_REFS_NAME . '\(id\)){0,2}\)';
    foreach ($request['declarations'] as $sql) {
        if (!is_string($sql) || preg_match('/\ACREATE (?:TEMPORARY )?TABLE ' . DROP_REFS_NAME . $layout . ' ENGINE=InnoDB\z/D', $sql) !== 1) {
            return false;
        }
    }

    return preg_match('/\ADROP (?:TEMPORARY )?TABLE (?:IF EXISTS )?' . DROP_REFS_NAME . '(?:, ' . DROP_REFS_NAME . '){0,2}(?: CASCADE| RESTRICT)?\z/D',
        $request['sql']) === 1;
}

function prepareDropReferences(PDO $catalog, PDO $owner, array $request): void
{
    foreach (DROP_REFS_LABELS as $label) {
        foreach (DROP_REFS_SCHEMAS as $schema) {
            $owner->exec('DROP TEMPORARY TABLE IF EXISTS ' . $schema . '.' . $label);
        }
    }
    $catalog->exec('SET SESSION FOREIGN_KEY_CHECKS=0');
    try {
        foreach (DROP_REFS_LABELS as $label) {
            foreach (DROP_REFS_SCHEMAS as $schema) {
                $catalog->exec('DROP TABLE IF EXISTS ' . $schema . '.' . $label);
            }
        }
        foreach ($request['declarations'] as $ddl) {
            (str_starts_with($ddl, 'CREATE TEMPORARY') ? $owner : $catalog)->exec($ddl);
        }
    } finally {
        $catalog->exec('SET SESSION FOREIGN_KEY_CHECKS=1');
    }
    $catalog->exec('UPDATE ' . DROP_REFS_MARKER . ' SET n=0');
    foreach ($request['nodes'] as $position => [$schema, $label]) {
        if ($position !== 1 || $request['populated']) {
            $insert = $catalog->prepare('INSERT INTO ' . $schema . '.' . $label . ' VALUES (?,NULL)');
            $insert->execute([101 + 100 * $position + $request['offset']]);
        }
    }
    if ($request['shadow'] !== null) {
        $insert = $owner->prepare('INSERT INTO ' . implode('.', $request['shadow']) . ' VALUES (?,NULL)');
        $insert->execute([901 + $request['offset']]);
    }
}

function snapshotDropReferences(PDO $catalog, PDO $owner, array $request): array
{
    $tables = $catalog->query("SELECT TABLE_SCHEMA,TABLE_NAME FROM information_schema.TABLES "
        . "WHERE TABLE_SCHEMA IN ('ir_fixture','ir_drop_fk_decoy') "
        . "AND TABLE_NAME IN ('ir_fk_drop_parent','ir_fk_drop_child','ir_fk_drop_grand') ORDER BY TABLE_SCHEMA,TABLE_NAME")->fetchAll(PDO::FETCH_NUM);
    $edges = $catalog->query("SELECT TABLE_SCHEMA,TABLE_NAME,REFERENCED_TABLE_SCHEMA,REFERENCED_TABLE_NAME "
        . "FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA IN ('ir_fixture','ir_drop_fk_decoy') "
        . "AND TABLE_NAME IN ('ir_fk_drop_parent','ir_fk_drop_child','ir_fk_drop_grand') AND REFERENCED_TABLE_NAME IS NOT NULL "
        . "ORDER BY TABLE_SCHEMA,TABLE_NAME,REFERENCED_TABLE_SCHEMA,REFERENCED_TABLE_NAME")->fetchAll(PDO::FETCH_NUM);
    $rows = [];
    foreach ($tables as [$schema, $label]) {
        $id = $catalog->query('SELECT MIN(id) FROM ' . $schema . '.' . $label)->fetchColumn();
        $rows[] = [$schema, $label, $id];
    }
    $shadow = null;
    if ($request['shadow'] !== null) {
        $relation = implode('.', $request['shadow']);
        if (mysqlFixtureTableDefinition($owner, $relation) !== null) {
            $id = $owner->query('SELECT MIN(id) FROM ' . $relation)->fetchColumn();
            $shadow = $id === 901 + $request['offset'] ? $id : null;
        }
    }

    return ['tables' => $rows, 'edges' => $edges, 'shadow' => $shadow];
}

function observeDropReferences(PDO $catalog, PDO $owner, array $request): array
{
    if (!dropReferenceScope($request)) {
        return ['status' => 'outside-observer-scope'];
    }
    prepareDropReferences($catalog, $owner, $request);
    $settings = $owner->query('SELECT @@foreign_key_checks, @@autocommit')->fetch(PDO::FETCH_NUM);
    $before = snapshotDropReferences($catalog, $owner, $request);
    $effect = observeMysqlIndexEffect($catalog, $owner, DROP_REFS_MARKER, $request['sql']);
    $after = snapshotDropReferences($catalog, $owner, $request);

    return ['status' => 'mysql-drop-table-foreign-keys', 'before' => $before, 'after' => $after, ...$effect, 'settings' => $settings];
}

runMysqlIndexObserver($argv, initDropReferences(...), observeDropReferences(...));
