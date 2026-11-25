<?php

declare(strict_types=1);

function mysqlFixtureTableDefinition(PDO $pdo, string $name): ?array
{
    try {
        return $pdo->query('SHOW CREATE TABLE ' . $name)->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $error) {
        if ($error->errorInfo[0] !== '42S02' || $error->errorInfo[1] !== 1146) {
            throw $error;
        }
        return null;
    }
}

function observeMysqlFixtureTable(PDO $pdo, string $name, bool $orderedRows = true): ?array
{
    $definition = mysqlFixtureTableDefinition($pdo, $name);
    if ($definition === null) {
        return null;
    }
    if (isset($definition['Create View'])) {
        return ['kind' => 'view', 'columns' => $pdo->query('SHOW COLUMNS FROM ' . $name)->fetchAll(PDO::FETCH_COLUMN),
            'rows' => $pdo->query('SELECT * FROM ' . $name)->fetchAll(PDO::FETCH_NUM)];
    }
    $fields = $pdo->query('SHOW FULL COLUMNS FROM ' . $name)->fetchAll(PDO::FETCH_ASSOC);

    return observeMysqlFixtureBaseTable($pdo, $name, $definition['Create Table'], $fields, $orderedRows);
}

function observeMysqlFixtureBaseTable(PDO $pdo, string $name, string $ddl, array $fields, bool $orderedRows = true): array
{
    $kind = str_starts_with($ddl, 'CREATE TEMPORARY TABLE ') ? 'temporary-table' : 'table';
    if (preg_match('/ ENGINE=([A-Za-z0-9]+)(?: AUTO_INCREMENT=[1-9][0-9]*)? DEFAULT CHARSET=utf8mb4 COLLATE=(utf8mb4_[a-z0-9_]+)/', $ddl, $storage) !== 1) {
        throw new RuntimeException('Unrecognized native table storage: ' . $ddl);
    }
    $columns = [];
    foreach ($fields as $column) {
        $columns[] = [$column['Field'], $column['Type'], $column['Null'] === 'YES', $column['Default'], $column['Collation']];
    }
    $key = [];
    foreach ($pdo->query('SHOW INDEX FROM ' . $name)->fetchAll(PDO::FETCH_ASSOC) as $term) {
        $key[] = [$term['Key_name'], $term['Non_unique'], $term['Seq_in_index'], $term['Column_name'], $term['Collation'], $term['Index_type']];
    }
    $order = $orderedRows ? ' ORDER BY ' . implode(', ', range(1, count($columns))) : '';
    $rows = $pdo->query('SELECT * FROM ' . $name . $order)->fetchAll(PDO::FETCH_NUM);

    return ['kind' => $kind, 'engine' => $storage[1], 'collation' => $storage[2], 'columns' => $columns, 'key' => $key, 'rows' => $rows];
}

function applyMysqlFixtureWrite(PDO $pdo, string $sql): array
{
    try {
        return ['executed', $pdo->exec($sql)];
    } catch (PDOException $error) {
        return ['db-error', $error->errorInfo[0], $error->errorInfo[1]];
    }
}

function mysqlFixtureOwner(): PDO
{
    [$owner] = connectFixture('mysql');
    $owner->exec("SET SESSION default_storage_engine = 'InnoDB', default_tmp_storage_engine = 'InnoDB', sql_notes = 1, max_error_count = 64, lc_messages = 'en_US'");

    return $owner;
}
