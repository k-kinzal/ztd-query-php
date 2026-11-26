<?php

declare(strict_types=1);

require_once __DIR__ . '/server_predicate_scope.php';

/**
 * Allows ordered writes and predicates on one named disposable fixture.
 */
function mysqlVarcharAssignmentAdmitted(string $sql): bool
{
    $quote = chr(96);
    $name = static fn (string $part): string => '(?:' . $quote . $part . $quote . '|' . $part . ')';
    $table = $name('ir_fixture') . '\\.' . $name('ir_varchar_assignment');
    $alias = $name('(?:__ztd_source|r)');
    $qualifier = '(?:' . $name('(?:__ztd_source|r|ir_varchar_assignment)') . '\\.)?';
    $destination = $qualifier . $name('(?:nullable_value|required_value|empty_value|id)');
    $column = $qualifier . $name('(?:source_value|wide_source|nullable_value|required_value|empty_value|row_key|id)');
    $text = <<<'PATTERN'
'(?:\\[\s\S]|''|[^'\\])*'
PATTERN;
    $operand = '(?:' . $column . '|' . $text . '|NULL|-?[0-9]{1,10})';
    $assignment = $destination . '\\h*=\\h*' . $operand;
    $update = 'UPDATE ' . $table . '(?: AS ' . $alias . ')? SET ' . $assignment
        . '(?:,\\h*' . $assignment . '){0,4}(?: WHERE (?&truth))?';

    return preg_match('/\\A' . $update . '\\z'
        . fixturePredicateDefinition($operand, '(?:<=>|<=|<>|!=|>=|=|<|>)') . '/iD', $sql) === 1;
}

/**
 * Records the actual assigned values, diagnostics and transactional trigger effects.
 */
function mysqlVarcharAssignment(PDO $pdo, string $sql): array
{
    if (!mysqlVarcharAssignmentAdmitted($sql) || $pdo->query('SELECT DATABASE()')->fetchColumn() !== 'ir_fixture') {
        return ['status' => 'outside-observer-scope'];
    }

    return mysqlVarcharWrite($pdo, $sql, mysqlVarcharAssignmentSnapshot(...));
}

function mysqlVarcharAssignmentSnapshot(PDO $pdo): array
{
    return [
        'rows' => $pdo->query('SELECT row_key, source_value, nullable_value, required_value, empty_value, id, wide_source FROM ir_fixture.ir_varchar_assignment ORDER BY row_key')->fetchAll(PDO::FETCH_NUM),
        'events' => $pdo->query('SELECT row_key, old_nullable, old_required, old_empty, old_id, new_nullable, new_required, new_empty, new_id FROM ir_fixture.ir_varchar_assignment_events ORDER BY row_key')->fetchAll(PDO::FETCH_NUM),
    ];
}
