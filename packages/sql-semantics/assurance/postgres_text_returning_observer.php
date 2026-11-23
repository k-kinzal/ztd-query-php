<?php

declare(strict_types=1);

require __DIR__ . '/server_connection.php';
require __DIR__ . '/server_predicate_scope.php';
require __DIR__ . '/server_returning_observation.php';

[$pdo, $environment] = connectFixture('postgres');
if ($pdo->query('SELECT current_database()')->fetchColumn() !== 'ir_fixture') {
    throw new RuntimeException('The mutation observer requires the disposable ir_fixture database.');
}
$pdo->exec('DROP TABLE IF EXISTS public.ir_text_changes');
$pdo->exec("CREATE TABLE public.ir_text_changes (id INTEGER, label pg_catalog.text DEFAULT 'added α', note pg_catalog.text DEFAULT 'new memo')");
$pdo->exec("INSERT INTO public.ir_text_changes VALUES (1, 'old 値', 'first'), (1, 'old 値', 'first'), "
    . "(2, NULL, 'second'), (3, '', NULL), (NULL, 'unkeyed', 'other')");

function snapshotTextRows(PDO $pdo): array
{
    return $pdo->query('SELECT id, label, note FROM public.ir_text_changes')->fetchAll(PDO::FETCH_NUM);
}

function admittedTextMutation(string $sql): bool
{
    $name = static fn (string $part): string => '(?:' . $part . '|"' . $part . '")';
    $table = $name('public') . '\\.' . $name('ir_text_changes');
    $alias = $name('(?:__ztd_source|r|ir_text_changes)');
    $column = '(?:' . $alias . '\\.)?' . $name('(?:id|label|note)');
    $number = '-?[0-9]{1,5}';
    $int4 = '(?:' . $number . '|CAST\\(' . $number . ' AS pg_catalog\\.int4\\))';
    $operand = '(?:' . $int4 . '|' . $column . ')';
    $operator = '(?:[<>=]{1,2}|OPERATOR\\(pg_catalog\\.[<>=]{1,2}\\))';
    $where = '(?: WHERE (?&truth))?';
    $delete = 'DELETE FROM (?:ONLY )?' . $table . '(?: AS ' . $alias . ')?' . $where;
    $text = "'(?:[^'\\x00]|'')*'";
    $scalar = '(?:' . $int4 . '|' . $column . '|NULL|' . $text . '|CAST\\(' . $text . ' AS pg_catalog\\.text\\))';
    $assignment = $name('(?:id|label|note)') . ' = ' . $scalar;
    $update = 'UPDATE (?:ONLY )?' . $table . '(?: AS ' . $alias . ')? SET ' . $assignment . '(?:, ' . $assignment . ')*' . $where;
    $values = 'VALUES \\(' . $int4 . '\\)(?:, \\(' . $int4 . '\\))*';
    $select = 'SELECT ' . $int4 . '(?: AS ' . $name('v') . ')?(?: WHERE FALSE)?';
    $insert = 'INSERT INTO ' . $table . ' \\(' . $name('id') . '\\) (?:' . $values . '|' . $select . ')';
    $returning = fixtureReturningClause($column, $alias);

    return preg_match('/\\A(?:' . $delete . '|' . $update . '|' . $insert . ')' . $returning . '\\z'
        . fixturePredicateDefinition($operand, $operator) . '/iD', $sql) === 1;
}

function observeTextMutation(PDO $pdo, string $sql): array
{
    if (!admittedTextMutation($sql)) {
        return ['status' => 'outside-observer-scope'];
    }
    $before = snapshotTextRows($pdo);
    $pdo->beginTransaction();
    try {
        $statement = $pdo->prepare($sql);
        $statement->execute();
        $result = [
            'status' => 'mutation',
            'count' => $statement->rowCount(),
            'columns' => $statement->columnCount(),
            ...observeReturning($statement),
        ];
        $statement->closeCursor();
        $result['after'] = snapshotTextRows($pdo);
    } catch (PDOException $error) {
        $result = ['status' => 'db-error', 'state' => $error->errorInfo[0]];
    } finally {
        $pdo->rollBack();
    }
    $result['before'] = $before;
    $result['restored'] = snapshotTextRows($pdo);

    return $result;
}

echo json_encode(['database' => $environment, 'php' => PHP_VERSION, 'initial' => snapshotTextRows($pdo)], JSON_THROW_ON_ERROR) . "\n";
while (($line = fgets(STDIN)) !== false) {
    $input = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
    $requests = array_is_list($input) ? $input : [$input];
    $results = array_map(static fn (array $entry): array => observeTextMutation($pdo, $entry['sql']), $requests);
    echo json_encode(array_is_list($input) ? $results : $results[0], JSON_THROW_ON_ERROR) . "\n";
}
