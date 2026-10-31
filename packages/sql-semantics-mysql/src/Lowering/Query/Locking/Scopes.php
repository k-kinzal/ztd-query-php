<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Query\Locking;

use SqlParser\Parser\Node;
use SqlSemantics\Lowering\Lists;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\Query\Modern\PrimaryRule;
use SqlSemantics\Platform\MySql\Lowering\Query\Shared\FromRule;
use SqlSemantics\Platform\MySql\Lowering\Query\Shared\ItemRule;
use SqlSemantics\Platform\MySql\Lowering\Query\Shared\TailRule;
use SqlSemantics\Platform\MySql\Rules\Query\Tail\LockedTables;
use SqlSemantics\Platform\MySql\Statement\Query\ExplicitTable;
use SqlSemantics\Platform\MySql\Statement\Query\Locking\LockingClause;
use SqlSemantics\Platform\MySql\Statement\Query\SelectOption;
use SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable;
use SqlSemantics\Platform\MySql\Statement\Relation\JsonTable;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Reads a query's locking targets without requiring its projection to be scalar.
 *
 * Query wrappers select the last set operand; nested locks apply before outer
 * locks. Only actual input occurrences, names, options and lock clauses escape
 * this transient reader. It creates no substitute query or expression.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Scopes
{
    /**
     * Reads names introduced by a WITH clause without lowering their definitions.
     *
     * @return list<Name>
     */
    public function common(Node $node, Lowering $lowering): array
    {
        if ($node->name !== 'query_expression' || $node->children[0]->name !== 'with_clause') {
            return [];
        }
        $with = $lowering->form($lowering->form($node)->node(0));
        $list = $with->node(count($with->node->children) - 1);
        return array_map(static fn (Node $table): Name => $lowering->names->identifier($lowering->form($table)->node(0)), (new Lists())->items($list));
    }

    /**
     * Answers the last block's relations and the complete locking chain.
     *
     * @throws \SqlSemantics\Construction\RejectedInput When an input relation cannot be lowered
     *
     * @param list<Name> $common Names visible outside this query
     * @return array{list<TableReference|DerivedTable|JsonTable|ExplicitTable>, list<LockingClause>, list<Name>, list<SelectOption>}
     */
    public function read(Node $node, Lowering $lowering, array $common): array
    {
        $options = $this->leading($node, $lowering);
        $clauses = [];
        while (true) {
            $form = $lowering->form($node);
            $common = [...$common, ...$this->common($node, $lowering)];
            foreach ($node->children as $child) {
                if ($child instanceof Node && $child->name === 'locking_clause_list') {
                    $clauses = [...(new TailRule($lowering))->locking($child), ...$clauses];
                }
            }
            if ($node->name === 'explicit_table') {
                return [[(new PrimaryRule($lowering))->explicit($node)], $clauses, $common, $options];
            }
            if ($node->name === 'query_specification') {
                $from = array_values(array_filter($node->children, static fn ($child): bool => $child instanceof Node && $child->name === 'opt_from_clause'));
                $input = $from === [] ? null : (new FromRule($lowering))->from($from[0]);
                return [$input === null ? [] : (new LockedTables())->terms($input), $clauses, $common, $options];
            }
            if ($node->name === 'table_value_constructor') {
                return [[], $clauses, $common, $options];
            }
            $index = match ($node->name) {
                'query_expression' => $node->children[0]->name === 'with_clause' ? 1 : 0,
                'query_expression_parens' => 1,
                'query_expression_body' => count($node->children) === 4 ? 3 : 0,
                default => 0,
            };
            $node = $form->node($index);
        }
    }

    /**
     * Reads the leading block's SELECT modifiers, including HIGH_PRIORITY.
     *
     * @return list<SelectOption>
     */
    public function leading(Node $node, Lowering $lowering): array
    {
        while (!in_array($node->name, ['query_specification', 'explicit_table', 'table_value_constructor'], true)) {
            $index = $node->name === 'query_expression_parens' || ($node->name === 'query_expression' && $node->children[0]->name === 'with_clause') ? 1 : 0;
            $node = $lowering->form($node)->node($index);
        }
        return $node->name === 'query_specification' ? (new ItemRule($lowering))->options($lowering->form($node)->node(1)) : [];
    }
}
