<?php

declare(strict_types=1);

require __DIR__ . '/server_connection.php';
require __DIR__ . '/mysql_varchar_write.php';
require __DIR__ . '/mysql_table_effects.php';

const LIKE_SCHEMAS = ['ir_fixture', 'ir_slike_decoy'];
const LIKE_SOURCE = 'ir_slike_original';
const LIKE_TARGET = 'ir_slike_copy';
const LIKE_PARENT = 'ir_fixture.ir_slike_parent';
const LIKE_MARKER = 'ir_fixture.ir_slike_marker';
const LIKE_STORAGE = ' ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin';

function admitCreateLike(string $sql): bool
{
    $database = '(?:(?:`(?:ir_fixture|ir_slike_decoy)`|ir_fixture|ir_slike_decoy)\.)?';
    $source = $database . '(?:`ir_slike_original`|ir_slike_original)';
    $target = $database . '(?:`ir_slike_copy`|ir_slike_copy)';

    return preg_match('/\ACREATE (?:TEMPORARY )?TABLE (?:IF NOT EXISTS )?' . $target
        . ' (?:LIKE ' . $source . '|\(LIKE ' . $source . '\))\z/D', $sql) === 1;
}

function prepareLikeSource(PDO $pdo, string $schema, bool $temporary, int $ordinal, int $variant): void
{
    $name = $schema . '.' . LIKE_SOURCE;
    $base = 100 + 100 * $ordinal + ($temporary ? 300 : 0);
    $fid = $temporary ? 'SMALLINT' : 'INT';
    $payload = $variant % 2 === 0 ? 'BIGINT' : 'SMALLINT';
    $parts = $variant % 2 === 0 ? '(fid, payload)' : '(fid)';
    $prefix = $temporary ? 'CREATE TEMPORARY TABLE ' : 'CREATE TABLE ';
    $link = $temporary ? 'KEY copy_key ' . $parts : 'UNIQUE KEY copy_key ' . $parts . ', FOREIGN KEY (fid) REFERENCES ' . LIKE_PARENT . ' (n)';
    $pdo->exec($prefix . $name . ' (id INT NOT NULL AUTO_INCREMENT, fid ' . $fid . ' NOT NULL DEFAULT ' . ($base + $variant)
        . ', payload ' . $payload . ' NULL DEFAULT ' . (-$base + $variant) . ', PRIMARY KEY (id), ' . $link . ')'
        . LIKE_STORAGE . ' AUTO_INCREMENT=' . ($base + 1));
    for ($index = 0; $index < 2 + intdiv($variant, 8); $index++) {
        $pdo->exec('INSERT INTO ' . $name . ' (fid, payload) VALUES (' . ($base + 20 + $index) . ', '
            . ($index === 0 ? 'NULL' : (string) ($base + $index)) . ')');
    }
}

function resetLikeFixture(PDO $catalog, PDO $owner, array $request): void
{
    ['sourceSchema' => $source, 'destinationSchema' => $target, 'sourceTopology' => $sources,
        'destinationTopology' => $targets, 'variant' => $variant] = $request;
    foreach (LIKE_SCHEMAS as $schema) {
        $catalog->exec('DROP TABLE IF EXISTS ' . $schema . '.' . LIKE_SOURCE . ', ' . $schema . '.' . LIKE_TARGET);
    }
    $catalog->exec('DROP TABLE IF EXISTS ' . LIKE_PARENT . ', ' . LIKE_MARKER);
    $catalog->exec('CREATE TABLE ' . LIKE_PARENT . ' (n INT PRIMARY KEY)' . LIKE_STORAGE);
    $catalog->exec('CREATE TABLE ' . LIKE_MARKER . ' (n INT PRIMARY KEY)' . LIKE_STORAGE);
    $catalog->exec('INSERT INTO ' . LIKE_MARKER . ' VALUES (0)');
    foreach (LIKE_SCHEMAS as $ordinal => $schema) {
        $base = 100 + 100 * $ordinal;
        $catalog->exec('INSERT INTO ' . LIKE_PARENT . ' VALUES (' . ($base + $variant) . '), ('
            . ($base + 20) . '), (' . ($base + 21) . '), (' . ($base + 22) . ')');
        if ($schema !== $source || $sources !== 'temporary-only') {
            prepareLikeSource($catalog, $schema, false, $ordinal, $variant);
        }
        if ($schema === $source && $sources !== 'permanent') {
            prepareLikeSource($owner, $schema, true, $ordinal, $variant);
        }
        $destination = $schema . '.' . LIKE_TARGET;
        if ($schema !== $target || in_array($targets, ['persistent', 'shadow'], true)) {
            $catalog->exec('CREATE TABLE ' . $destination . ' (existing INT NULL)' . LIKE_STORAGE);
            $catalog->exec('INSERT INTO ' . $destination . ' VALUES (NULL), (' . ($base + 61) . ')');
        }
        if ($schema === $target && in_array($targets, ['temporary', 'shadow'], true)) {
            $owner->exec('CREATE TEMPORARY TABLE ' . $destination . ' (local_value BIGINT NULL)' . LIKE_STORAGE);
            $owner->exec('INSERT INTO ' . $destination . ' VALUES (NULL), (' . ($base + 79) . ')');
        }
    }
}

function observeLikeTable(PDO $pdo, string $name): ?array
{
    $definition = mysqlFixtureTableDefinition($pdo, $name);
    if ($definition === null) {
        return null;
    }
    $ddl = $definition['Create Table'];
    $columns = $pdo->query('SHOW FULL COLUMNS FROM ' . $name)->fetchAll(PDO::FETCH_ASSOC);
    $relation = observeMysqlFixtureBaseTable($pdo, $name, $ddl, $columns);
    $allocated = [];
    foreach ($columns as $column) {
        if ($column['Extra'] === 'auto_increment') {
            $allocated[] = $column['Field'];
        }
    }
    $relation['allocatedColumns'] = $allocated;
    $relation['foreignKeys'] = preg_match_all('/^  CONSTRAINT [^\n]* FOREIGN KEY /m', $ddl);

    return $relation;
}

function likeState(PDO $owner, PDO $catalog): array
{
    $visible = $permanent = [];
    foreach (LIKE_SCHEMAS as $schema) {
        foreach ([LIKE_SOURCE, LIKE_TARGET] as $table) {
            $name = $schema . '.' . $table;
            $visible[$name] = observeLikeTable($owner, $name);
            $permanent[$name] = observeLikeTable($catalog, $name);
        }
    }
    $marker = 'SELECT n FROM ' . LIKE_MARKER . ' ORDER BY n';

    return ['visible' => $visible, 'permanent' => $permanent, 'marker' => $owner->query($marker)->fetchAll(PDO::FETCH_COLUMN),
        'committedMarker' => $catalog->query($marker)->fetchAll(PDO::FETCH_COLUMN)];
}

function probeLikeInsert(PDO $connection, string $sql): array
{
    $result = applyMysqlFixtureWrite($connection, $sql);

    return $result === ['executed', 1] ? [...$result, $connection->lastInsertId()] : $result;
}

function observeCreateLike(PDO $catalog, array $request): array
{
    ['sql' => $sql, 'sourceSchema' => $source, 'destinationSchema' => $target, 'sourceTopology' => $sources,
        'destinationTopology' => $targets, 'destinationKind' => $kind, 'variant' => $variant] = $request;
    if (!admitCreateLike($sql) || !in_array($source, LIKE_SCHEMAS, true) || !in_array($target, LIKE_SCHEMAS, true)
        || !in_array($sources, ['permanent', 'temporary-only', 'shadow'], true)
        || !in_array($targets, ['absent', 'persistent', 'temporary', 'shadow'], true)
        || !in_array($kind, ['table', 'temporary-table'], true) || !is_int($variant) || $variant < 0 || $variant > 15) {
        return ['status' => 'outside-observer-scope'];
    }
    $owner = mysqlFixtureOwner();
    $owner->exec('SET SESSION foreign_key_checks = 1, auto_increment_increment = 1, auto_increment_offset = 1');
    resetLikeFixture($catalog, $owner, $request);
    $initial = likeState($owner, $catalog);
    $owner->beginTransaction();
    $owner->exec('INSERT INTO ' . LIKE_MARKER . ' VALUES (7)');
    $effect = applyMysqlFixtureWrite($owner, $sql);
    $diagnostics = mysqlFixtureDiagnostics($owner);
    $owner->query('SELECT 1')->fetchColumn();
    $active = $owner->inTransaction();
    $afterDdl = likeState($owner, $catalog);
    $name = $target . '.' . LIKE_TARGET;
    $default = 'INSERT INTO ' . $name . ' () VALUES ()';
    $fresh = $effect === ['executed', 0] && $diagnostics['warningCount'] === 0;
    $temporary = $kind === 'temporary-table';
    $provisional = $fresh && $temporary ? probeLikeInsert($owner, $default) : null;
    $beforeRollback = $fresh && $temporary ? likeState($owner, $catalog) : $afterDdl;
    if ($active) {
        $owner->rollBack();
    } else {
        $owner->exec('ROLLBACK');
    }
    $restored = likeState($owner, $catalog);
    $writes = null;
    if ($fresh) {
        $actor = $temporary ? $owner : $catalog;
        $writes = [
            'defaults' => probeLikeInsert($actor, $default),
            'unreferenced' => probeLikeInsert($actor, 'INSERT INTO ' . $name . ' (fid) VALUES (999)'),
            'state' => likeState($owner, $catalog),
        ];
        unset($actor);
    }
    $rejectsUnreferenced = $sources === 'permanent'
        ? probeLikeInsert($catalog, 'INSERT INTO ' . $source . '.' . LIKE_SOURCE . ' (fid) VALUES (999)') : null;
    $owner = null;

    return ['status' => 'mysql-create-like', 'initial' => $initial, 'effect' => $effect, ...$diagnostics, 'active' => $active,
        'afterDdl' => $afterDdl, 'provisionalWrite' => $provisional, 'beforeRollback' => $beforeRollback,
        'restored' => $restored, 'writes' => $writes, 'originalForeignKeyProbe' => $rejectsUnreferenced,
        'afterDisconnect' => likeState(mysqlFixtureOwner(), $catalog)];
}

if ($argv[1] !== 'mysql') {
    throw new RuntimeException('The definition-copy observer requires MySQL.');
}
[$catalog, $environment] = connectFixture('mysql');
if ($catalog->query('SELECT DATABASE()')->fetchColumn() !== LIKE_SCHEMAS[0]) {
    throw new RuntimeException('The definition-copy observer requires the disposable fixture database.');
}
$catalog->exec('CREATE DATABASE IF NOT EXISTS ir_slike_decoy CHARACTER SET utf8mb4 COLLATE utf8mb4_bin');
$catalog->exec('SET SESSION foreign_key_checks = 1, auto_increment_increment = 1, auto_increment_offset = 1');
echo json_encode(['database' => $environment, 'php' => PHP_VERSION], JSON_THROW_ON_ERROR) . "\n";
while (($line = fgets(STDIN)) !== false) {
    $request = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
    $batch = array_is_list($request);
    $observed = array_map(static fn (array $input): array => observeCreateLike($catalog, $input), $batch ? $request : [$request]);
    echo json_encode($batch ? $observed : $observed[0], JSON_THROW_ON_ERROR) . "\n";
}
