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
    throw new RuntimeException('The DELETE observer requires the disposable ir_fixture database.');
}

function deleteFixture(PDO $pdo, string $engine, string $schema): void
{
    $target = $schema . '.ir_delete_target';
    $child = $schema . '.ir_delete_child';
    $control = $schema . '.ir_delete_control';
    $events = $schema . '.ir_delete_events';
    foreach ([$child, $target, $control, $events] as $name) {
        $pdo->exec('DROP TABLE IF EXISTS ' . $name);
    }
    $pdo->exec('CREATE TABLE ' . $target . ' (id INTEGER, delta INTEGER)');
    $pdo->exec('CREATE TABLE ' . $child . ($engine === 'postgres' ? ' () INHERITS (' . $target . ')' : ' (id INTEGER, delta INTEGER)'));
    $pdo->exec('CREATE TABLE ' . $control . ' (id INTEGER, delta INTEGER)');
    $pdo->exec('CREATE TABLE ' . $events . ' (source VARCHAR(32), id INTEGER, delta INTEGER)');
    $pdo->exec('INSERT INTO ' . $target . ' VALUES (-2,1),(-1,-1),(0,2),(0,2),(NULL,-1),(1,NULL),(2,0),(2147483647,-2147483648)');
    $pdo->exec('INSERT INTO ' . $child . ' VALUES (-1,5),(0,2),(NULL,3),(3,3)');
    $pdo->exec('INSERT INTO ' . $control . ' VALUES (99,2),(9,99),(0,NULL)');
    if ($engine === 'postgres') {
        $pdo->exec('CREATE FUNCTION pg_temp.ir_record_delete() RETURNS trigger LANGUAGE plpgsql AS $body$'
            . ' BEGIN INSERT INTO ' . $events . ' VALUES (TG_TABLE_NAME, OLD.id, OLD.delta); RETURN OLD; END; $body$');
    }
    foreach (['ir_delete_target', 'ir_delete_child', 'ir_delete_control'] as $name) {
        $trigger = 'CREATE TRIGGER ' . $name . '_log AFTER DELETE ON ' . $schema . '.' . $name . ' FOR EACH ROW ';
        $action = $engine === 'postgres'
            ? 'EXECUTE FUNCTION pg_temp.ir_record_delete()'
            : 'INSERT INTO ' . $events . " VALUES ('" . $name . "', OLD.id, OLD.delta)";
        $pdo->exec($trigger . $action);
    }
}

function admitDelete(string $sql, string $schema, string $engine): bool
{
    // Fixtures admit simple quoted names, signed scalars and the registered DELETE forms.
    $quote = '["' . chr(96) . ']?';
    $name = static fn (string $value): string => $quote . $value . $quote;
    $table = '(?:' . $name($schema) . '\\.)?' . $name('ir_delete_(?:target|child|control)');
    $qualifier = $name('(?:ir_delete_(?:target|child|control)|r)');
    $reference = '(?:' . $qualifier . '\\.)?' . $name('(?:id|delta)');
    $integer = '-?[0-9]{1,10}';
    $operand = '(?:' . $integer . '|CAST\\(' . $integer . ' AS pg_catalog\\.int4\\)|' . $reference . ')';
    $operator = '(?:[<>=]{1,2}|OPERATOR\\(pg_catalog\\.[<>=]{1,2}\\))';
    $returning = $engine === 'postgres' ? fixtureReturningClause($reference, $qualifier) : '';
    return preg_match('/\\ADELETE FROM (?:ONLY )?' . $table . '(?: AS r)?(?: WHERE (?&truth))?' . $returning . '\\z'
        . fixturePredicateDefinition($operand, $operator) . '/iD', $sql) === 1;
}

function physicalRows(PDO $pdo, string $engine, string $schema): array
{
    $physical = $engine === 'postgres' ? 'ONLY ' : '';
    $rows = ['target' => [], 'child' => [], 'control' => [], 'events' => []];
    $relations = [];
    foreach (['target', 'child', 'control'] as $name) {
        $relations[] = "SELECT '" . $name . "' AS bucket, id, delta, NULL AS source FROM " . $physical . $schema . '.ir_delete_' . $name;
    }
    $relations[] = "SELECT 'events', id, delta, source FROM " . $schema . '.ir_delete_events';
    foreach ($pdo->query(implode(' UNION ALL ', $relations))->fetchAll(PDO::FETCH_NUM) as [$bucket, $id, $delta, $source]) {
        $rows[$bucket][] = $bucket === 'events' ? [$source, $id, $delta] : [$id, $delta];
    }

    return $rows;
}

function deleteEffect(PDO $pdo, string $engine, string $schema, string $sql): array
{
    if (!admitDelete($sql, $schema, $engine)) {
        return ['status' => 'outside-observer-scope'];
    }
    $pdo->beginTransaction();
    try {
        $statement = $pdo->prepare($sql);
        $statement->execute();
        $effect = ['status' => 'deleted', 'count' => $statement->rowCount(), 'columns' => $statement->columnCount(), ...observeReturning($statement)];
        $statement->closeCursor();
        $effect['after'] = physicalRows($pdo, $engine, $schema);
    } catch (PDOException $error) {
        $effect = ['status' => 'db-error', 'state' => $error->errorInfo[0], 'message' => $error->getMessage()];
    } finally {
        $pdo->rollBack();
    }
    $effect['restored'] = physicalRows($pdo, $engine, $schema);

    return $effect;
}

deleteFixture($pdo, $engine, $schema);
echo json_encode(['database' => $environment, 'php' => PHP_VERSION, 'initial' => physicalRows($pdo, $engine, $schema)], JSON_THROW_ON_ERROR) . "\n";
while (($line = fgets(STDIN)) !== false) {
    $input = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
    $items = array_is_list($input) ? $input : [$input];
    $responses = [];
    foreach ($items as $item) {
        $responses[] = deleteEffect($pdo, $engine, $schema, $item['sql']);
    }
    echo json_encode(array_is_list($input) ? $responses : $responses[0], JSON_THROW_ON_ERROR) . "\n";
}
