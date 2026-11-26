<?php

declare(strict_types=1);

require_once __DIR__ . '/server_connection.php';
require_once __DIR__ . '/mysql_varchar_write.php';
require_once __DIR__ . '/mysql_table_effects.php';

const MYSQL_INDEX_ALGORITHM = ' ALGORITHM(?:=| )(?:DEFAULT|INPLACE|COPY|INSTANT)';
const MYSQL_INDEX_LOCK = ' LOCK(?:=| )(?:DEFAULT|NONE|SHARED|EXCLUSIVE)';
const MYSQL_INDEX_POLICY = '(?:' . MYSQL_INDEX_ALGORITHM . '(?:' . MYSQL_INDEX_LOCK . ')?|'
    . MYSQL_INDEX_LOCK . '(?:' . MYSQL_INDEX_ALGORITHM . ')?)?';

function runMysqlIndexObserver(array $args, Closure $initialize, Closure $observe): void
{
    if (($args[1] ?? '') !== 'mysql') {
        throw new RuntimeException('Native index observations require a MySQL fixture.');
    }
    [$catalog, $environment] = connectFixture('mysql');
    if ($catalog->query('SELECT DATABASE()')->fetchColumn() !== 'ir_fixture') {
        throw new RuntimeException('Use the disposable ir_fixture database.');
    }
    $owner = mysqlFixtureOwner();
    $initialize($catalog);
    echo json_encode(['database' => $environment, 'php' => PHP_VERSION], JSON_THROW_ON_ERROR) . "\n";
    while (($line = fgets(STDIN)) !== false) {
        $request = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
        $batch = array_is_list($request);
        $observed = array_map(static fn (array $item): array => $observe($catalog, $owner, $item), $batch ? $request : [$request]);
        echo json_encode($batch ? $observed : $observed[0], JSON_THROW_ON_ERROR) . "\n";
    }
}

/** Executes one whitelisted DDL statement and measures its transaction boundary before clearing diagnostics. */
function observeMysqlIndexEffect(PDO $catalog, PDO $owner, string $markerTable, string $sql): array
{
    $owner->beginTransaction();
    $owner->exec('UPDATE ' . $markerTable . ' SET n = 7');
    $effect = applyMysqlFixtureWrite($owner, $sql);
    $diagnostics = mysqlFixtureDiagnostics($owner);
    $owner->query('SELECT 1')->fetchColumn();
    $active = $owner->inTransaction();
    // A current transaction still owns the uncommitted marker. A consistent read on the other connection sees zero.
    $marker = $catalog->query('SELECT n FROM ' . $markerTable)->fetchColumn();
    if ($active) {
        $owner->rollBack();
    }

    return ['effect' => $effect[0] === 'executed' ? ['executed'] : $effect,
        'affected' => $effect[0] === 'executed' ? $effect[1] : null, 'diagnostics' => $diagnostics['diagnostics'],
        'marker' => $marker, 'active' => $active];
}
