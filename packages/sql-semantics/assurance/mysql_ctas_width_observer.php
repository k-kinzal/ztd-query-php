<?php

declare(strict_types=1);

require __DIR__ . '/server_connection.php';
require __DIR__ . '/mysql_varchar_write.php';
require __DIR__ . '/mysql_table_effects.php';

const CTAS_WIDTH_SOURCE = 'ir_fixture.ir_ctas_wide_source';
const CTAS_WIDTH_CHARSET = ' DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin';

if (($argv[1] ?? '') !== 'mysql') {
    throw new RuntimeException('This observer requires a disposable MySQL fixture.');
}
[$catalog, $environment] = connectFixture('mysql');
if ($catalog->query('SELECT DATABASE()')->fetchColumn() !== 'ir_fixture') {
    throw new RuntimeException('The selected database must be the disposable ir_fixture.');
}
$catalog->exec('ALTER DATABASE ir_fixture CHARACTER SET utf8mb4 COLLATE utf8mb4_bin');
$catalog->exec('DROP TABLE IF EXISTS ' . CTAS_WIDTH_SOURCE);
$catalog->exec('CREATE TABLE ' . CTAS_WIDTH_SOURCE . ' (`required` INTEGER NOT NULL DEFAULT 29, `optional` INTEGER NULL, '
    . '`pad` VARCHAR(64) NOT NULL DEFAULT \'a\', `short` VARCHAR(10) NULL, `huge` VARCHAR(1023) NULL) ENGINE=InnoDB' . CTAS_WIDTH_CHARSET);
$catalog->exec('INSERT INTO ' . CTAS_WIDTH_SOURCE . " VALUES (17, NULL, 'first', NULL, 'larger'), (29, -7, 'second', 's', NULL), "
    . "(17, NULL, 'first', NULL, 'larger')");
$owner = mysqlFixtureOwner();
$settings = $owner->query('SELECT @@innodb_page_size, @@innodb_strict_mode, @@default_storage_engine, @@default_tmp_storage_engine')
    ->fetch(PDO::FETCH_NUM);
$format = $catalog->query("SHOW TABLE STATUS FROM ir_fixture WHERE Name = 'ir_ctas_wide_source'")->fetch(PDO::FETCH_ASSOC)['Row_format'];

function admissibleCtasWidth(string $sql, string $target): bool
{
    $name = '`(?:[cdf][0-9]{1,4}|leading|odd `` field[0-9]{1,5})`';
    $table = '`ir_fixture`\.`' . $target . '`';
    $source = '(?: FROM (?:DUAL|`ir_fixture`\.`ir_ctas_wide_source` AS `__ztd_source`))?';
    if (strlen($sql) > 180000 || preg_match('/\ACREATE (?:TEMPORARY )?TABLE (?:IF NOT EXISTS )?' . $table
            . '(?: \(([^)]*)\))? ENGINE=InnoDB AS SELECT (.+?)' . $source . '(?: WHERE FALSE)?\z/D', $sql, $parts) !== 1) {
        return false;
    }
    if ($parts[1] !== '') {
        $declarations = explode(', ', $parts[1]);
        if (count($declarations) > 1017) {
            return false;
        }
        foreach ($declarations as $field) {
            if (preg_match('/\A' . $name . ' (?:TINYINT|SMALLINT|MEDIUMINT|INTEGER|BIGINT)'
                    . '(?: NULL| NOT NULL)(?: DEFAULT (?:-?[0-9]{1,19}|NULL))?\z/D', $field) !== 1) {
                return false;
            }
        }
    }
    $atom = '(?:-?[0-9]{1,10}|TRUE|FALSE|NULL)';
    $ref = '`__ztd_source`\.`(?:required|optional|pad|short|huge)`';
    $values = explode(', ', $parts[2]);
    if (count($values) > 1018) {
        return false;
    }
    foreach ($values as $value) {
        if (preg_match('/\A(?:' . $atom . '|CAST\(' . $atom . ' AS SIGNED\)|' . $ref . ') AS ' . $name . '\z/D', $value) !== 1) {
            return false;
        }
    }
    return true;
}

function observeCtasWidth(PDO $catalog, PDO $owner, array $request): array
{
    if (!in_array($request['target'], ['ir_ctas_wide', 'ir_create_target'], true)
        || !admissibleCtasWidth($request['sql'], $request['target'])) {
        return ['status' => 'outside-observer-scope'];
    }
    $target = 'ir_fixture.' . $request['target'];
    $owner->exec('DROP TEMPORARY TABLE IF EXISTS ' . $target);
    $catalog->exec('DROP TABLE IF EXISTS ' . $target);
    if ($request['preparePrior']) {
        $temporary = $request['temporary'];
        $connection = $temporary ? $owner : $catalog;
        $connection->exec('CREATE ' . ($temporary ? 'TEMPORARY ' : '') . 'TABLE ' . $target
            . ' (prior INTEGER NOT NULL DEFAULT 61) ENGINE=InnoDB' . CTAS_WIDTH_CHARSET);
        $connection->exec('INSERT INTO ' . $target . ' () VALUES ()');
    }
    $effect = applyMysqlFixtureWrite($owner, $request['sql']);
    $diagnostics = mysqlFixtureDiagnostics($owner);
    $warnings = array_map(static fn (array $warning): array => array_slice($warning, 0, 2), $diagnostics['diagnostics']);
    if (count($warnings) !== $diagnostics['warningCount']) {
        throw new RuntimeException('Incomplete native warning list.');
    }
    return ['status' => 'mysql-create-table-as-width', 'effect' => $effect, 'warnings' => $warnings,
        'visible' => observeMysqlFixtureTable($owner, $target, orderedRows: false),
        'global' => mysqlFixtureTableDefinition($catalog, $target) !== null,
        'diagnostics' => $diagnostics['diagnostics']];
}

echo json_encode([
    'database' => $environment, 'php' => PHP_VERSION, 'storage' => [...$settings, $format],
    'fixture' => observeMysqlFixtureTable($catalog, CTAS_WIDTH_SOURCE, orderedRows: false),
], JSON_THROW_ON_ERROR) . "\n";
while (($line = fgets(STDIN)) !== false) {
    $request = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
    $batch = array_is_list($request);
    $observed = array_map(static fn (array $item): array => observeCtasWidth($catalog, $owner, $item), $batch ? $request : [$request]);
    echo json_encode($batch ? $observed : $observed[0], JSON_THROW_ON_ERROR) . "\n";
}
