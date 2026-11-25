<?php

declare(strict_types=1);

require_once __DIR__ . '/mysql_drop_index_observer.php';

const AUTO_INDEX_TABLE = 'ir_fixture.ir_index_sequence';
const AUTO_INDEX_TEMPLATE = 'ir_fixture.ir_index_sequence_template';
const AUTO_INDEX_MARKER = 'ir_fixture.ir_index_sequence_marker';

function initIndexAutoIncrement(PDO $catalog): void
{
    $catalog->exec('DROP TABLE IF EXISTS ' . AUTO_INDEX_MARKER);
    $catalog->exec('CREATE TABLE ' . AUTO_INDEX_MARKER . ' (n INT NOT NULL) ENGINE=InnoDB');
    $catalog->exec('INSERT INTO ' . AUTO_INDEX_MARKER . ' VALUES (0)');
}

function autoIndexSql(string $sql): bool
{
    $name = DROP_INDEX_NAME;
    $table = '%ir_fixture%.%ir_index_sequence%';
    $command = '/\A(?:DROP INDEX ' . $name . ' ON ' . $table
        . '|ALTER TABLE ' . $table . ' DROP (?:PRIMARY KEY|KEY ' . $name . '))\z/Du';

    return strlen($sql) <= 600 && preg_match(str_replace('%', chr(96), $command), $sql) === 1;
}

function autoIndexFixture(string $sql): bool
{
    $table = '%ir_fixture%.%ir_index_sequence%';
    $template = '%ir_fixture%.%ir_index_sequence_template%';
    $columns = 'a (?:INT NOT NULL AUTO_INCREMENT|SERIAL|INT SERIAL DEFAULT VALUE), b INT NOT NULL, c INT NULL';
    $key = '(?:PRIMARY KEY|(?:UNIQUE )?KEY ' . DROP_INDEX_NAME . ') \([abc](?: DESC)?(?:, [abc])?\)(?: INVISIBLE)?';
    $ddl = '/\ACREATE (?:TEMPORARY )?TABLE (?:(?:' . $table . '|' . $template . ') \(' . $columns
        . '(?:, (?&key)){1,8}\) ENGINE=InnoDB AUTO_INCREMENT=101|' . $table . ' LIKE ' . $template . ')\z(?(DEFINE)(?<key>' . $key . '))/Du';
    if (strlen($sql) > 1400) {
        return false;
    }
    $match = preg_match(str_replace('%', chr(96), $ddl), $sql);
    if ($match === false) {
        throw new RuntimeException('AUTO_INCREMENT fixture admission failed: ' . preg_last_error_msg());
    }

    return $match === 1;
}

function prepareAutoIndexFixture(PDO $catalog, PDO $owner, array $request): void
{
    $owner->exec('DROP TEMPORARY TABLE IF EXISTS ' . AUTO_INDEX_TABLE);
    $catalog->exec('DROP TABLE IF EXISTS ' . AUTO_INDEX_TABLE);
    $catalog->exec('DROP TABLE IF EXISTS ' . AUTO_INDEX_TEMPLATE);
    $catalog->exec('UPDATE ' . AUTO_INDEX_MARKER . ' SET n = 0');

    $templateHead = 'CREATE TABLE ' . dropIndexQuote('ir_fixture') . '.' . dropIndexQuote('ir_index_sequence_template') . ' (';
    foreach ($request['declarations'] as $ddl) {
        $temporary = str_starts_with($ddl, 'CREATE TEMPORARY');
        $target = $temporary ? $owner : $catalog;
        $target->exec($ddl);
        if (!str_starts_with($ddl, $templateHead)) {
            $insert = $target->prepare('INSERT INTO ' . AUTO_INDEX_TABLE . ' (a,b,c) VALUES (?, ?, ?)');
            foreach ($request['rows'][$temporary ? 'temporary' : 'persistent'] as $row) {
                $insert->execute($row);
            }
        }
    }
}

function observeIndexAutoIncrement(PDO $catalog, PDO $owner, array $request): array
{
    if (!autoIndexSql($request['sql']) || $request['declarations'] === []
        || count($request['declarations']) > 3 || count(array_filter($request['declarations'], autoIndexFixture(...))) !== count($request['declarations'])) {
        return ['status' => 'outside-observer-scope'];
    }

    prepareAutoIndexFixture($catalog, $owner, $request);
    $before = dropIndexState($owner, 'a', AUTO_INDEX_TABLE);
    $effect = observeMysqlIndexEffect($catalog, $owner, AUTO_INDEX_MARKER, $request['sql']);
    $after = dropIndexState($owner, 'a', AUTO_INDEX_TABLE);
    $owner->exec('INSERT INTO ' . AUTO_INDEX_TABLE . ' (b,c) VALUES (79,5)');
    $generated = $owner->lastInsertId();

    return ['status' => 'mysql-index-auto-increment', 'initial' => $before, ...$effect, 'after' => $after,
        'afterGenerated' => dropIndexState($owner, 'a', AUTO_INDEX_TABLE),
        'catalog' => dropIndexState($catalog, 'a', AUTO_INDEX_TABLE), 'generated' => $generated];
}

runMysqlIndexObserver($argv, initIndexAutoIncrement(...), observeIndexAutoIncrement(...));
