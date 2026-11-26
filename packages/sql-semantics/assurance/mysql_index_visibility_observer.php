<?php

declare(strict_types=1);

require_once __DIR__ . '/mysql_index_observation.php';

const VISIBILITY_RELATION = 'ir_fixture.ir_visibility';
const VISIBILITY_MARKER = 'ir_fixture.ir_visibility_marker';
const VISIBILITY_CONTROL = 'ir_vis_decoy.ir_visibility';
const VISIBILITY_NAME = '\x60ir_fixture\x60\.\x60ir_visibility\x60';
const VISIBILITY_KEY = '\x60(?:pv_[a-c][0-9]{1,2}|pv_missing|PRIMARY)\x60';

function initializeVisibility(PDO $catalog): void
{
    $catalog->exec('CREATE DATABASE IF NOT EXISTS ir_vis_decoy');
    $catalog->exec('DROP TABLE IF EXISTS ' . VISIBILITY_CONTROL);
    $catalog->exec('CREATE TABLE ' . VISIBILITY_CONTROL . ' (z INT PRIMARY KEY, a INT, KEY preserved_only (a) INVISIBLE) ENGINE=InnoDB');
    $catalog->exec('INSERT INTO ' . VISIBILITY_CONTROL . ' VALUES (301,307)');
    $catalog->exec('DROP TABLE IF EXISTS ' . VISIBILITY_MARKER);
    $catalog->exec('CREATE TABLE ' . VISIBILITY_MARKER . ' (n INT NOT NULL) ENGINE=InnoDB');
    $catalog->exec('INSERT INTO ' . VISIBILITY_MARKER . ' VALUES (0)');
}

function allowVisibilityRequest(array $request): bool
{
    $count = count($request['declarations']);
    if (!is_bool($request['temporary']) || !is_int($request['variant']) || $request['variant'] < 0
        || $request['variant'] >= 20 || $count !== ($request['temporary'] ? 2 : 1)
        || !in_array($request['family'], ['none', 'prefix', 'functional', 'fulltext', 'spatial'], true)) {
        return false;
    }
    $columns = 'a INT NOT NULL, b INT NOT NULL, z INT NOT NULL, payload VARCHAR\(12\)(?:, geom GEOMETRY NOT NULL)?';
    $names = '\x60pv_[ABC][0-9]{1,2}\x60';
    $key = '(?:(?:, PRIMARY KEY \(z\))|(?:, (?:UNIQUE |FULLTEXT |SPATIAL )?KEY ' . $names
        . ' \((?:a|b|payload|payload\(2\)|geom|\(a\+10\))\)(?: INVISIBLE)?))';
    foreach ($request['declarations'] as $ddl) {
        if (!is_string($ddl) || preg_match('/\ACREATE (?:TEMPORARY )?TABLE ' . VISIBILITY_NAME . ' \(' . $columns . $key . '{2,4}\) ENGINE=InnoDB\z/D', $ddl) !== 1) {
            return false;
        }
    }
    $edit = 'ALTER INDEX ' . VISIBILITY_KEY . ' (?:VISIBLE|INVISIBLE)';
    $option = '(?:ALGORITHM=(?:DEFAULT|COPY|INPLACE|INSTANT)|LOCK=(?:DEFAULT|NONE|SHARED|EXCLUSIVE))';
    $clause = '(?:' . $edit . '|' . $option . ')';
    return preg_match('/\AALTER TABLE ' . VISIBILITY_NAME . ' ' . $clause . '(?:, ' . $clause . '){0,11}\z/Di', $request['sql']) === 1;
}

function visibilityInsert(PDO $pdo, string $family, int $a, int $b, int $z): array
{
    $spatial = $family === 'spatial';
    $list = $spatial ? ' (a,b,z,payload,geom) VALUES (?,?,?,?,POINT(2,9))' : ' (a,b,z,payload) VALUES (?,?,?,?)';
    $insert = $pdo->prepare('INSERT INTO ' . VISIBILITY_RELATION . $list);
    try {
        $insert->execute([$a, $b, $z, 'fixture']);
        return ['executed', $insert->rowCount()];
    } catch (PDOException $error) {
        return ['db-error', $error->errorInfo[0], $error->errorInfo[1]];
    }
}

function prepareVisibility(PDO $catalog, PDO $owner, array $request): void
{
    $owner->exec('DROP TEMPORARY TABLE IF EXISTS ' . VISIBILITY_RELATION);
    $catalog->exec('DROP TABLE IF EXISTS ' . VISIBILITY_RELATION);
    foreach ($request['declarations'] as $ddl) {
        (str_starts_with($ddl, 'CREATE TEMPORARY') ? $owner : $catalog)->exec($ddl);
    }
    $catalog->exec('UPDATE ' . VISIBILITY_MARKER . ' SET n=0');
    $variant = $request['variant'];
    $persistent = visibilityInsert($catalog, $request['family'], 17 + $variant, 23 + $variant, 97 + $variant);
    $temporary = $request['temporary'] ? visibilityInsert($owner, $request['family'], 59 + $variant, 67 + $variant, 197 + $variant) : ['executed', 1];
    if ($persistent !== ['executed', 1] || $temporary !== ['executed', 1]) {
        throw new RuntimeException('Cannot populate the native visibility fixture.');
    }
}

function nativeVisibilityKeys(PDO $pdo, string $table): array
{
    $terms = [];
    foreach ($pdo->query('SHOW INDEX FROM ' . $table)->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $terms[] = [$row['Key_name'], $row['Index_type'], $row['Non_unique'], $row['Column_name'],
                    $row['Collation'], $row['Sub_part'], $row['Expression'], $row['Visible'], $row['Seq_in_index']];
    }
    usort($terms, static fn (array $left, array $right): int => strcmp($left[0], $right[0]) ?: $left[8] <=> $right[8]);

    return array_map(static fn (array $term): array => array_slice($term, 0, -1), $terms);
}

function visibilityCatalog(PDO $catalog, PDO $owner, bool $temporary): array
{
    return [
        'persistent' => nativeVisibilityKeys($catalog, VISIBILITY_RELATION),
        'temporary' => $temporary ? nativeVisibilityKeys($owner, VISIBILITY_RELATION) : null,
        'control' => nativeVisibilityKeys($catalog, VISIBILITY_CONTROL),
    ];
}

function observeIndexVisibility(PDO $catalog, PDO $owner, array $request): array
{
    if (!allowVisibilityRequest($request)) {
        return ['status' => 'outside-observer-scope'];
    }
    prepareVisibility($catalog, $owner, $request);
    $temporary = $request['temporary'];
    $settings = $owner->query("SELECT @@foreign_key_checks, IF(@@sql_generate_invisible_primary_key, 'ON', 'OFF')")->fetch(PDO::FETCH_NUM);
    $before = visibilityCatalog($catalog, $owner, $temporary);
    $effect = observeMysqlIndexEffect($catalog, $owner, VISIBILITY_MARKER, $request['sql']);
    $after = visibilityCatalog($catalog, $owner, $temporary);
    $n = $request['variant'];
    $unique = visibilityInsert($owner, $request['family'], ($temporary ? 59 : 17) + $n, ($temporary ? 67 : 23) + $n, 799 + $n);

    return ['status' => 'mysql-index-visibility', 'before' => $before, 'after' => $after, ...$effect, 'settings' => $settings,
            'data' => [
                $catalog->query('SELECT a,b FROM ' . VISIBILITY_RELATION . ' ORDER BY z')->fetch(PDO::FETCH_NUM),
                $temporary ? $owner->query('SELECT a,b FROM ' . VISIBILITY_RELATION . ' ORDER BY z')->fetch(PDO::FETCH_NUM) : null,
            ],
            'uniqueness' => $unique];
}

runMysqlIndexObserver($argv, initializeVisibility(...), observeIndexVisibility(...));
