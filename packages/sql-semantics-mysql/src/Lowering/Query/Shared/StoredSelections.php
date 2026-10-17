<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Query\Shared;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Lowering\Lists;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Star;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredProjection;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection;
use SqlSemantics\Statement\Scalar;

/**
 * Reads stored item/input/filter selections without changing the scalar query domain.
 *
 * The complete clause frontier is inspected before any operand is lowered.
 * Other query forms remain with their ordinary query rules; no clause is dropped
 * or replaced when this instruction form does not represent the input.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class StoredSelections
{
    /**
     * @param Lowering $lowering The language interpretation of the input
     */
    public function __construct(public readonly Lowering $lowering)
    {
    }

    /**
     * Retains a complete stored selection whose projection or filter needs deferred preparation.
     */
    public function read(Node $node): DeferredSelection|\SqlSemantics\Platform\MySql\Statement\QueryRoot|null
    {
        $parts = $this->frontier($node);
        if ($parts === null) {
            return null;
        }
        [$list, $from, $where, $dual] = $parts;
        $items = $this->items($list);
        $input = $from === null ? ($dual ? new \SqlSemantics\Platform\MySql\Statement\Relation\Dual() : null) : (new FromRule($this->lowering))->from($from);
        $predicate = $where === null || $where->tokens() === [] ? null : $this->predicate($where);
        $deferred = array_filter($items, static fn ($item): bool => $item instanceof DeferredProjection) !== [] || ($predicate !== null && !$predicate instanceof Scalar);
        if ($deferred) {
            return new DeferredSelection($items, $input, $predicate);
        }
        $ordinary = [];
        foreach ($items as $item) {
            \SqlSemantics\Diagnostic\Check::invariant(!$item instanceof DeferredProjection, 'An ordinary selection has no deferred projection.');
            $ordinary[] = $item;
        }
        $select = $this->lowering->origins->record(new \SqlSemantics\Platform\MySql\Statement\Query\Select([], $ordinary, $input, $predicate), $node);
        return $this->lowering->origins->record(new \SqlSemantics\Platform\MySql\Statement\QueryRoot($select), $node);
    }

    /**
     * Locates every clause before lowering operands; unsupported frontiers remain untouched.
     *
     * @return array{Node, Node|null, Node|null, bool}|null
     */
    public function frontier(Node $node): ?array
    {
        $wrappers = ['statement', 'simple_statement', 'select', 'select_init', 'select_init2', 'select_stmt', 'select_part2', 'select_options_and_item_list', 'select_into', 'select_from', 'query_expression', 'query_expression_body', 'query_primary', 'query_specification'];
        $list = null;
        $from = null;
        $where = null;
        $dual = false;
        $pending = [$node];
        while ($pending !== []) {
            $part = array_pop($pending);
            if ($part instanceof Token) {
                if ($part->name === 'DUAL_SYM') {
                    $dual = true;
                } elseif (!in_array($part->name, ['SELECT_SYM', 'FROM'], true) || $this->lowering->hints($part) !== []) {
                    return null;
                }
                continue;
            }
            if ($part->name === 'select_item_list') {
                if ($list !== null) {
                    return null;
                }
                $list = $part;
            } elseif (in_array($part->name, ['from_clause', 'opt_from_clause', 'join_table_list'], true)) {
                $from = $part;
            } elseif (in_array($part->name, ['where_clause', 'opt_where_clause'], true)) {
                $where = $part;
            } elseif (in_array($part->name, $wrappers, true)) {
                array_push($pending, ...array_reverse($part->children));
            } elseif ($part->tokens() !== []) {
                return null;
            }
        }
        return $list === null ? null : [$list, $from, $where, $dual];
    }

    /**
     * Reads a SELECT list while preserving the scalar or deferred role of each projection.
     *
     * @return list<SelectExpression|DeferredProjection|Star|\SqlSemantics\Platform\MySql\Statement\Name\TableWildcard>
     */
    public function items(Node $list): array
    {
        $items = [];
        $rule = new ItemRule($this->lowering);
        foreach ((new Lists())->elements($list) as $item) {
            if ($item instanceof Token) {
                if ($item->name === '*') {
                    $items[] = new Star();
                }
                continue;
            }
            $form = $this->lowering->form($item);
            $position = match ($form->signature) {
                'select_item: expr select_alias' => [0, 1],
                'select_item: remember_name expr remember_end select_alias' => [1, 3],
                default => null,
            };
            if ($position === null) {
                $items[] = $rule->item($item);
                continue;
            }
            $expression = $this->lowering->expressions->operand($form->node($position[0]));
            $alias = $form->node($position[1]);
            $items[] = $expression instanceof Scalar ? $rule->resolved($expression, $form->node($position[0]), $alias) : new DeferredProjection($expression, $rule->alias($alias), $rule->mark($alias));
        }
        return $items;
    }

    /**
     * Reads the operand of a present WHERE clause without forcing a scalar role.
     */
    public function predicate(Node $where): Scalar|\SqlSemantics\Platform\MySql\Statement\Expression\RowOperand|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand
    {
        $form = $this->lowering->form($where);
        return match ($form->signature) {
            'opt_where_clause: where_clause' => $this->predicate($form->node(0)),
            'where_clause: WHERE expr', 'opt_where_clause: WHERE expr' => $this->lowering->expressions->operand($form->node(1)),
            default => throw \SqlSemantics\Diagnostic\ImplementationGap::production($form),
        };
    }
}
