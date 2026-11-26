<?php

declare(strict_types=1);

require_once __DIR__ . '/mysql_btree_index_observer.php';
require_once __DIR__ . '/mysql_drop_index_observer.php';

const CAPACITY_TABLE = 'ir_fixture.ir_index_capacity';
const CAPACITY_SQL = 'CREATE INDEX `new_key` ON `ir_fixture`.`ir_index_capacity` (`target_field`)';

function initIndexPolicy(PDO $catalog): void
{
    initBtree($catalog);
    initDropIndex($catalog);
}

function capacityDefinition(int $count, bool $primary): string
{
    $fields = ['rid INT NOT NULL', 'target_field INT NOT NULL'];
    $keys = $primary ? ['PRIMARY KEY (rid)'] : [];
    for ($slot = 0; $slot < 65; ++$slot) {
        $fields[] = 'k' . $slot . ' INT NOT NULL';
        if ($slot < $count - (int) $primary) {
            $keys[] = 'KEY retained_' . $slot . ' (k' . $slot . ')';
        }
    }
    return 'CREATE TABLE ' . CAPACITY_TABLE . ' (' . implode(', ', [...$fields, ...$keys]) . ') ENGINE=InnoDB';
}

function observeIndexCapacity(PDO $catalog, PDO $owner, array $request): array
{
    $count = $request['count'];
    $primary = $request['primary'];
    if ($request['sql'] !== CAPACITY_SQL || !in_array($count, [0, 62, 63, 64, 65], true) || !is_bool($primary) || ($primary && $count === 0)) {
        return ['status' => 'outside-observer-scope'];
    }

    $catalog->exec('DROP TABLE IF EXISTS ' . CAPACITY_TABLE);
    $catalog->exec('UPDATE ' . BTREE_MARKER . ' SET n = 0');
    $creation = applyMysqlFixtureWrite($catalog, capacityDefinition($count, $primary));
    if ($creation[0] !== 'executed') {
        return ['status' => 'mysql-index-capacity', 'declaration' => $creation,
            'diagnostics' => mysqlFixtureDiagnostics($catalog)['diagnostics'],
            'catalog' => btreeState($catalog, CAPACITY_TABLE)];
    }
    $base = implode(', ', range(0, 64));
    $catalog->exec('INSERT INTO ' . CAPACITY_TABLE . ' VALUES (1, 67, ' . $base . '), (2, 71, ' . $base . ')');
    $initial = btreeState($catalog, CAPACITY_TABLE);
    $effect = observeMysqlIndexEffect($catalog, $owner, BTREE_MARKER, $request['sql']);

    return ['status' => 'mysql-index-capacity', 'declaration' => ['executed'], 'initial' => $initial,
        ...$effect, 'catalog' => btreeState($catalog, CAPACITY_TABLE)];
}

function observeIndexPolicy(PDO $catalog, PDO $owner, array $request): array
{
    return match ($request['fixture'] ?? null) {
        'capacity' => observeIndexCapacity($catalog, $owner, $request),
        'create' => observeBtree($catalog, $owner, $request),
        'drop' => observeDropIndex($catalog, $owner, $request),
        default => ['status' => 'outside-observer-scope'],
    };
}

runMysqlIndexObserver($argv, initIndexPolicy(...), observeIndexPolicy(...));
