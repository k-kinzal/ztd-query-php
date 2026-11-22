<?php

declare(strict_types=1);

require __DIR__ . '/server_connection.php';
require __DIR__ . '/server_predicate_scope.php';
require __DIR__ . '/server_returning_observation.php';

$engine = $argv[1];
[$pdo, $environment] = connectFixture($engine);
$schema = $engine === 'mysql' ? 'ir_fixture' : 'public';
$database = $pdo->query($engine === 'mysql' ? 'SELECT DATABASE()' : 'SELECT current_database()')->fetchColumn();
if ($database !== 'ir_fixture') {
    throw new RuntimeException('The UPDATE observer requires the disposable ir_fixture database.');
}

function updateFixture(PDO $pdo, string $engine, string $schema): void
{
    $base = $schema . '.ir_update_';
    $engineClause = $engine === 'mysql' ? ' ENGINE=InnoDB' : '';
    $layout = ' (id INTEGER, delta INTEGER)' . $engineClause;
    foreach (['child', 'target', 'control', 'events'] as $name) {
        $pdo->exec('DROP TABLE IF EXISTS ' . $base . $name);
    }
    $pdo->exec('CREATE TABLE ' . $base . 'target' . $layout);
    $pdo->exec('CREATE TABLE ' . $base . 'control' . $layout);
    $pdo->exec('CREATE TABLE ' . $base . 'child' . ($engine === 'postgres' ? ' () INHERITS (' . $base . 'target)' : $layout));
    $pdo->exec('CREATE TABLE ' . $base . 'events (source VARCHAR(32), old_id INTEGER, old_delta INTEGER, new_id INTEGER, new_delta INTEGER)' . $engineClause);
    $pdo->exec('INSERT INTO ' . $base . 'target VALUES (-2,1),(-1,-1),(0,2),(0,2),(NULL,-1),(1,NULL),(2,0),(2147483647,-2147483648)');
    $pdo->exec('INSERT INTO ' . $base . 'child VALUES (-1,5),(0,2),(NULL,3),(3,3)');
    $pdo->exec('INSERT INTO ' . $base . 'control VALUES (99,2),(9,99),(0,NULL)');
    if ($engine === 'postgres') {
        $pdo->exec('CREATE FUNCTION pg_temp.ir_record_update() RETURNS trigger LANGUAGE plpgsql AS $log$'
            . ' BEGIN INSERT INTO ' . $base . 'events VALUES (TG_TABLE_NAME, OLD.id, OLD.delta, NEW.id, NEW.delta); RETURN NEW; END; $log$');
    }
    foreach (['target', 'child', 'control'] as $name) {
        $trigger = 'CREATE TRIGGER ir_update_' . $name . '_log AFTER UPDATE ON ' . $base . $name . ' FOR EACH ROW ';
        $action = $engine === 'postgres'
            ? 'EXECUTE FUNCTION pg_temp.ir_record_update()'
            : 'INSERT INTO ' . $base . "events VALUES ('ir_update_" . $name . "', OLD.id, OLD.delta, NEW.id, NEW.delta)";
        $pdo->exec($trigger . $action);
    }
}

function admitUpdate(string $sql, string $schema, string $engine): bool
{
    $quote = '["' . chr(96) . ']?';
    $name = static fn (string $part): string => $quote . $part . $quote;
    $table = $name($schema) . '\\.' . $name('ir_update_(?:target|child|control)');
    $alias = $name('(?:r|__ztd_source)');
    $qualifier = $name('(?:r|__ztd_source|ir_update_(?:target|child|control))');
    $column = '(?:' . $qualifier . '\\.)?' . $name('(?:id|delta)');
    $integer = '-?[0-9]{1,10}';
    $operand = '(?:' . $column . '|NULL|' . $integer . '|CAST\\(' . $integer . ' AS pg_catalog\\.int4\\))';
    $operation = '(?:[<>=]{1,2}|OPERATOR\\(pg_catalog\\.[<>=]{1,2}\\))';
    $assignment = $column . '\\h*=\\h*' . $operand;
    $returning = $engine === 'postgres' ? fixtureReturningClause($column, $qualifier) : '';

    return preg_match('/\\AUPDATE (?:ONLY )?' . $table . '(?: AS ' . $alias . ')? SET '
        . $assignment . '(?:\\h*,\\h*' . $assignment . ')*(?: WHERE (?&truth))?' . $returning . '\\z'
        . fixturePredicateDefinition($operand, $operation) . '/iD', $sql) === 1;
}

function updatedRows(PDO $pdo, string $engine, string $schema): array
{
    $prefix = $schema . '.ir_update_';
    $scan = $engine === 'postgres' ? 'ONLY ' : '';
    $rows = ['target' => [], 'child' => [], 'control' => [], 'events' => []];
    $placeholders = $engine === 'postgres' ? 'CAST(NULL AS INTEGER), CAST(NULL AS INTEGER), CAST(NULL AS VARCHAR(32))' : 'NULL, NULL, NULL';
    $select = [];
    foreach (['target', 'child', 'control'] as $kind) {
        $select[] = "SELECT '" . $kind . "', id, delta, " . $placeholders . ' FROM ' . $scan . $prefix . $kind;
    }
    $select[] = "SELECT 'events', old_id, old_delta, new_id, new_delta, source FROM " . $prefix . 'events';
    foreach ($pdo->query(implode(' UNION ALL ', $select))->fetchAll(PDO::FETCH_NUM) as [$kind, $id, $delta, $newId, $newDelta, $source]) {
        $rows[$kind][] = $kind === 'events' ? [$source, $id, $delta, $newId, $newDelta] : [$id, $delta];
    }

    return $rows;
}

function updateEffect(PDO $pdo, string $engine, string $schema, string $sql): array
{
    if (!admitUpdate($sql, $schema, $engine)) {
        return ['status' => 'outside-observer-scope'];
    }
    $pdo->beginTransaction();
    try {
        $statement = $pdo->prepare($sql);
        $statement->execute();
        $effect = ['status' => 'updated', 'count' => $statement->rowCount(), 'columns' => $statement->columnCount(), ...observeReturning($statement)];
        $statement->closeCursor();
        $effect['after'] = updatedRows($pdo, $engine, $schema);
    } catch (PDOException $error) {
        $effect = ['status' => 'db-error', 'state' => $error->errorInfo[0], 'message' => $error->getMessage()];
    } finally {
        $pdo->rollBack();
    }
    $effect['restored'] = updatedRows($pdo, $engine, $schema);

    return $effect;
}

updateFixture($pdo, $engine, $schema);
echo json_encode(['database' => $environment, 'php' => PHP_VERSION, 'initial' => updatedRows($pdo, $engine, $schema)], JSON_THROW_ON_ERROR) . "\n";
while (($line = fgets(STDIN)) !== false) {
    $item = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
    $requests = array_is_list($item) ? $item : [$item];
    $responses = [];
    foreach ($requests as $request) {
        $responses[] = updateEffect($pdo, $engine, $schema, $request['sql']);
    }
    echo json_encode(array_is_list($item) ? $responses : $responses[0], JSON_THROW_ON_ERROR) . "\n";
}
