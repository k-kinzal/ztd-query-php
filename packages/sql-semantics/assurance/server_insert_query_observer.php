<?php

declare(strict_types=1);

require __DIR__ . '/server_connection.php';
require __DIR__ . '/server_insert_support.php';

$engine = $argv[1];
[$pdo, $environment] = connectFixture($engine);
$schema = $engine === 'mysql' ? 'ir_fixture' : 'public';
$database = $pdo->query($engine === 'mysql' ? 'SELECT DATABASE()' : 'SELECT current_database()')->fetchColumn();
if ($database !== 'ir_fixture') {
    throw new RuntimeException('The query INSERT observer requires the disposable ir_fixture database.');
}

function admitInsertQuery(string $sql, string $schema): bool
{
    $quote = '["' . chr(96) . ']?';
    $name = static fn (string $value): string => $quote . $value . $quote;
    $target = $name($schema) . '\\.' . $name('ir_insert_target');
    $column = $name('(?:id|delta|stamp)');
    $scan = $name($schema) . '\\.' . $name('ir_insert_(?:scan|zero|target)');
    $alias = $name('(?:__ztd_source|s)');
    $returnQualifier = $name('(?:ir_insert_target|r)');
    $returning = $schema === 'public' ? fixtureReturningClause('(?:' . $returnQualifier . '\\.)?' . $column, $returnQualifier) : '';
    $targetAlias = $schema === 'public' ? '(?: AS r)?' : '';
    $qualified = '(?:' . $alias . '\\.)?' . $name('(?:id|delta)');
    $value = 'NULL|-?[0-9]{1,43}|CAST\\((?:-?[0-9]{1,10}|NULL) AS pg_catalog\\.int4\\)|' . $qualified;
    $arithmetic = '(?:[+*-]|OPERATOR\\(pg_catalog\\.[+*-]\\))';
    $definition = '(?(DEFINE)(?<value>(?:' . $value . '|\\((?&value) ' . $arithmetic . ' (?&value)\\))))';
    $as = ' AS ' . $name('(?:p[0-9]+|left_field|right_field)');
    $field = '(?&value)' . $as;
    $unit = '\\(SELECT 1\\) AS ' . $name('__ztd_unit') . ($schema === 'public' ? '' : '|DUAL');
    $from = '(?: FROM (?:(?:ONLY )?' . $scan . '(?: AS ' . $alias . ')?|' . $unit . '))?';
    $operator = '(?:<|>|=|<=|>=|<>|OPERATOR\\(pg_catalog\\.(?:<|>|=|<=|>=|<>)\\))';
    $filter = '(?:(?&value) ' . $operator . ' (?&value))';
    $where = '(?: WHERE (?:TRUE|FALSE|NULL|' . $filter . '|\\(' . $filter . '\\)))?';
    $head = '\\AINSERT INTO ' . $target . $targetAlias . '\\h*\\(' . $column . '(?:\\h*,\\h*' . $column . ')*\\) ';
    $body = 'SELECT (?:DISTINCT )?' . $field . '(?:,\\h*' . $field . ')*' . $from . $where . $returning . '\\z';

    return preg_match('/' . $definition . $head . $body . '/iD', $sql) === 1;
}

function queryInsertState(PDO $pdo, string $schema): array
{
    $rows = insertedRows($pdo, $schema);
    $rows['scan'] = $pdo->query('SELECT id, delta FROM ' . $schema . '.ir_insert_scan')->fetchAll(PDO::FETCH_NUM);
    $rows['zero'] = $pdo->query('SELECT id, delta FROM ' . $schema . '.ir_insert_zero')->fetchAll(PDO::FETCH_NUM);

    return $rows;
}

insertFixture($pdo, $engine, $schema);
foreach (['scan', 'zero'] as $table) {
    $pdo->exec('DROP TABLE IF EXISTS ' . $schema . '.ir_insert_' . $table);
    $pdo->exec('CREATE TABLE ' . $schema . '.ir_insert_' . $table . ' (id INTEGER, delta INTEGER)');
}
$pdo->exec('INSERT INTO ' . $schema . '.ir_insert_scan VALUES (2,3),(2,3),(2,7),(-1,4),(3,NULL),(NULL,8)');
echo json_encode(['database' => $environment, 'php' => PHP_VERSION, 'initial' => queryInsertState($pdo, $schema)], JSON_THROW_ON_ERROR) . "\n";
while (($line = fgets(STDIN)) !== false) {
    $item = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
    $requests = array_is_list($item) ? $item : [$item];
    $responses = [];
    foreach ($requests as $request) {
        $admit = $engine === 'postgres' && ($request['form'] ?? 'select') === 'values' ? admitInsert(...) : admitInsertQuery(...);
        $responses[] = insertEffect($pdo, $schema, $request['sql'], $admit, queryInsertState(...));
    }
    echo json_encode(array_is_list($item) ? $responses : $responses[0], JSON_THROW_ON_ERROR) . "\n";
}
