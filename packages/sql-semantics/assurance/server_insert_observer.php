<?php

declare(strict_types=1);

require __DIR__ . '/server_connection.php';

$engine = $argv[1];
[$pdo, $environment] = connectFixture($engine);
$schema = $engine === 'mysql' ? 'ir_fixture' : 'public';
$database = $pdo->query($engine === 'mysql' ? 'SELECT DATABASE()' : 'SELECT current_database()')->fetchColumn();
if ($database !== 'ir_fixture') {
    throw new RuntimeException('The INSERT observer requires the disposable ir_fixture database.');
}

function insertFixture(PDO $pdo, string $engine, string $schema): void
{
    $prefix = $schema . '.ir_insert_';
    $engineClause = $engine === 'mysql' ? ' ENGINE=InnoDB' : '';
    $pdo->exec('CREATE TABLE ' . $prefix . 'events (source VARCHAR(32), id INTEGER, delta INTEGER, stamp INTEGER)' . $engineClause);
    foreach (['target', 'control'] as $name) {
        $pdo->exec('CREATE TABLE ' . $prefix . $name . ' (id INTEGER DEFAULT 83, delta INTEGER DEFAULT -29, stamp INTEGER DEFAULT 9)' . $engineClause);
        $seed = $name === 'target' ? '(2,3,4),(2,3,4),(NULL,0,7)' : '(99,-2,3),(5,6,2)';
        $pdo->exec('INSERT INTO ' . $prefix . $name . ' VALUES ' . $seed);
    }
    if ($engine === 'postgres') {
        $pdo->exec('CREATE FUNCTION pg_temp.ir_record_insert() RETURNS trigger LANGUAGE plpgsql AS $log$'
            . ' BEGIN INSERT INTO ' . $prefix . 'events VALUES (TG_TABLE_NAME, NEW.id, NEW.delta, NEW.stamp); RETURN NEW; END; $log$');
    }
    foreach (['target', 'control'] as $name) {
        $trigger = 'CREATE TRIGGER ir_insert_' . $name . '_log AFTER INSERT ON ' . $prefix . $name . ' FOR EACH ROW ';
        $action = $engine === 'postgres'
            ? 'EXECUTE FUNCTION pg_temp.ir_record_insert()'
            : 'INSERT INTO ' . $prefix . "events VALUES ('ir_insert_" . $name . "', NEW.id, NEW.delta, NEW.stamp)";
        $pdo->exec($trigger . $action);
    }
}

function admitInsert(string $sql, string $schema): bool
{
    $quote = '["' . chr(96) . ']?';
    $name = static fn (string $value): string => $quote . $value . $quote;
    $table = $name($schema) . '\\.' . $name('ir_insert_(?:target|control)');
    $column = $name('(?:id|delta|stamp)');
    $integer = '-?[0-9]{1,10}';
    $operand = '(?:NULL|' . $integer . '|CAST\\(' . $integer . ' AS pg_catalog\\.int4\\))';
    $row = '\\(\\h*' . $operand . '(?:\\h*,\\h*' . $operand . ')*\\h*\\)';

    return preg_match('/\\AINSERT INTO ' . $table . '\\h*\\(' . $column . '(?:\\h*,\\h*' . $column
        . ')*\\) VALUES ' . $row . '(?:\\h*,\\h*' . $row . ')*\\z/iD', $sql) === 1;
}

function insertedRows(PDO $pdo, string $schema): array
{
    $prefix = $schema . '.ir_insert_';
    $rows = ['target' => [], 'control' => [], 'events' => []];
    $query = "SELECT 'target', id, delta, stamp, NULL FROM " . $prefix . 'target'
        . " UNION ALL SELECT 'control', id, delta, stamp, NULL FROM " . $prefix . 'control'
        . " UNION ALL SELECT 'events', id, delta, stamp, source FROM " . $prefix . 'events';
    foreach ($pdo->query($query)->fetchAll(PDO::FETCH_NUM) as [$bucket, $id, $delta, $stamp, $source]) {
        $rows[$bucket][] = $bucket === 'events' ? [$source, $id, $delta, $stamp] : [$id, $delta, $stamp];
    }

    return $rows;
}

function insertEffect(PDO $pdo, string $schema, string $sql): array
{
    if (!admitInsert($sql, $schema)) {
        return ['status' => 'outside-observer-scope'];
    }
    $pdo->beginTransaction();
    try {
        $statement = $pdo->prepare($sql);
        $statement->execute();
        $effect = ['status' => 'inserted', 'count' => $statement->rowCount(), 'columns' => $statement->columnCount()];
        $statement->closeCursor();
        $effect['after'] = insertedRows($pdo, $schema);
    } catch (PDOException $error) {
        $effect = ['status' => 'db-error', 'state' => $error->errorInfo[0], 'message' => $error->getMessage()];
    } finally {
        $pdo->rollBack();
    }
    $effect['restored'] = insertedRows($pdo, $schema);

    return $effect;
}

insertFixture($pdo, $engine, $schema);
echo json_encode(['database' => $environment, 'php' => PHP_VERSION, 'initial' => insertedRows($pdo, $schema)], JSON_THROW_ON_ERROR) . "\n";
while (($line = fgets(STDIN)) !== false) {
    $item = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
    echo json_encode(insertEffect($pdo, $schema, $item['sql']), JSON_THROW_ON_ERROR) . "\n";
}
