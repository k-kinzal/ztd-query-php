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
$pdo->exec('DROP TABLE IF EXISTS public.ir_text_inputs');
$pdo->exec('CREATE TABLE public.ir_text_inputs (note pg_catalog.text, id INTEGER, label pg_catalog.text)');
$pdo->exec("INSERT INTO public.ir_text_inputs VALUES ('other', 7, 'copied α'), ('other', 7, 'copied α'), "
    . "('memo', 8, NULL), ('blank', 9, ''), (NULL, 10, '東京')");

function snapshotTextRows(PDO $pdo): array
{
    return $pdo->query('SELECT id, label, note FROM public.ir_text_changes')->fetchAll(PDO::FETCH_NUM);
}

function snapshotInputRows(PDO $pdo): array
{
    return $pdo->query('SELECT note, id, label FROM public.ir_text_inputs')->fetchAll(PDO::FETCH_NUM);
}

function admittedTextMutation(string $sql, bool $collatedComparisons): bool
{
    $name = static fn (string $part): string => '(?:' . $part . '|"' . $part . '")';
    $table = $name('public') . '\\.' . $name('ir_text_changes');
    $alias = $name('(?:__ztd_source|r|s|ir_text_changes|ir_text_inputs)');
    $column = '(?:' . $alias . '\\.)?' . $name('(?:id|label|note)');
    $number = '-?[0-9]{1,10}';
    $int4 = '(?:' . $number . '|CAST\\(' . $number . ' AS pg_catalog\\.int4\\))';
    $operand = '(?:' . $int4 . '|' . $column . ')';
    $operator = '(?:[<>=]{1,2}|OPERATOR\\(pg_catalog\\.[<>=]{1,2}\\))';
    if ($collatedComparisons) {
        $literal = "'(?:[^'\\x00]|'')*'";
        $text = '(?:' . $column . '|NULL|' . $literal . '|CAST\\((?:' . $literal . '|NULL) AS pg_catalog\\.text\\))';
        $operand = '(?:' . $text . '|\\(' . $text . ' COLLATE pg_catalog\\."C"\\)|' . $text . ' COLLATE pg_catalog\\."C")';
        $operator = '(?:[<>!=]{1,2}|OPERATOR\\(pg_catalog\\.[<>!=]{1,2}\\))';
    }
    $where = '(?: WHERE (?&truth))?';
    $delete = 'DELETE FROM (?:ONLY )?' . $table . '(?: AS ' . $alias . ')?' . $where;
    $text = "'(?:[^'\\x00]|'')*'";
    $scalar = '(?:' . $int4 . '|' . $column . '|NULL|' . $text . '|CAST\\(' . $text . ' AS pg_catalog\\.text\\))';
    $assignment = $name('(?:id|label|note)') . ' = ' . $scalar;
    $update = 'UPDATE (?:ONLY )?' . $table . '(?: AS ' . $alias . ')? SET ' . $assignment . '(?:, ' . $assignment . ')*' . $where;
    $typedNull = 'CAST\\(NULL AS pg_catalog\\.(?:int4|text)\\)';
    $cell = '(?:' . $int4 . '|NULL|' . $typedNull . '|' . $text . '|CAST\\(' . $text . ' AS pg_catalog\\.text\\))';
    $row = '\\(' . $cell . '(?:, ' . $cell . ')*\\)';
    $values = 'VALUES ' . $row . '(?:, ' . $row . ')*';
    $input = $name('public') . '\\.' . $name('ir_text_inputs');
    $label = '(?:[A-Za-z_][A-Za-z_0-9]*|"(?:[^"\\x00]|"")+")';
    $output = '(?:' . $cell . '|' . $column . ')(?: AS ' . $label . ')?';
    $select = 'SELECT (?:DISTINCT )?' . $output . '(?:, ' . $output . ')*'
        . '(?: FROM (?:ONLY )?' . $input . '(?: AS ' . $alias . ')?)?' . $where;
    $written = $name('(?:id|label|note)');
    $insert = 'INSERT INTO ' . $table . ' \\(' . $written . '(?:, ' . $written . ')*\\) (?:' . $values . '|' . $select . ')';
    $returning = fixtureReturningClause($column, $alias);

    return preg_match('/\\A(?:' . $delete . '|' . $update . '|' . $insert . ')' . $returning . '\\z'
        . fixturePredicateDefinition($operand, $operator) . '/iD', $sql) === 1;
}

function observeTextMutation(PDO $pdo, string $sql, bool $observeInput, bool $collatedComparisons): array
{
    if (!admittedTextMutation($sql, $collatedComparisons)) {
        return ['status' => 'outside-observer-scope'];
    }
    $before = snapshotTextRows($pdo);
    $input = $observeInput ? snapshotInputRows($pdo) : [];
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
        if ($observeInput) {
            $result['inputAfter'] = snapshotInputRows($pdo);
        }
    } catch (PDOException $error) {
        $result = ['status' => 'db-error', 'state' => $error->errorInfo[0]];
    } finally {
        $pdo->rollBack();
    }
    $result['before'] = $before;
    $result['restored'] = snapshotTextRows($pdo);
    if ($observeInput) {
        $result['inputBefore'] = $input;
        $result['inputRestored'] = snapshotInputRows($pdo);
    }

    return $result;
}

function observeTextSelect(PDO $pdo, string $sql): array
{
    $pdo->beginTransaction();
    try {
        $pdo->exec('SET TRANSACTION READ ONLY');
        $statement = $pdo->prepare($sql);
        $statement->execute();
        $response = ['status' => 'rows', ...observeReturning($statement)];
        $statement->closeCursor();

        return $response;
    } catch (PDOException $error) {
        return ['status' => 'db-error', 'state' => $error->errorInfo[0]];
    } finally {
        $pdo->rollBack();
    }
}

$castOverrides = $pdo->query("SELECT count(*) FROM pg_catalog.pg_cast WHERE castsource = 'pg_catalog.int4'::pg_catalog.regtype AND casttarget = 'pg_catalog.text'::pg_catalog.regtype")->fetchColumn();
echo json_encode(['database' => $environment, 'php' => PHP_VERSION, 'initial' => snapshotTextRows($pdo), 'input' => snapshotInputRows($pdo),
    'int4TextCastOverrides' => (int) $castOverrides], JSON_THROW_ON_ERROR) . "\n";
while (($line = fgets(STDIN)) !== false) {
    $input = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
    $requests = array_is_list($input) ? $input : [$input];
    $results = array_map(
        static fn (array $entry): array => ($entry['observeReadOnly'] ?? false) === true
            ? observeTextSelect($pdo, $entry['sql'])
            : observeTextMutation($pdo, $entry['sql'], $entry['observeInput'] ?? false, $entry['collatedComparisons'] ?? false),
        $requests
    );
    echo json_encode(array_is_list($input) ? $results : $results[0], JSON_THROW_ON_ERROR) . "\n";
}
