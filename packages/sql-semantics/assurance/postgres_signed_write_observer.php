<?php

declare(strict_types=1);

require __DIR__ . '/server_connection.php';
require __DIR__ . '/server_predicate_scope.php';
require __DIR__ . '/server_returning_observation.php';

[$pdo, $environment] = connectFixture('postgres');
if ($pdo->query('SELECT current_database()')->fetchColumn() !== 'ir_fixture') {
    throw new RuntimeException('The signed write observer requires the disposable ir_fixture database.');
}
$pdo->exec('DROP TABLE IF EXISTS public.ir_signed_changes, public.ir_signed_inputs');
$pdo->exec("CREATE TABLE public.ir_signed_changes (id INTEGER DEFAULT 11, wide BIGINT DEFAULT 2147483648, note TEXT DEFAULT 'kept', enabled BOOLEAN DEFAULT TRUE)");
$pdo->exec("INSERT INTO public.ir_signed_changes VALUES (1,-7,'one',TRUE), (1,-7,'one',TRUE), (2,-9223372036854775808,'min',FALSE), (3,9223372036854775807,'max',NULL), (NULL,NULL,NULL,TRUE)");
$pdo->exec('CREATE TABLE public.ir_signed_inputs (wide BIGINT, id INTEGER)');
$pdo->exec('INSERT INTO public.ir_signed_inputs VALUES (23,1), (23,1), (-9223372036854775808,2), (9223372036854775807,3), (NULL,NULL)');

function signedTarget(PDO $pdo): array
{
    return $pdo->query('SELECT id, wide, note, enabled FROM public.ir_signed_changes')->fetchAll(PDO::FETCH_NUM);
}

function signedInput(PDO $pdo): array
{
    return $pdo->query('SELECT wide, id FROM public.ir_signed_inputs')->fetchAll(PDO::FETCH_NUM);
}

function admittedSignedWrite(string $sql): bool
{
    $target = '(?:public|"public")\\.(?:ir_signed_changes|"ir_signed_changes")';
    $source = '(?:public|"public")\\.(?:ir_signed_inputs|"ir_signed_inputs")';
    $alias = '(?:m|s|ir_signed_changes|ir_signed_inputs|"__ztd_source")';
    $name = '(?:id|"id"|wide|"wide"|note|"note"|enabled|"enabled")';
    $column = '(?:' . $alias . '\\.)?' . $name;
    $label = '(?:[A-Za-z_][A-Za-z_0-9]*|"(?:[^"\x00]|"")+")';
    $number = '-?[0-9]{1,19}';
    $simple = '(?:' . $number . '|NULL|TRUE|FALSE)';
    $nativeType = '(?:pg_catalog\\.(?:int4|int8|text)|bigint|integer)';
    $text = '\x27(?:[^\x27\x00]|\x27\x27)*\x27';
    $cast = 'CAST\\((?:' . $simple . '|' . $column . '|' . $text . ') AS ' . $nativeType . '\\)';
    $scalar = '(?:' . $simple . '|' . $cast . ')';
    $operand = '(?:' . $scalar . '|' . $column . ')';
    $operator = '(?:[<>=]{1,2}|OPERATOR\\(pg_catalog\\.[<>=]{1,2}\\))';
    $where = '(?: WHERE (?&truth))?';
    $writeTarget = $target . '(?: AS ' . $alias . ')?';
    $assignment = $name . '\\h*=\\h*' . $operand;
    $update = 'UPDATE ' . $writeTarget . ' SET ' . $assignment . '(?:,\\h*' . $assignment . ')*' . $where;
    $delete = 'DELETE FROM ' . $writeTarget . $where;
    $cell = '\\(' . $scalar . '(?:,\\h*' . $scalar . ')*\\)';
    $result = $operand . '(?: AS ' . $label . ')?';
    $query = 'SELECT (?:DISTINCT )?' . $result . '(?:,\\h*' . $result . ')*'
        . '(?: FROM ' . $source . '(?: AS ' . $alias . ')?)?' . $where;
    $insert = 'INSERT INTO ' . $target . ' \\(' . $name . '(?:,\\h*' . $name . ')*\\) (?:VALUES '
        . $cell . '(?:,\\h*' . $cell . ')*|' . $query . ')';

    return preg_match('/\\A(?:' . $insert . '|' . $update . '|' . $delete . ')'
        . fixtureReturningClause($column, $alias) . '\\z' . fixturePredicateDefinition($operand, $operator) . '/iD', $sql) === 1;
}

function observeSignedWrite(PDO $pdo, string $sql): array
{
    if (!admittedSignedWrite($sql)) {
        return ['status' => 'outside-observer-scope'];
    }
    $before = signedTarget($pdo);
    $input = signedInput($pdo);
    $pdo->beginTransaction();
    try {
        $statement = $pdo->prepare($sql);
        $statement->execute();
        $result = [
            'status' => 'mutation',
            'count' => $statement->rowCount(),
            ...observeReturning($statement, true),
        ];
        $statement->closeCursor();
        $result['after'] = signedTarget($pdo);
        $result['inputAfter'] = signedInput($pdo);
    } catch (PDOException $error) {
        $result = ['status' => 'db-error', 'state' => $error->errorInfo[0]];
    } finally {
        $pdo->rollBack();
    }

    return [...$result, 'before' => $before, 'restored' => signedTarget($pdo),
        'inputBefore' => $input, 'inputRestored' => signedInput($pdo)];
}

echo json_encode(['database' => $environment, 'php' => PHP_VERSION, 'initial' => signedTarget($pdo), 'input' => signedInput($pdo)], JSON_THROW_ON_ERROR) . "\n";
while (($line = fgets(STDIN)) !== false) {
    $input = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
    $results = array_map(
        static fn (array $request): array => observeSignedWrite($pdo, $request['sql']),
        array_is_list($input) ? $input : [$input],
    );
    echo json_encode(array_is_list($input) ? $results : $results[0], JSON_THROW_ON_ERROR) . "\n";
}
