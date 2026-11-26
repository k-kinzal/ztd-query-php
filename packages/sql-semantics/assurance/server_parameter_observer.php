<?php

declare(strict_types=1);

require __DIR__ . '/server_connection.php';

$engine = $argv[1];
[$pdo, $environment] = connectFixture($engine);
$mysql = $engine === 'mysql';
$database = $pdo->query($mysql ? 'SELECT DATABASE()' : 'SELECT current_database()')->fetchColumn();
if ($database !== 'ir_fixture') {
    throw new RuntimeException('The parameter observer requires the disposable ir_fixture database.');
}
$pdo->exec('START TRANSACTION READ ONLY');

function admitProjection(string $sql, bool $mysql): bool
{
    $mark = $mysql ? '\?' : '\$[1-9][0-9]{0,3}';
    $argument = '(?:' . $mark . '|\(' . $mark . '\))';
    $cast = $mysql ? '(?:SIGNED(?: INTEGER)?|UNSIGNED)' : '(?:integer|bigint|pg_catalog\.int[48])';
    $conversion = 'CAST\(' . $argument . ' AS ' . $cast . '\)';
    if (!$mysql) {
        $conversion = '(?:' . $conversion . '|' . $argument . '::' . $cast . ')';
    }
    $integer = $mysql ? '-?[0-9]{1,10}' : 'CAST\(-?[0-9]{1,10} AS pg_catalog\.int4\)';
    $atom = '(?:' . $conversion . '(?: \* 0)?|NULL|' . $integer . ')';
    $quote = $mysql ? chr(96) : '"';
    $label = '(?:[a-z][0-9]{1,2}|' . $quote . '[a-z][0-9]{1,2}' . $quote . ')';
    $column = $atom . ' AS ' . $label;

    return preg_match('/\ASELECT ' . $column . '(?:, ' . $column . '){0,15}\z/iD', $sql) === 1;
}

function parameterRows(PDOStatement $statement, array $bindings): array
{
    $fields = [];
    for ($index = 0; $index < $statement->columnCount(); ++$index) {
        $metadata = $statement->getColumnMeta($index);
        $fields[] = [$metadata['name'], $metadata['native_type']];
    }
    $rows = $statement->fetchAll(PDO::FETCH_NUM);
    $statement->closeCursor();

    return ['status' => 'rows', 'fields' => $fields, 'rows' => $rows, 'bindings' => $bindings];
}

function observeParameterProjection(PDO $pdo, bool $mysql, array $request): array
{
    $sql = $request['sql'];
    $bindings = $request['bindings'];
    if (!admitProjection($sql, $mysql) || $bindings === [] || count($bindings) > 16 || !array_is_list($bindings)) {
        return ['status' => 'outside-observer-scope'];
    }
    foreach ($bindings as $value) {
        if ($value !== null && (!is_int($value) || $value < -2147483648 || $value > 2147483647)) {
            return ['status' => 'outside-observer-scope'];
        }
    }
    try {
        if ($mysql) {
            $statement = $pdo->prepare($sql);
            foreach ($bindings as $offset => $value) {
                $statement->bindValue($offset + 1, $value, $value === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            }
            $statement->execute();

            return parameterRows($statement, $bindings);
        }
        $pdo->exec('PREPARE ir_parameter_observation (' . implode(', ', array_fill(0, count($bindings), 'pg_catalog.int4')) . ') AS ' . $sql);
        $args = implode(', ', array_map(static fn (?int $value): string => $value === null ? 'NULL' : (string) $value, $bindings));

        return parameterRows($pdo->query('EXECUTE ir_parameter_observation (' . $args . ')'), $bindings);
    } catch (PDOException $error) {
        $pdo->exec('ROLLBACK');
        $pdo->exec('START TRANSACTION READ ONLY');

        return ['status' => 'db-error', 'state' => $error->errorInfo[0], 'message' => $error->getMessage()];
    } finally {
        if (!$mysql) {
            $pdo->exec('DEALLOCATE ALL');
        }
    }
}

echo json_encode(['database' => $environment, 'php' => PHP_VERSION, 'binding' => $mysql ? 'PDO native integer or NULL' : 'PREPARE with int4 positions'], JSON_THROW_ON_ERROR) . "\n";
while (($line = fgets(STDIN)) !== false) {
    $item = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
    $requests = array_is_list($item) ? $item : [$item];
    $responses = [];
    foreach ($requests as $request) {
        $responses[] = observeParameterProjection($pdo, $mysql, $request);
    }
    echo json_encode(array_is_list($item) ? $responses : $responses[0], JSON_THROW_ON_ERROR) . "\n";
}
