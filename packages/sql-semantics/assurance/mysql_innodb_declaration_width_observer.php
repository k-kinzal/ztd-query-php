<?php

declare(strict_types=1);

require __DIR__ . '/server_connection.php';
require __DIR__ . '/mysql_varchar_write.php';
require __DIR__ . '/mysql_table_effects.php';

const WIDTH_TARGET = 'ir_fixture.ir_width_data';
const WIDTH_MARKER = 'ir_fixture.ir_width_marker';

if (($argv[1] ?? '') !== 'mysql') {
    throw new RuntimeException('This observer requires a disposable MySQL fixture.');
}
[$catalog, $environment] = connectFixture('mysql');
$owner = mysqlFixtureOwner();
if ($catalog->query('SELECT DATABASE()')->fetchColumn() !== 'ir_fixture') {
    throw new RuntimeException('The selected database must be the disposable ir_fixture.');
}
$catalog->exec('DROP TABLE IF EXISTS ' . WIDTH_MARKER);
$catalog->exec('CREATE TABLE ' . WIDTH_MARKER . ' (visible INT NOT NULL) ENGINE=InnoDB');
$catalog->exec('INSERT INTO ' . WIDTH_MARKER . ' VALUES (0)');

function stockWidthSettings(PDO $catalog, PDO $owner): array
{
    $setting = $owner->query('SELECT @@innodb_page_size, @@innodb_strict_mode, @@default_storage_engine, @@default_tmp_storage_engine')
        ->fetch(PDO::FETCH_NUM);
    $status = $catalog->query("SHOW TABLE STATUS FROM ir_fixture WHERE Name = 'ir_width_marker'")->fetch(PDO::FETCH_ASSOC);

    return ['pageBytes' => $setting[0], 'strict' => $setting[1], 'engine' => $setting[2], 'temporaryEngine' => $setting[3],
        'rowFormat' => $status['Row_format']];
}

function admitWidthDeclaration(string $sql): bool
{
    $target = '`ir_fixture`\.`ir_width_data`';
    $options = ' ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin';
    if (strlen($sql) > 150000 ||
        preg_match('/\ACREATE (TEMPORARY )?TABLE (IF NOT EXISTS )?' . $target . ' \((.*)\)' . $options . '\z/D', $sql, $parts) !== 1) {
        return false;
    }
    if ($parts[3] === '') {
        return true;
    }
    $name = '`(?:c[0-9]{1,4}|odd `` field[0-9]{1,5})`';
    $definition = $name . ' (?:TINYINT|SMALLINT|MEDIUMINT|INTEGER|INT|BIGINT|VARCHAR\([0-9]{1,5}\) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin)'
        . "(?: NULL| NOT NULL) DEFAULT (?:NULL|-?[0-9]{1,19}|'a')";
    $elements = explode(', ', $parts[3]);
    if (count($elements) > 1019) {
        return false;
    }
    $last = array_pop($elements);
    $key = preg_match('/\APRIMARY KEY \(' . $name . '(?: DESC)?\)\z/D', $last) === 1;
    if (!$key) {
        $elements[] = $last;
    }
    foreach ($elements as $column) {
        if (preg_match('/\A' . $definition . '\z/D', $column) !== 1) {
            return false;
        }
    }
    return $elements !== [];
}

function resetWidthFixture(PDO $catalog, PDO $owner, bool $guard, bool $temporary): void
{
    $owner->exec('DROP TEMPORARY TABLE IF EXISTS ' . WIDTH_TARGET);
    $catalog->exec('DROP TABLE IF EXISTS ' . WIDTH_TARGET);
    $catalog->exec('UPDATE ' . WIDTH_MARKER . ' SET visible = 0');
    if ($guard) {
        $connection = $temporary ? $owner : $catalog;
        $connection->exec('CREATE ' . ($temporary ? 'TEMPORARY ' : '') . 'TABLE ' . WIDTH_TARGET
            . ' (prior INTEGER NOT NULL DEFAULT 61) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin');
        $connection->exec('INSERT INTO ' . WIDTH_TARGET . ' () VALUES ()');
    }
}

function observeWidth(PDO $catalog, PDO $owner, array $request): array
{
    $sql = $request['sql'];
    $guard = $request['preparePrior'];
    $temporary = $request['temporary'];
    if (!admitWidthDeclaration($sql)) {
        return ['status' => 'outside-observer-scope'];
    }
    resetWidthFixture($catalog, $owner, $guard, $temporary);
    $owner->beginTransaction();
    $owner->exec('UPDATE ' . WIDTH_MARKER . ' SET visible = 7');
    $effect = applyMysqlFixtureWrite($owner, $sql);
    $diagnostics = mysqlFixtureDiagnostics($owner);
    $conditions = array_map(static fn (array $warning): array => array_slice($warning, 0, 2), $diagnostics['diagnostics']);
    if (count($conditions) !== $diagnostics['warningCount']) {
        throw new RuntimeException('Incomplete native warning list.');
    }
    $committedMarker = $catalog->query('SELECT visible FROM ' . WIDTH_MARKER)->fetchColumn();
    $owner->query('SELECT 1')->fetchColumn();
    $active = $owner->inTransaction();
    $write = null;
    if (!$guard && $effect === ['executed', 0]) {
        $write = applyMysqlFixtureWrite($owner, 'INSERT INTO ' . WIDTH_TARGET . ' () VALUES ()');
    }
    $visible = observeMysqlFixtureTable($owner, WIDTH_TARGET, orderedRows: false);
    $global = mysqlFixtureTableDefinition($catalog, WIDTH_TARGET) !== null;
    if ($active) {
        $owner->rollBack();
    }
    $state = ['status' => 'mysql-innodb-declaration-width', 'effect' => $effect, 'warnings' => $conditions,
        'visible' => $visible, 'global' => $global, 'write' => $write, 'active' => $active, 'committedMarker' => $committedMarker,
        'diagnostics' => $diagnostics['diagnostics']];
    return $state;
}

echo json_encode(['database' => $environment, 'php' => PHP_VERSION, 'storage' => stockWidthSettings($catalog, $owner)], JSON_THROW_ON_ERROR) . "\n";
while (($line = fgets(STDIN)) !== false) {
    $request = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
    $batch = array_is_list($request);
    $observed = array_map(static fn (array $input): array => observeWidth($catalog, $owner, $input), $batch ? $request : [$request]);
    echo json_encode($batch ? $observed : $observed[0], JSON_THROW_ON_ERROR) . "\n";
}
