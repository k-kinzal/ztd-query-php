<?php

declare(strict_types=1);

require __DIR__ . '/server_connection.php';
require __DIR__ . '/mysql_varchar_write.php';
require __DIR__ . '/mysql_table_effects.php';

if ($argv[1] !== 'mysql') {
    throw new RuntimeException('The session declaration observer requires MySQL.');
}
[$catalog, $environment] = connectFixture('mysql');
if ($catalog->query('SELECT DATABASE()')->fetchColumn() !== 'ir_fixture') {
    throw new RuntimeException('The session declaration observer requires the disposable ir_fixture database.');
}

const CREATE_PRIMARY = 'ir_fixture.ir_screate_data';
const CREATE_DECOY = 'ir_screate_decoy.ir_screate_data';
const CREATE_MARKER = 'ir_fixture.ir_screate_marker';
const CREATE_STORAGE = ' ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin';

$catalog->exec('CREATE DATABASE IF NOT EXISTS ir_screate_decoy CHARACTER SET utf8mb4 COLLATE utf8mb4_bin');

function admitSessionCreate(string $sql): bool
{
    $database = '(?:`(?:ir_fixture|ir_screate_decoy)`|ir_fixture|ir_screate_decoy)';
    $table = '(?:`ir_screate_data`|ir_screate_data)';
    $identifier = '(?:`n[0-7]`|n[0-7])';
    $string = <<<'REGEX'
'(?:\\[\s\S]|''|[^'\\])*'
REGEX;
    $column = $identifier . ' (?:TINYINT|SMALLINT|MEDIUMINT|INTEGER|INT|BIGINT|VARCHAR\([1-8]\)'
        . '(?: CHARACTER SET utf8mb4)?(?: COLLATE utf8mb4_bin)?)(?: NULL| NOT NULL)?'
        . '(?: DEFAULT (?:NULL|-?[0-9]{1,19}|' . $string . '))?';
    $part = $identifier . '(?: ASC| DESC)?';
    $key = '(?:, PRIMARY KEY \(' . $part . '(?:, ' . $part . '){0,7}\))?';
    $engine = '(?: ENGINE=InnoDB)?(?: DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin)?';

    return preg_match('/\ACREATE (?:TEMPORARY )?TABLE (?:IF NOT EXISTS )?(?:' . $database . '\.)?' . $table
        . ' \(' . $column . '(?:, ' . $column . '){0,7}' . $key . '\)' . $engine . '\z/D', $sql) === 1;
}

function resetSessionCreate(PDO $catalog, PDO $owner, string $target, string $fixture, int $variant): void
{
    foreach ([CREATE_PRIMARY, CREATE_DECOY] as $name) {
        $definition = mysqlFixtureTableDefinition($catalog, $name);
        if ($definition !== null) {
            $catalog->exec((isset($definition['Create View']) ? 'DROP VIEW ' : 'DROP TABLE ') . $name);
        }
    }
    $catalog->exec('DROP TABLE IF EXISTS ' . CREATE_MARKER);
    $catalog->exec('CREATE TABLE ' . CREATE_MARKER . ' (n INT) ENGINE=InnoDB');
    $catalog->exec('INSERT INTO ' . CREATE_MARKER . ' VALUES (0)');
    foreach ([CREATE_PRIMARY => 11, CREATE_DECOY => 41] as $name => $base) {
        if ($name !== $target || in_array($fixture, ['base', 'shadow-base'], true)) {
            $catalog->exec('CREATE TABLE ' . $name . ' (existing INT NULL)' . CREATE_STORAGE);
            $catalog->exec('INSERT INTO ' . $name . ' VALUES (NULL), (' . ($base + $variant) . ')');
        } elseif (in_array($fixture, ['view', 'shadow-view'], true)) {
            $catalog->exec('CREATE VIEW ' . $name . ' AS SELECT CAST(' . ($base + $variant + 60) . ' AS SIGNED) AS marker');
        }
    }
    if (in_array($fixture, ['temporary', 'shadow-base', 'shadow-view'], true)) {
        $owner->exec('CREATE TEMPORARY TABLE ' . $target . ' (local_value SMALLINT NULL DEFAULT 9)' . CREATE_STORAGE);
        $owner->exec('INSERT INTO ' . $target . ' VALUES (NULL), (' . (120 + $variant) . ')');
    }
}

function sessionCreateState(PDO $owner, PDO $catalog): array
{
    $visible = $permanent = [];
    foreach ([CREATE_PRIMARY, CREATE_DECOY] as $name) {
        $visible[$name] = observeMysqlFixtureTable($owner, $name);
        $permanent[$name] = observeMysqlFixtureTable($catalog, $name);
    }
    $marker = 'SELECT n FROM ' . CREATE_MARKER . ' ORDER BY n';

    return ['visible' => $visible, 'permanent' => $permanent, 'marker' => $owner->query($marker)->fetchAll(PDO::FETCH_COLUMN),
        'committedMarker' => $catalog->query($marker)->fetchAll(PDO::FETCH_COLUMN)];
}

function observeSessionCreate(PDO $catalog, array $request): array
{
    ['sql' => $sql, 'target' => $target, 'fixture' => $fixture, 'kind' => $kind, 'variant' => $variant] = $request;
    if (!admitSessionCreate($sql) || !in_array($target, [CREATE_PRIMARY, CREATE_DECOY], true)
        || !in_array($fixture, ['absent', 'base', 'view', 'temporary', 'shadow-base', 'shadow-view'], true)
        || !in_array($kind, ['table', 'temporary-table'], true) || !is_int($variant) || $variant < 0 || $variant > 40) {
        return ['status' => 'outside-observer-scope'];
    }
    $owner = mysqlFixtureOwner();
    resetSessionCreate($catalog, $owner, $target, $fixture, $variant);
    $initial = sessionCreateState($owner, $catalog);
    $owner->beginTransaction();
    $owner->exec('INSERT INTO ' . CREATE_MARKER . ' VALUES (7)');
    $effect = applyMysqlFixtureWrite($owner, $sql);
    $diagnostics = mysqlFixtureDiagnostics($owner);
    $owner->query('SELECT 1')->fetchColumn();
    $active = $owner->inTransaction();
    $temporary = $kind === 'temporary-table';
    $insert = 'INSERT INTO ' . $target . ' () VALUES ()';
    $fresh = $effect === ['executed', 0] && $diagnostics['warningCount'] === 0;
    $provisional = $fresh && $temporary ? applyMysqlFixtureWrite($owner, $insert) : null;
    $after = sessionCreateState($owner, $catalog);
    if ($active) {
        $owner->rollBack();
    } else {
        $owner->exec('ROLLBACK');
    }
    $restored = sessionCreateState($owner, $catalog);
    $defaults = null;
    if ($fresh) {
        $first = applyMysqlFixtureWrite($temporary ? $owner : $catalog, $insert);
        $second = applyMysqlFixtureWrite($temporary ? $owner : $catalog, $insert);
        $defaults = ['writes' => [$first, $second], 'state' => sessionCreateState($owner, $catalog)];
    }
    $owner = null;
    $reconnected = mysqlFixtureOwner();

    return ['status' => 'mysql-session-create', 'initial' => $initial, 'effect' => $effect, ...$diagnostics, 'active' => $active,
        'provisionalWrite' => $provisional, 'after' => $after, 'restored' => $restored, 'defaults' => $defaults,
        'afterDisconnect' => sessionCreateState($reconnected, $catalog)];
}

$owner = mysqlFixtureOwner();
resetSessionCreate($catalog, $owner, CREATE_PRIMARY, 'shadow-view', 0);
$initial = sessionCreateState($owner, $catalog);
$owner = null;
echo json_encode(['database' => $environment, 'php' => PHP_VERSION, 'initial' => $initial], JSON_THROW_ON_ERROR) . "\n";
while (($line = fgets(STDIN)) !== false) {
    $request = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
    $batch = array_is_list($request);
    $observed = array_map(static fn (array $input): array => observeSessionCreate($catalog, $input), $batch ? $request : [$request]);
    echo json_encode($batch ? $observed : $observed[0], JSON_THROW_ON_ERROR) . "\n";
}
