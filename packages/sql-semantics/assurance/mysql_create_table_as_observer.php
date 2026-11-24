<?php

declare(strict_types=1);

require __DIR__ . '/server_connection.php';
require __DIR__ . '/mysql_varchar_write.php';
require __DIR__ . '/mysql_table_effects.php';
require __DIR__ . '/mysql_create_table_as_source_fixture.php';

const CTAS_PRIMARY = 'ir_fixture.ir_create_target';
const CTAS_DECOY = 'ir_ctas_decoy.ir_create_target';
const CTAS_MARKER = 'ir_fixture.ir_ctas_marker';

function admitMysqlCreateAs(string $sql, bool $withInput, bool $withDefinitions): bool
{
    $schema = '(?:`(?:ir_fixture|ir_ctas_decoy)`|ir_fixture|ir_ctas_decoy)';
    $target = '(?:`ir_create_target`|ir_create_target)';
    $literal = '(?:-?[0-9]{1,10}|NULL|TRUE|FALSE)';
    $label = '(?:`(?:[a-zA-Z0-9_ ]|``){1,66}`|[a-zA-Z_][a-zA-Z0-9_]*)';
    $reference = '(?:(?:`[a-zA-Z_][a-zA-Z0-9_]*`|[a-zA-Z_][a-zA-Z0-9_]*)\.){0,2}(?:`[nmvzw]`|[nmvzw])';
    $field = '(?:' . $literal . '|CAST\(' . $literal . ' AS SIGNED\)' . ($withInput ? '|' . $reference : '') . ')(?: AS ' . $label . ')?';
    $source = '(?: FROM (?:DUAL|\(SELECT 1 AS `__ztd_unit`\) AS `__ztd_single`))?';
    $predicate = '(?:TRUE|FALSE|NULL)';
    if ($withInput) {
        $name = '(?:`(?:ir_ctas_source|ir_create_target|known)`|ir_ctas_source|ir_create_target|known)';
        $source = ' FROM (?:' . $schema . '\.)?' . $name . '(?: AS ' . $label . ')?';
        $integer = '(?:' . $reference . '|-?[0-9]{1,10})';
        $comparison = $integer . ' (?:<=|>=|<>|!=|=|<|>) ' . $integer;
        $null = $reference . ' IS (?:NOT )?NULL';
        $predicate = '(?:TRUE|FALSE|NULL|' . $comparison . '|' . $null . '|\((?:' . $comparison . '|' . $null . ')\))';
    }
    $query = 'SELECT (?:DISTINCT )?(?&field)(?:, ?(?&field)){0,127}' . $source
        . '(?: WHERE ' . $predicate . ')?(?: ORDER BY [0-9]{1,3}(?: ASC| DESC)?'
        . '(?:, [0-9]{1,3}(?: ASC| DESC)?)*)?(?: LIMIT [0-9]{1,10}(?:(?: OFFSET |, ?)[0-9]{1,10})?)?';

    $declared = $label . ' (?:TINYINT|SMALLINT|MEDIUMINT|INTEGER|INT|BIGINT)(?: NOT NULL| NULL)?'
        . '(?: DEFAULT (?:NULL|-?[0-9]{1,19}))?';
    $layout = $withDefinitions ? '(?: \((?&declared)(?:, ?(?&declared)){0,127}\))?' : '';

    return preg_match('/(?(DEFINE)(?<field>' . $field . ')(?<declared>' . $declared . '))'
        . '\ACREATE (?:TEMPORARY )?TABLE (?:IF NOT EXISTS )?(?:' . $schema . '\.)?' . $target
        . $layout . '(?: ENGINE=InnoDB)? AS ' . $query . '\z/D', $sql) === 1;
}

function resetMysqlCreateAs(PDO $catalog, PDO $owner, string $target, string $topology, int $variant): void
{
    $catalog->exec('DROP TABLE IF EXISTS ' . CTAS_MARKER);
    $catalog->exec('CREATE TABLE ' . CTAS_MARKER . ' (n INT) ENGINE=InnoDB');
    $catalog->exec('INSERT INTO ' . CTAS_MARKER . ' VALUES (0)');
    foreach ([CTAS_PRIMARY => 11, CTAS_DECOY => 41] as $name => $marker) {
        $catalog->exec('DROP TABLE IF EXISTS ' . $name);
        if ($name !== $target || in_array($topology, ['persistent', 'shadow'], true)) {
            $catalog->exec('CREATE TABLE ' . $name . ' (existing INT) ENGINE=InnoDB');
            $catalog->exec('INSERT INTO ' . $name . ' VALUES (NULL), (' . ($marker + $variant) . ')');
        }
    }
    if (in_array($topology, ['temporary', 'shadow'], true)) {
        $owner->exec('CREATE TEMPORARY TABLE ' . $target . ' (local_value SMALLINT DEFAULT 9) ENGINE=InnoDB');
        $owner->exec('INSERT INTO ' . $target . ' VALUES (NULL), (' . (120 + $variant) . ')');
    }
}

function mysqlCreateAsState(PDO $owner, PDO $catalog, array $sourceLocations): array
{
    $visible = $permanent = [];
    foreach (array_unique([CTAS_PRIMARY, CTAS_DECOY, ...$sourceLocations]) as $name) {
        $visible[$name] = observeMysqlFixtureTable($owner, $name);
        $permanent[$name] = observeMysqlFixtureTable($catalog, $name);
    }
    $marker = 'SELECT n FROM ' . CTAS_MARKER . ' ORDER BY n';

    return ['visible' => $visible, 'permanent' => $permanent, 'marker' => $owner->query($marker)->fetchAll(PDO::FETCH_COLUMN),
        'committedMarker' => $catalog->query($marker)->fetchAll(PDO::FETCH_COLUMN)];
}

function observeMysqlCreateAs(PDO $catalog, array $request): array
{
    ['sql' => $sql, 'target' => $target, 'topology' => $topology, 'variant' => $variant] = $request;
    $input = $request['sourceFixture'] ?? null;
    $definitions = ($request['explicitDefinitions'] ?? false) === true;
    if (!admitMysqlCreateAs($sql, $input !== null, $definitions) || !in_array($target, [CTAS_PRIMARY, CTAS_DECOY], true)
        || ($input !== null && !validMysqlCreateAsSource($input, $target, $topology))
        || !in_array($topology, ['absent', 'persistent', 'temporary', 'shadow'], true)
        || !is_int($variant) || $variant < 0 || $variant > 40) {
        return ['status' => 'outside-observer-scope'];
    }
    $owner = mysqlFixtureOwner();
    resetMysqlCreateAs($catalog, $owner, $target, $topology, $variant);
    $sourceLocations = $input === null ? [] : seedMysqlCreateAsSources($catalog, $owner, $input, $variant);
    $initial = mysqlCreateAsState($owner, $catalog, $sourceLocations);
    $owner->beginTransaction();
    $owner->exec('INSERT INTO ' . CTAS_MARKER . ' VALUES (7)');
    $effect = applyMysqlFixtureWrite($owner, $sql);
    $diagnostics = mysqlFixtureDiagnostics($owner);
    $owner->query('SELECT 1')->fetchColumn();
    $active = $owner->inTransaction();
    $after = mysqlCreateAsState($owner, $catalog, $sourceLocations);
    if ($active) {
        $owner->rollBack();
    } else {
        $owner->exec('ROLLBACK');
    }
    $restored = mysqlCreateAsState($owner, $catalog, $sourceLocations);
    $owner = null;

    return ['status' => 'mysql-create-table-as', 'initial' => $initial, 'effect' => $effect, ...$diagnostics,
        'active' => $active, 'after' => $after, 'restored' => $restored,
        'afterDisconnect' => mysqlCreateAsState(mysqlFixtureOwner(), $catalog, $sourceLocations)];
}

if ($argv[1] !== 'mysql') {
    throw new RuntimeException('The query-backed table observer requires MySQL.');
}
[$catalog, $environment] = connectFixture('mysql');
if ($catalog->query('SELECT DATABASE()')->fetchColumn() !== 'ir_fixture') {
    throw new RuntimeException('The query-backed table observer requires the disposable ir_fixture database.');
}
$defaults = $catalog->query("SELECT DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = 'ir_fixture'")->fetch(PDO::FETCH_NUM);
if (!is_array($defaults) || preg_match('/\A[a-z0-9_]+ [a-z0-9_]+\z/D', implode(' ', $defaults)) !== 1) {
    throw new RuntimeException('The disposable fixture must have known schema character defaults.');
}
$catalog->exec('CREATE DATABASE IF NOT EXISTS ir_ctas_decoy CHARACTER SET utf8mb4 COLLATE utf8mb4_bin');
$catalog->exec('ALTER DATABASE ir_fixture CHARACTER SET utf8mb4 COLLATE utf8mb4_bin');

try {
    echo json_encode(['database' => $environment, 'php' => PHP_VERSION], JSON_THROW_ON_ERROR) . "\n";
    while (($line = fgets(STDIN)) !== false) {
        $request = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
        $batch = array_is_list($request);
        $observed = array_map(static fn (array $input): array => observeMysqlCreateAs($catalog, $input), $batch ? $request : [$request]);
        echo json_encode($batch ? $observed : $observed[0], JSON_THROW_ON_ERROR) . "\n";
    }
} finally {
    $catalog->exec('ALTER DATABASE ir_fixture CHARACTER SET ' . $defaults[0] . ' COLLATE ' . $defaults[1]);
}
