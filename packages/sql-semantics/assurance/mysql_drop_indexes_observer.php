<?php

declare(strict_types=1);

require_once __DIR__ . '/mysql_drop_index_observer.php';

const BATCH_INDEX_TABLE = 'ir_fixture.ir_index_set';
const BATCH_INDEX_MARKER = 'ir_fixture.ir_index_set_marker';

function initIndexRemovalSet(PDO $catalog): void
{
    $catalog->exec('DROP TABLE IF EXISTS ' . BATCH_INDEX_MARKER);
    $catalog->exec('CREATE TABLE ' . BATCH_INDEX_MARKER . ' (n INT NOT NULL) ENGINE=InnoDB');
    $catalog->exec('INSERT INTO ' . BATCH_INDEX_MARKER . ' VALUES (0)');
}

function batchIndexSql(string $sql): bool
{
    $table = str_replace('%', chr(96), '%ir_fixture%.%ir_index_set%');
    $action = 'DROP PRIMARY KEY|DROP (?:INDEX|KEY) ' . DROP_INDEX_NAME;
    $pattern = '/\AALTER (?:IGNORE )?TABLE ' . $table . ' (?&item)(?:, (?&item)){1,65}\z'
        . '(?(DEFINE)(?<item>' . $action . '|' . ltrim(MYSQL_INDEX_ALGORITHM) . '|' . ltrim(MYSQL_INDEX_LOCK) . '))/Du';
    if (strlen($sql) > 18000) {
        return false;
    }
    $match = preg_match($pattern, $sql);
    if ($match === false) {
        throw new RuntimeException('Atomic index SQL admission failed: ' . preg_last_error_msg());
    }

    return $match === 1;
}

function batchIndexFixture(string $sql): bool
{
    $table = str_replace('%', chr(96), '%ir_fixture%.%ir_index_set%');
    $key = '(?:PRIMARY KEY|(?:UNIQUE )?KEY ' . DROP_INDEX_NAME . ') \([abc](?: DESC)?(?:, [abc])?\)(?: INVISIBLE)?';
    $pattern = '/\ACREATE (?:TEMPORARY )?TABLE ' . $table . ' \(a INT NOT NULL(?: AUTO_INCREMENT)?, b INT NOT NULL, c INT NULL'
        . '(?:, (?&key)){2,64}\) ENGINE=InnoDB\z(?(DEFINE)(?<key>' . $key . '))/Du';
    if (strlen($sql) > 18000) {
        return false;
    }
    $match = preg_match($pattern, $sql);
    if ($match === false) {
        throw new RuntimeException('Atomic index fixture admission failed: ' . preg_last_error_msg());
    }

    return $match === 1;
}

function prepareBatchIndexFixture(PDO $catalog, PDO $owner, array $request): void
{
    $owner->exec('DROP TEMPORARY TABLE IF EXISTS ' . BATCH_INDEX_TABLE);
    $catalog->exec('DROP TABLE IF EXISTS ' . BATCH_INDEX_TABLE);
    $catalog->exec('UPDATE ' . BATCH_INDEX_MARKER . ' SET n = 0');

    foreach ($request['declarations'] as $statement) {
        $temporary = str_starts_with($statement, 'CREATE TEMPORARY');
        $receiver = $temporary ? $owner : $catalog;
        $receiver->exec($statement);
        $insert = $receiver->prepare('INSERT INTO ' . BATCH_INDEX_TABLE . ' VALUES (?, ?, ?)');
        foreach ($request['rows'][$temporary ? 'temporary' : 'persistent'] as $row) {
            $insert->execute($row);
        }
    }
}

function observeNativeIndexChange(PDO $catalog, PDO $owner, array $request, Closure $admits, string $status): array
{
    $fixtures = $request['declarations'];
    if (!$admits($request['sql']) || !is_bool($request['generated']) || count($fixtures) < 1 || count($fixtures) > 2
        || count(array_filter($fixtures, batchIndexFixture(...))) !== count($fixtures)) {
        return ['status' => 'outside-observer-scope'];
    }

    prepareBatchIndexFixture($catalog, $owner, $request);
    $before = dropIndexState($owner, 'a', BATCH_INDEX_TABLE);
    $effect = observeMysqlIndexEffect($catalog, $owner, BATCH_INDEX_MARKER, $request['sql']);
    $after = dropIndexState($owner, 'a', BATCH_INDEX_TABLE);
    $generated = $advanced = null;
    if ($request['generated']) {
        $owner->exec('INSERT INTO ' . BATCH_INDEX_TABLE . ' (b,c) VALUES (79,5)');
        $generated = $owner->lastInsertId();
        $advanced = dropIndexState($owner, 'a', BATCH_INDEX_TABLE);
    }

    return ['status' => $status, 'initial' => $before, ...$effect, 'after' => $after,
        'afterGenerated' => $advanced, 'catalog' => dropIndexState($catalog, 'a', BATCH_INDEX_TABLE), 'generated' => $generated];
}

function observeIndexRemovalSet(PDO $catalog, PDO $owner, array $request): array
{
    return observeNativeIndexChange($catalog, $owner, $request, batchIndexSql(...), 'mysql-index-removal-set');
}

if (realpath($argv[0] ?? '') === __FILE__) {
    runMysqlIndexObserver($argv, initIndexRemovalSet(...), observeIndexRemovalSet(...));
}
