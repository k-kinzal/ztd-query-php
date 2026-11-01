<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation\Rewrite\Query;

use SqlFaker\Generation\Token\RewriteRule;
use SqlFaker\Generation\Token\TerminalSequence;

/**
 * Legacy table_factor shares SELECT and relation-list productions with derived queries.
 * A SELECT used as a table needs parentheses and an alias; a UNION needs a query on its left.
 * @see https://github.com/mysql/mysql-server/blob/mysql-5.6.51/sql/sql_yacc.yy
 * @see https://github.com/mysql/mysql-server/blob/mysql-5.7.44/sql/sql_yacc.yy
 */
final class LegacyDerivedTableRule implements RewriteRule
{
    /**
     * Completes legacy query roles without turning an ordinary nested join into a query.
     */
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        foreach ($sequence->occurrences('table_factor') as $id) {
            $sequence = $this->bare($sequence, $id);
            $sequence = $this->parenthesized($sequence, $id);
        }
        return $sequence;
    }

    /**
     * Wraps a bare SELECT only when it is a relation operand, not the head of a derived query.
     */
    public function bare(TerminalSequence $sequence, int $id): TerminalSequence
    {
        if ($sequence->child($id, 'select_derived_init') === null && $sequence->child($id, 'select_options') === null) {
            return $sequence;
        }
        $range = $sequence->range($id);
        if ($range === null || $sequence->nameAt($range[0]) !== 'SELECT_SYM') {
            return $sequence;
        }
        $derived = $sequence->terminals[$range[0]]->ancestor('select_derived');
        if ($derived !== null && $sequence->range($derived) === $range) {
            return $sequence;
        }
        $rule = 'sql_yacc.yy:legacy-derived-table';
        return $sequence->replace($range[0], $range[1] - $range[0], [
            $sequence->insertedFor('(', $id, $rule),
            ...array_slice($sequence->terminals, $range[0], $range[1] - $range[0]),
            $sequence->insertedFor(')', $id, $rule, 1),
            $sequence->insertedFor('AS', $id, $rule, 2),
            $sequence->insertedFor('IDENT_QUOTED', $id, $rule, 3),
        ], $rule);
    }

    /**
     * Supplies a SELECT for relation lists used by UNION, ordering or a derived-table alias.
     */
    public function parenthesized(TerminalSequence $sequence, int $id): TerminalSequence
    {
        $union = $sequence->child($id, 'select_derived_union');
        if ($union === null) {
            return $sequence;
        }
        $first = $union;
        while (($left = $sequence->child($first->id, 'select_derived_union')) !== null) {
            $first = $left;
        }
        $head = $sequence->child($first->id, 'select_derived');
        $range = $head === null ? null : $sequence->range($head->id);
        if ($head === null || $range === null) {
            return $sequence;
        }
        $alias = $sequence->child($id, 'opt_table_alias');
        $named = $alias !== null && $sequence->range($alias->id) !== null;
        $order = $sequence->child($first->id, 'opt_union_order_or_limit');
        $ordered = $order !== null && $sequence->range($order->id) !== null;
        if ($sequence->nameAt($range[0]) !== 'SELECT_SYM') {
            if ($first === $union && !$named && !$ordered) {
                return $sequence;
            }
            $rule = 'sql_yacc.yy:legacy-derived-query';
            $sequence = $sequence->replace($range[0], 0, [
                $sequence->insertedFor('SELECT_SYM', $head->id, $rule),
                $sequence->insertedFor('*', $head->id, $rule, 1),
                $sequence->insertedFor('FROM', $head->id, $rule, 2),
            ], $rule);
        }
        $range = $sequence->range($id);
        if ($alias === null || $named || $range === null) {
            return $sequence;
        }
        $rule = 'sql_yacc.yy:legacy-derived-alias';
        return $sequence->replace($range[1], 0, [
            $sequence->insertedFor('AS', $alias->id, $rule),
            $sequence->insertedFor('IDENT_QUOTED', $alias->id, $rule, 1),
        ], $rule);
    }
}
