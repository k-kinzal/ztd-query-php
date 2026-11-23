<?php

declare(strict_types=1);

/**
 * Allows explicit ordinary VALUES writes on the one disposable fixture.
 */
function mysqlVarcharInsertAdmitted(string $sql): bool
{
    $quote = chr(96);
    $name = static fn (string $part): string => '(?:' . $quote . $part . $quote . '|' . $part . ')';
    $table = $name('ir_fixture') . '\\.' . $name('ir_varchar_insert');
    $column = $name('(?:row_key|nullable_value|required_value|empty_value)');
    $heading = '\\(' . $column . '(?:,\\h*' . $column . '){0,3}\\)';
    $text = <<<'PATTERN'
'(?:\\[\s\S]|''|[^'\\])*'
PATTERN;
    $value = '(?:NULL|-?[0-9]{1,10}|' . $text . ')';
    $row = '\\(' . $value . '(?:,\\h*' . $value . '){0,3}\\)';

    return preg_match('/\\AINSERT INTO ' . $table . ' ' . $heading . ' VALUES? '
        . $row . '(?:,\\h*' . $row . '){0,3}\\z/iD', $sql) === 1;
}

function mysqlVarcharInsert(PDO $pdo, string $sql): array
{
    if (!mysqlVarcharInsertAdmitted($sql) || $pdo->query('SELECT DATABASE()')->fetchColumn() !== 'ir_fixture') {
        return ['status' => 'outside-observer-scope'];
    }

    return mysqlVarcharWrite($pdo, $sql, mysqlVarcharInsertSnapshot(...));
}

function mysqlVarcharInsertSnapshot(PDO $pdo): array
{
    return [
        'rows' => $pdo->query('SELECT row_key, nullable_value, required_value, empty_value FROM ir_fixture.ir_varchar_insert ORDER BY row_key')->fetchAll(PDO::FETCH_NUM),
        'events' => $pdo->query('SELECT ordinal, row_key, nullable_value, required_value, empty_value FROM ir_fixture.ir_varchar_insert_events ORDER BY ordinal')->fetchAll(PDO::FETCH_NUM),
    ];
}
