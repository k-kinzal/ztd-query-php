<?php

declare(strict_types=1);

require_once __DIR__ . '/server_predicate_scope.php';

/**
 * Admits only the registered SELECT forms writing to the disposable target.
 */
function mysqlVarcharInsertQueryAdmitted(string $sql): bool
{
    $quote = chr(96);
    $name = static fn (string $part): string => '(?:' . $quote . $part . $quote . '|' . $part . ')';
    $schema = $name('ir_fixture') . '\\.';
    $target = $schema . $name('ir_varchar_query_target');
    $destination = $name('(?:row_key|nullable_value|required_value|empty_value)');
    $heading = '\\(' . $destination . '(?:,\\h*' . $destination . '){0,3}\\)';
    $alias = $name('(?:__ztd_source|r)');
    $column = '(?:' . $alias . '\\.)?' . $name('(?:label|bucket|alternate)');
    $text = <<<'PATTERN'
'(?:\\[\s\S]|''|[^'\\])*'
PATTERN;
    $textAtom = '(?:' . $column . '|' . $text . ')';
    $operand = '(?:NULL|-?[0-9]{1,10}|' . $textAtom . '|\\(' . $textAtom . ' COLLATE utf8mb4_bin\\))';
    $field = $operand . ' AS ' . $name('(?:p[0-3]|value)');
    $physical = $schema . $name('ir_varchar_query_(?:scan|empty)') . '(?: AS ' . $alias . ')?';
    $unit = '(?:DUAL|\\(SELECT 1\\) AS ' . $name('__ztd_unit') . ')';
    $source = '(?: FROM (?:' . $physical . '|' . $unit . '))?';
    $query = 'SELECT (?:DISTINCT )?' . $field . '(?:,\\h*' . $field . '){0,3}' . $source . '(?: WHERE (?&truth))?';

    return preg_match('/\\AINSERT INTO ' . $target . ' ' . $heading . ' ' . $query . '\\z'
        . fixturePredicateDefinition($operand, '(?:<=>|<=|<>|>=|=|<|>)') . '/iD', $sql) === 1;
}

/**
 * Observes all fixture rows, trigger order and the effects of rollback.
 */
function mysqlVarcharInsertQuery(PDO $pdo, string $sql): array
{
    if (!mysqlVarcharInsertQueryAdmitted($sql) || $pdo->query('SELECT DATABASE()')->fetchColumn() !== 'ir_fixture') {
        return ['status' => 'outside-observer-scope'];
    }

    return mysqlVarcharWrite($pdo, $sql, mysqlVarcharInsertQuerySnapshot(...));
}

function mysqlVarcharInsertQuerySnapshot(PDO $pdo): array
{
    return [
        'rows' => $pdo->query('SELECT row_key, nullable_value, required_value, empty_value FROM ir_fixture.ir_varchar_query_target')->fetchAll(PDO::FETCH_NUM),
        'events' => $pdo->query('SELECT ordinal, row_key, nullable_value, required_value, empty_value FROM ir_fixture.ir_varchar_query_events ORDER BY ordinal')->fetchAll(PDO::FETCH_NUM),
        'scan' => $pdo->query('SELECT label, bucket, alternate FROM ir_fixture.ir_varchar_query_scan')->fetchAll(PDO::FETCH_NUM),
        'empty' => $pdo->query('SELECT label, bucket, alternate FROM ir_fixture.ir_varchar_query_empty')->fetchAll(PDO::FETCH_NUM),
        'control' => $pdo->query('SELECT row_key, nullable_value, required_value, empty_value FROM ir_fixture.ir_varchar_query_control')->fetchAll(PDO::FETCH_NUM),
    ];
}
