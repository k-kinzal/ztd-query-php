<?php

declare(strict_types=1);

namespace Fuzz\Observation;

use SqlParser\Parser\Node;

/**
 * Finds result ordering in the official grammar, independently of semantic analysis.
 *
 * Only a SELECT's outer query orders its rows. A derived table, CTE, window,
 * aggregate, comment or literal cannot order the enclosing result. Other commands
 * retain their protocol order; an unknown ordering contract never discards order.
 */
final class ResultOrder
{
    /**
     * Whether the observer must retain row order for this statement.
     */
    public function ordered(Node $statement): bool
    {
        if (in_array($statement->name, ['select', 'select_stmt'], true)) {
            return $this->query($statement);
        }
        if (in_array($statement->name, ['query', 'verb_clause', 'statement', 'start_entry', 'sql_statement', 'simple_statement_or_begin', 'simple_statement'], true)) {
            foreach ($statement->children as $child) {
                if ($child instanceof Node) {
                    return $this->ordered($child);
                }
            }
        }

        return true;
    }

    /**
     * Follows query enclosures, stopping at operands and nested query scopes.
     *
     * The 5.x grammar attaches global UNION ordering to its final unparenthesized
     * SELECT or union_order_or_limit. An enclosed arm's own order is local.
     */
    public function query(Node $query, bool $union = false): bool
    {
        foreach ($query->children as $child) {
            if ($child instanceof Node && ($child->name === 'order_clause' || ($child->name === 'opt_order_clause' && !$child->isEmpty()))) {
                return true;
            }
        }
        if ($query->name === 'query_expression_body' && count($query->children) !== 1) {
            return false;
        }
        foreach ($query->children as $child) {
            if ($child instanceof Node && in_array($child->name, ['union_clause', 'opt_union_clause', 'union_opt'], true) && !$child->isEmpty()) {
                return $this->query($child, true);
            }
        }
        foreach ($query->children as $child) {
            if (!$child instanceof Node || ($union && $child->name === 'select_paren')) {
                continue;
            }
            if (in_array($child->name, ['select_init', 'select_init2', 'select_paren', 'select_part2', 'select_into', 'select_from', 'union_list', 'union_order_or_limit', 'order_or_limit', 'select_stmt_with_into', 'query_expression', 'query_expression_body', 'query_expression_parens', 'query_expression_with_opt_locking_clauses'], true) && $this->query($child, $union)) {
                return true;
            }
        }

        return false;
    }
}
