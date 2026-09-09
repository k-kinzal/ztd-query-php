<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation\Rewrite\Expression;

use Override;
use SqlFaker\Grammar\Generation\Token\RewriteRule;
use SqlFaker\Grammar\Generation\Token\TerminalOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;

/**
 * sql_resolver.cc disallows empty rows and DEFAULT outside an INSERT table value constructor.
 */
final class TableValueConstructorRule implements RewriteRule
{
    /**
     * Completes empty query rows with NULL and replaces bare DEFAULT while preserving INSERT sources.
     */
    #[Override]
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        foreach ($sequence->occurrences('row_value_explicit') as $row) {
            $range = $sequence->range($row);
            if ($range === null) {
                continue;
            }
            $origin = $sequence->terminals[$range[0]];
            if (!$origin->within('table_value_constructor') || $this->isInsertSource($sequence, $origin)) {
                continue;
            }
            $values = $sequence->child($row, 'opt_values');
            if ($values === null) {
                continue;
            }
            $source = 'sql/sql_resolver.cc:resolve_table_value_constructor_values';
            $valuesRange = $sequence->range($values->id);
            if ($valuesRange === null) {
                $sequence = $sequence->replace($range[1] - 1, 0, [$sequence->insertedFor('NULL_SYM', $values->id, $source)], $source);
                continue;
            }
            for ($index = $valuesRange[0]; $index < $valuesRange[1]; ++$index) {
                $terminal = $sequence->terminals[$index];
                if ($terminal->name === 'DEFAULT_SYM' && ($terminal->rules[count($terminal->rules) - 1] ?? null) === 'expr_or_default') {
                    $sequence = $sequence->replace($index, 1, [$terminal->replaced('NULL_SYM', $source)], $source);
                }
            }
        }
        return $sequence;
    }

    /**
     * Mirrors PT_insert's direct-constructor conversion through parentheses, excluding set operations and subqueries.
     */
    public function isInsertSource(TerminalSequence $sequence, TerminalOccurrence $origin): bool
    {
        $position = array_search('insert_query_expression', $origin->rules, true);
        if ($position === false || array_diff(array_slice($origin->rules, $position + 1), [
            'query_expression_with_opt_locking_clauses', 'query_expression', 'query_expression_body',
            'query_expression_parens', 'query_primary', 'table_value_constructor', 'values_row_list', 'row_value_explicit',
        ]) !== []) {
            return false;
        }
        foreach ($origin->ancestors as $ancestor) {
            $bodies = 0;
            foreach ($sequence->productions as $child) {
                if ($child->parent === $ancestor && $child->rule === 'query_expression_body' && ++$bodies > 1) {
                    return false;
                }
            }
        }
        return true;
    }
}
