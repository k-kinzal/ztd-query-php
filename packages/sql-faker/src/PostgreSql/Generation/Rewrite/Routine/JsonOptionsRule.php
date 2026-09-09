<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql\Generation\Rewrite\Routine;

use Override;
use SqlFaker\Grammar\Generation\Token\RewriteRule;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;

/**
 * Applies syntax-level SQL/JSON option restrictions from parse_expr.c/transformJsonFuncExpr.
 */
final class JsonOptionsRule implements RewriteRule
{
    /**
     * Keeps options scoped to their function or JSON_TABLE column, preserving nested expressions.
     */
    #[Override]
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        foreach (['func_expr_common_subexpr', 'json_table_column_definition'] as $rule) {
            foreach ($sequence->occurrences($rule) as $owner) {
                $range = $sequence->range($owner);
                if ($range === null) {
                    continue;
                }
                $kind = $rule === 'func_expr_common_subexpr' ? $sequence->nameAt($range[0]) : null;
                if ($rule === 'json_table_column_definition' && $sequence->child($owner, 'json_on_error_clause_opt') !== null) {
                    $kind = 'JSON_EXISTS';
                }
                $wrapper = $sequence->child($owner, 'json_wrapper_behavior');
                $quotes = $sequence->child($owner, 'json_quotes_clause_opt');
                $wrapRange = $wrapper === null ? null : $sequence->range($wrapper->id);
                $quoteRange = $quotes === null ? null : $sequence->range($quotes->id);
                if ($wrapRange !== null && $quoteRange !== null && $sequence->nameAt($wrapRange[0]) === 'WITH'
                    && $sequence->nameAt($quoteRange[0]) === 'OMIT') {
                    $sequence = $sequence->replace($quoteRange[0], $quoteRange[1] - $quoteRange[0], [], 'parse_expr.c:json-wrapper-quotes');
                }
                if ($kind === 'JSON_VALUE') {
                    $sequence = $this->returningFormat($sequence, $owner);
                }
                $allowed = match ($kind) {
                    'JSON_EXISTS' => ['ERROR_P', 'TRUE_P', 'FALSE_P', 'UNKNOWN'],
                    'JSON_VALUE' => ['ERROR_P', 'NULL_P', 'DEFAULT'],
                    'JSON_QUERY' => ['ERROR_P', 'NULL_P', 'DEFAULT', 'EMPTY_P'],
                    default => null,
                };
                if ($allowed !== null) {
                    $sequence = $this->behaviors($sequence, $owner, $allowed);
                }
            }
        }
        return $sequence;
    }

    /**
     * Removes only the explicit output format forbidden by JSON_VALUE.
     */
    public function returningFormat(TerminalSequence $sequence, int $owner): TerminalSequence
    {
        $returning = $sequence->child($owner, 'json_returning_clause_opt');
        $format = $returning === null ? null : $sequence->child($returning->id, 'json_format_clause_opt');
        $range = $format === null ? null : $sequence->range($format->id);
        return $range === null ? $sequence : $sequence->replace($range[0], $range[1] - $range[0], [], 'parse_expr.c:json-value-format');
    }

    /**
     * Retains valid behavior alternatives and replaces only those forbidden for this function.
     * @param list<string> $allowed
     */
    public function behaviors(TerminalSequence $sequence, int $owner, array $allowed): TerminalSequence
    {
        foreach (['json_behavior_clause_opt', 'json_on_error_clause_opt'] as $rule) {
            $clause = $sequence->child($owner, $rule);
            if ($clause === null) {
                continue;
            }
            foreach ($sequence->productions as $behavior) {
                if ($behavior->parent !== $clause->id || $behavior->rule !== 'json_behavior') {
                    continue;
                }
                $range = $sequence->range($behavior->id);
                if ($range !== null && !in_array($sequence->nameAt($range[0]), $allowed, true)) {
                    $sequence = $sequence->replace($range[0], $range[1] - $range[0], [
                        $sequence->insertedFor('ERROR_P', $behavior->id, 'parse_expr.c:json-behavior'),
                    ], 'parse_expr.c:json-behavior');
                }
            }
        }
        return $sequence;
    }
}
