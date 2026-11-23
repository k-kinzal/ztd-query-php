<?php

declare(strict_types=1);

require __DIR__ . '/server_connection.php';

[$pdo, $environment] = connectFixture('postgres');
if ($pdo->query('SELECT current_database()')->fetchColumn() !== 'ir_fixture') {
    throw new RuntimeException('Prepared parameter observation requires the disposable fixture.');
}
$pdo->exec('START TRANSACTION READ ONLY');

function admitsTypedParameterProjection(string $sql): bool
{
    $grammar = <<<'REGEX'
~\A(?&projection)(?:\ UNION\ ALL\ (?&projection))?\z
(?(DEFINE)
  (?<projection> SELECT\ (?&field)(?:,\ (?&field)){0,15})
  (?<field> (?&expr)\ AS\ (?:"a[0-9]{1,2}"|a[0-9]{1,2}))
  (?<expr> (?:\$[1-9][0-9]{0,3}|\((?&expr)\)|CAST\((?&expr)\ AS\ (?&type)\)|(?&literal))(?:::(?&type))*)
  (?<type> (?:pg_catalog\.)?(?:int[48]|bool|text|varchar)|integer|bigint|boolean)
  (?<literal> NULL|TRUE|FALSE|-?[0-9]{1,19}|'(?:''|[^'\x00])*')
)~ixD
REGEX;

    return strlen($sql) <= 16384 && preg_match($grammar, $sql) === 1;
}

function parameterArgument(mixed $value): string
{
    return match (true) {
        $value === null => 'NULL',
        $value === true => 'TRUE',
        $value === false => 'FALSE',
        is_int($value) => (string) $value,
        is_string($value) => "pg_catalog.convert_from(pg_catalog.decode('" . bin2hex($value) . "', 'hex'), 'UTF8')",
        default => throw new InvalidArgumentException('A parameter input is an exact integer, Boolean, UTF-8 text or NULL.'),
    };
}

function observeTypedProjection(PDO $pdo, array $request): array
{
    $bindings = $request['bindings'];
    $constant = ($request['allowConstantProjection'] ?? false) === true;
    if (!admitsTypedParameterProjection($request['sql']) || !array_is_list($bindings)
        || ($bindings === [] && !$constant) || count($bindings) > 16) {
        return ['status' => 'outside-observer-scope'];
    }
    $args = implode(', ', array_map('parameterArgument', $bindings));
    $types = null;
    try {
        $pdo->exec('PREPARE ir_typed_projection AS ' . $request['sql']);
        $types = $pdo->query(
            'SELECT param::oid::text FROM pg_catalog.pg_prepared_statements AS prepared, '
            . 'unnest(prepared.parameter_types) WITH ORDINALITY AS t (param, slot) '
            . "WHERE prepared.name = 'ir_typed_projection' ORDER BY slot"
        )->fetchAll(PDO::FETCH_COLUMN);
        $statement = $pdo->query('EXECUTE ir_typed_projection' . ($bindings === [] ? '' : ' (' . $args . ')'));
        $fields = [];
        for ($index = 0; $index < $statement->columnCount(); ++$index) {
            $meta = $statement->getColumnMeta($index);
            $fields[] = [$meta['name'], $meta['native_type']];
        }
        $rows = $statement->fetchAll(PDO::FETCH_NUM);
        $statement->closeCursor();

        return ['status' => 'rows', 'inputOids' => $types, 'fields' => $fields, 'rows' => $rows];
    } catch (PDOException $error) {
        $pdo->exec('ROLLBACK');
        $pdo->exec('START TRANSACTION READ ONLY');

        return ['status' => 'db-error', 'state' => $error->errorInfo[0], 'inputOids' => $types];
    } finally {
        $pdo->exec('DEALLOCATE ALL');
    }
}

echo json_encode(['database' => $environment, 'php' => PHP_VERSION, 'binding' => 'PREPARE without a supplied input signature'], JSON_THROW_ON_ERROR) . "\n";
while (($line = fgets(STDIN)) !== false) {
    $request = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
    $answer = array_is_list($request)
        ? array_map(static fn (array $item): array => observeTypedProjection($pdo, $item), $request)
        : observeTypedProjection($pdo, $request);
    echo json_encode($answer, JSON_THROW_ON_ERROR) . "\n";
}
