<?php

declare(strict_types=1);

require __DIR__ . '/server_connection.php';
require __DIR__ . '/server_predicate_scope.php';
require __DIR__ . '/server_returning_observation.php';

[$pdo, $environment] = connectFixture('postgres');
if ($pdo->query('SELECT current_database()')->fetchColumn() !== 'ir_fixture') {
    throw new RuntimeException('The Boolean mutation observer requires the disposable ir_fixture database.');
}
$pdo->exec('DROP TABLE IF EXISTS public.ir_bool_changes');
$pdo->exec('CREATE TABLE public.ir_bool_changes (id INTEGER, enabled BOOLEAN DEFAULT TRUE, fallback BOOLEAN DEFAULT FALSE)');
$pdo->exec('INSERT INTO public.ir_bool_changes VALUES (1, TRUE, FALSE), (1, TRUE, FALSE), (2, FALSE, TRUE), (3, NULL, FALSE), (NULL, TRUE, NULL)');

$pdo->exec('DROP TABLE IF EXISTS public.ir_bool_inputs');
$pdo->exec('CREATE TABLE public.ir_bool_inputs (fallback BOOLEAN, id INTEGER, enabled BOOLEAN)');
$pdo->exec('INSERT INTO public.ir_bool_inputs VALUES (TRUE, 2, FALSE), (TRUE, 2, FALSE), (FALSE, 3, NULL), (NULL, NULL, TRUE), (FALSE, 4, FALSE)');

function snapshotBooleanInputs(PDO $pdo): array
{
    return $pdo->query('SELECT fallback, id, enabled FROM public.ir_bool_inputs')->fetchAll(PDO::FETCH_NUM);
}

function snapshotBooleanTarget(PDO $pdo): array
{
    return $pdo->query('SELECT id, enabled, fallback FROM public.ir_bool_changes')->fetchAll(PDO::FETCH_NUM);
}

function admittedBooleanMutation(string $sql): bool
{
    $name = static fn (string $value): string => '(?:' . $value . '|"' . $value . '")';
    $table = '(?:public|"public")\\.(?:ir_bool_changes|"ir_bool_changes")';
    $alias = '(?:f|ir_bool_changes|"?__ztd_source"?)';
    $column = '(?:' . $alias . '\\.)?' . $name('(?:id|enabled|fallback)');
    $label = '(?:[A-Za-z_][A-Za-z_0-9]*|"(?:[^"\x00]|"")+")';
    $number = '-?[0-9]{1,10}';
    $int4 = '(?:' . $number . '|CAST\\(' . $number . ' AS pg_catalog\\.int4\\))';
    $operator = '(?:[<>=]{1,2}|OPERATOR\\(pg_catalog\\.[<>=]{1,2}\\))';
    $operand = '(?:' . $int4 . '|' . $column . ')';
    $where = '(?: WHERE (?&truth))?';
    $target = $table . '(?: AS ' . $alias . ')?';
    $delete = 'DELETE FROM ' . $target . $where;
    $update = 'UPDATE ' . $target . ' SET ' . $name('id') . '\\h*=\\h*' . $int4 . $where;
    $row = '\\(' . $int4 . '\\)';
    $insert = 'INSERT INTO ' . $table . ' \\(' . $name('id') . '\\) (?:VALUES ' . $row . '(?:, ' . $row . ')*'
        . '|SELECT ' . $int4 . '(?: AS ' . $label . ')?' . $where . ')';

    return preg_match('/\\A(?:' . $delete . '|' . $update . '|' . $insert . ')' . fixtureReturningClause($column, $alias)
        . '\\z' . fixturePredicateDefinition($operand, $operator) . '/iD', $sql) === 1;
}

function admittedBooleanWrite(string $sql, bool $inspectUnknown): bool
{
    $target = '(?:public|"public")\\.(?:ir_bool_changes|"ir_bool_changes")';
    $source = '(?:public|"public")\\.(?:ir_bool_inputs|"ir_bool_inputs")';
    $alias = '(?:f|s|ir_bool_changes|ir_bool_inputs|"__ztd_source")';
    $name = '(?:id|"id"|enabled|"enabled"|fallback|"fallback")';
    $column = '(?:' . $alias . '\\.)?' . $name;
    $label = '(?:[A-Za-z_][A-Za-z_0-9]*|"(?:[^"\x00]|"")+")';
    $number = '-?[0-9]{1,10}';
    $plain = '(?:TRUE|FALSE|NULL)';
    $unknown = '\x27(?:[^\x27\x00]|\x27\x27)*\x27';
    $sourceValue = $inspectUnknown ? '(?:' . $unknown . '|' . $plain . ')' : $plain;
    $castType = $inspectUnknown ? '(?:pg_catalog\\.bool|public\\.no_checked_bool)' : 'pg_catalog\\.bool';
    $typed = '(?:CAST\\(' . $number . ' AS pg_catalog\\.int4\\)|CAST\\(NULL AS pg_catalog\\.int4\\)'
        . '|CAST\\(' . $sourceValue . ' AS ' . $castType . '\\)|CAST\\(\\x27true\\x27 AS pg_catalog\\.text\\))';
    $literal = $inspectUnknown ? '|' . $unknown . '|\\(' . $unknown . '\\)' : '';
    $scalar = '(?:' . $number . '|' . $typed . '|' . $plain . $literal . ')';
    $operand = '(?:' . $scalar . '|' . $column . ')';
    $operator = '(?:[<>=]{1,2}|OPERATOR\\(pg_catalog\\.[<>=]{1,2}\\))';
    $where = '(?: WHERE (?&truth))?';
    $assignment = $name . '\\h*=\\h*' . $operand;
    $update = 'UPDATE ' . $target . '(?: AS ' . $alias . ')? SET ' . $assignment . '(?:, ' . $assignment . ')*' . $where;
    $row = '\\(' . $scalar . '(?:,\\h*' . $scalar . ')*\\)';
    $output = '(?:' . $operand . '|(?&truth))(?: AS ' . $label . ')?';
    $select = 'SELECT (?:DISTINCT )?' . $output . '(?:,\\h*' . $output . ')*(?: FROM ' . $source . '(?: AS ' . $alias . ')?)?' . $where;
    $insert = 'INSERT INTO ' . $target . ' \\(' . $name . '(?:,\\h*' . $name . ')*\\)'
        . ' (?:VALUES ' . $row . '(?:,\\h*' . $row . ')*|' . $select . ')';

    return preg_match('/\\A(?:' . $insert . '|' . $update . ')' . fixtureReturningClause($column, $alias) . '\\z'
        . fixturePredicateDefinition($operand, $operator, true) . '/iD', $sql) === 1;
}

function observeBooleanMutation(PDO $pdo, string $sql, bool $observeWrites, bool $inspectUnknown): array
{
    if (!($observeWrites ? admittedBooleanWrite($sql, $inspectUnknown) : admittedBooleanMutation($sql))) {
        return ['status' => 'outside-observer-scope'];
    }
    $before = snapshotBooleanTarget($pdo);
    $input = $observeWrites ? snapshotBooleanInputs($pdo) : [];
    $pdo->beginTransaction();
    try {
        $statement = $pdo->prepare($sql);
        $statement->execute();
        $result = [
            'status' => 'mutation',
            'count' => $statement->rowCount(),
            'columns' => $statement->columnCount(),
            ...observeReturning($statement, true),
        ];
        $statement->closeCursor();
        $result['after'] = snapshotBooleanTarget($pdo);
        if ($observeWrites) {
            $result['inputAfter'] = snapshotBooleanInputs($pdo);
        }
    } catch (PDOException $error) {
        $result = ['status' => 'db-error', 'state' => $error->errorInfo[0]];
    } finally {
        $pdo->rollBack();
    }

    return [...$result, 'before' => $before, 'restored' => snapshotBooleanTarget($pdo),
        ...($observeWrites ? ['inputBefore' => $input, 'inputRestored' => snapshotBooleanInputs($pdo)] : [])];
}

function observeBooleanDefault(PDO $pdo, string $sql): array
{
    $target = '(?:public|"public")\\.(?:ir_bool_decode_default|"ir_bool_decode_default")';
    $field = '(?:enabled|"enabled")';
    $value = '(?:TRUE|FALSE|\x27(?:[^\x27\x00]|\x27\x27)*\x27)';
    $pattern = '/\\ACREATE TABLE ' . $target . ' \\(' . $field
        . ' (?:BOOLEAN|pg_catalog\\.bool)(?: NULL)? DEFAULT ' . $value . '\\)(?: USING heap)?\\z/iD';
    if (preg_match($pattern, $sql) !== 1) {
        return ['status' => 'outside-observer-scope'];
    }
    $pdo->beginTransaction();
    try {
        $pdo->exec($sql);
        $row = $pdo->query('INSERT INTO public.ir_bool_decode_default DEFAULT VALUES RETURNING enabled');
        $result = observeReturning($row, true);
        $row->closeCursor();
    } finally {
        $pdo->rollBack();
    }
    $restored = $pdo->query("SELECT pg_catalog.to_regclass('public.ir_bool_decode_default') IS NULL")->fetchColumn();

    return ['status' => 'Boolean default', 'removedAfterRollback' => $restored, ...$result];
}

echo json_encode(['database' => $environment, 'php' => PHP_VERSION, 'initial' => snapshotBooleanTarget($pdo),
    'input' => snapshotBooleanInputs($pdo)], JSON_THROW_ON_ERROR) . "\n";
while (($line = fgets(STDIN)) !== false) {
    $input = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
    $requests = array_is_list($input) ? $input : [$input];
    $results = array_map(
        static fn (array $request): array => ($request['observeBooleanDefault'] ?? false) === true
            ? observeBooleanDefault($pdo, $request['sql'])
            : observeBooleanMutation($pdo, $request['sql'], ($request['observeWrites'] ?? false) === true, ($request['inspectUnknown'] ?? false) === true),
        $requests,
    );
    echo json_encode(array_is_list($input) ? $results : $results[0], JSON_THROW_ON_ERROR) . "\n";
}
