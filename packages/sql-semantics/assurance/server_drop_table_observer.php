<?php

declare(strict_types=1);

require __DIR__ . '/server_connection.php';

$engine = $argv[1];
[$pdo, $environment] = connectFixture($engine);
$mysql = $engine === 'mysql';
$schema = $mysql ? 'ir_fixture' : 'public';
$database = $pdo->query($mysql ? 'SELECT DATABASE()' : 'SELECT current_database()')->fetchColumn();
if ($database !== 'ir_fixture') {
    throw new RuntimeException('The DROP TABLE observer requires the disposable ir_fixture database.');
}
$base = $schema . '.ir_drop_';

function resetDropFixture(PDO $pdo, string $base, bool $mysql, bool $dependent): void
{
    $pdo->exec('DROP VIEW IF EXISTS ' . $base . 'view');
    $pdo->exec('DROP TABLE IF EXISTS ' . $base . 'a, ' . $base . 'b, ' . $base . 'control, ' . $base . 'marker');
    foreach (['a' => 11, 'b' => 23, 'control' => -1, 'marker' => 0] as $table => $value) {
        $pdo->exec('CREATE TABLE ' . $base . $table . ' (value INTEGER)' . ($mysql ? ' ENGINE=InnoDB' : ''));
        $pdo->exec('INSERT INTO ' . $base . $table . ' VALUES (' . $value . ')');
    }
    if ($dependent) {
        $pdo->exec('CREATE VIEW ' . $base . 'view AS SELECT value FROM ' . $base . 'a');
    }
}

function admitDropTable(string $sql, string $schema, bool $mysql): bool
{
    $quote = $mysql ? chr(96) : '"';
    $name = static fn (string $part): string => '(?:' . $quote . $part . $quote . '|' . $part . ')';
    $target = $name($schema) . '\.' . $name('ir_drop_(?:a|b|control|missing)');

    return preg_match('/\ADROP TABLE (?:IF EXISTS )?' . $target . '(?:, ' . $target . '){0,3}(?: RESTRICT| CASCADE)?\z/D', $sql) === 1;
}

function dropFixtureState(PDO $pdo, string $schema, string $base): array
{
    $tables = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = '" . $schema
        . "' AND table_name IN ('ir_drop_a', 'ir_drop_b', 'ir_drop_control', 'ir_drop_marker', 'ir_drop_view') ORDER BY table_name")->fetchAll(PDO::FETCH_COLUMN);
    $rows = [];
    foreach (['a', 'b', 'control', 'marker'] as $table) {
        if (in_array('ir_drop_' . $table, $tables, true)) {
            $rows[$table] = $pdo->query('SELECT value FROM ' . $base . $table . ' ORDER BY value')->fetchAll(PDO::FETCH_COLUMN);
        }
    }

    return ['rows' => $rows, 'view' => in_array('ir_drop_view', $tables, true)];
}

function droppedState(PDO $pdo, string $schema, string $base, bool $mysql, array $input): array
{
    if (!admitDropTable($input['sql'], $schema, $mysql)) {
        return ['status' => 'outside-observer-scope'];
    }
    resetDropFixture($pdo, $base, $mysql, $input['dependent']);
    $pdo->beginTransaction();
    $pdo->exec('INSERT INTO ' . $base . 'marker VALUES (7)');
    try {
        $pdo->exec($input['sql']);
        $status = 'dropped';
    } catch (PDOException $error) {
        $status = $error->errorInfo[0];
    }
    if ($mysql) {
        // An error response can leave PDO's cached transaction status stale.
        $pdo->query('SELECT 1')->fetchColumn();
    }
    $active = $pdo->inTransaction();
    $effect = $active && $status !== 'dropped' ? null : dropFixtureState($pdo, $schema, $base);
    if ($active) {
        $pdo->rollBack();
    }

    return ['status' => $status, 'active' => $active, 'effect' => $effect, 'restored' => dropFixtureState($pdo, $schema, $base)];
}

resetDropFixture($pdo, $base, $mysql, false);
echo json_encode(['database' => $environment, 'php' => PHP_VERSION, 'initial' => dropFixtureState($pdo, $schema, $base)], JSON_THROW_ON_ERROR) . "\n";
while (($line = fgets(STDIN)) !== false) {
    $item = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
    $requests = array_is_list($item) ? $item : [$item];
    $responses = [];
    foreach ($requests as $request) {
        $responses[] = droppedState($pdo, $schema, $base, $mysql, $request);
    }
    echo json_encode(array_is_list($item) ? $responses : $responses[0], JSON_THROW_ON_ERROR) . "\n";
}
