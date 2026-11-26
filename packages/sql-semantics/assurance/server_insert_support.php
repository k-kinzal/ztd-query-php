<?php

declare(strict_types=1);

require __DIR__ . '/server_returning_observation.php';

function insertFixture(PDO $pdo, string $engine, string $schema): void
{
    $prefix = $schema . '.ir_insert_';
    foreach (['target', 'control', 'events'] as $old) {
        $pdo->exec('DROP TABLE IF EXISTS ' . $prefix . $old);
    }
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
    $qualifier = $name('(?:ir_insert_(?:target|control)|r)');
    $reference = '(?:' . $qualifier . '\\.)?' . $column;
    $returning = $schema === 'public' ? fixtureReturningClause($reference, $qualifier) : '';
    $alias = $schema === 'public' ? '(?: AS r)?' : '';
    $integer = '-?[0-9]{1,10}';
    $operand = '(?:NULL|' . $integer . '|CAST\\(' . $integer . ' AS pg_catalog\\.int4\\))';
    $row = '\\(\\h*' . $operand . '(?:\\h*,\\h*' . $operand . ')*\\h*\\)';

    return preg_match('/\\AINSERT INTO ' . $table . $alias . '\\h*\\(' . $column . '(?:\\h*,\\h*' . $column
        . ')*\\) VALUES ' . $row . '(?:\\h*,\\h*' . $row . ')*' . $returning . '\\z/iD', $sql) === 1;
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

function insertEffect(PDO $pdo, string $schema, string $sql, callable $admit, callable $snapshot): array
{
    if (!$admit($sql, $schema)) {
        return ['status' => 'outside-observer-scope'];
    }
    $pdo->beginTransaction();
    try {
        $statement = $pdo->prepare($sql);
        $statement->execute();
        $effect = ['status' => 'inserted', 'count' => $statement->rowCount(), 'columns' => $statement->columnCount(), ...observeReturning($statement)];
        $statement->closeCursor();
        $effect['after'] = $snapshot($pdo, $schema);
    } catch (PDOException $error) {
        $effect = ['status' => 'db-error', 'state' => $error->errorInfo[0], 'message' => $error->getMessage()];
    } finally {
        $pdo->rollBack();
    }
    $effect['restored'] = $snapshot($pdo, $schema);

    return $effect;
}
