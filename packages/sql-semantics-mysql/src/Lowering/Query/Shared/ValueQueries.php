<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Query\Shared;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\Query\Modern\PrimaryRule;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\ValuesClauses;
use SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\QueryStatement;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredOrderItem;
use SqlSemantics\Statement\Query;

/**
 * Retains unused query clauses around a standalone VALUES write.
 *
 * Parsing still checks all syntax. Contextualization visits only the row
 * expressions; a SELECT or set-operation source follows ordinary query rules.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ValueQueries
{
    private const BODY = [
        'query_expression_with_opt_locking_clauses: query_expression' => 0,
        'query_expression_with_opt_locking_clauses: query_expression locking_clause_list' => 0,
        'query_expression: query_expression_body opt_order_clause opt_limit_clause' => 0,
        'query_expression: with_clause query_expression_body opt_order_clause opt_limit_clause' => 1,
        'query_expression_body: query_primary' => 0,
        'query_expression_body: query_expression_parens' => 0,
        'query_expression_parens: ( query_expression_with_opt_locking_clauses )' => 1,
        'query_expression_parens: ( query_expression_parens )' => 1,
        'query_primary: table_value_constructor' => 0,
    ];

    /**
     * @param Lowering $lowering The active row-expression interpretation
     */
    public function __construct(public readonly Lowering $lowering)
    {
    }

    /**
     * Answers only a constructor reached through wrappers, never through a set operation.
     */
    public function values(Node $node): ?Node
    {
        while ($node->name !== 'table_value_constructor') {
            $form = $this->lowering->form($node);
            $position = self::BODY[$form->signature] ?? null;
            if ($position === null) {
                return null;
            }
            $node = $form->node($position);
        }
        return $node;
    }

    /**
     * Retains every wrapper and lowers only its clauses without native-call contextualization.
     */
    public function query(Node $node): Query
    {
        $form = $this->lowering->form($node);
        if ($node->name === 'table_value_constructor') {
            return (new PrimaryRule($this->lowering))->values($node);
        }
        $position = self::BODY[$form->signature] ?? throw ImplementationGap::production($form);
        $body = $this->query($form->node($position));
        $inactive = $this->lowering->unprepared();
        if ($node->name === 'query_expression') {
            $with = $position === 1 ? (new PrimaryRule($inactive))->with($form->node(0)) : null;
            $order = (new StoredClauses($inactive))->ordering($form->node($position + 1));
            $limit = (new TailRule($inactive))->limit($form->node($position + 2));
            (new \SqlSemantics\Platform\MySql\Lowering\Query\Modern\ExpressionRule($this->lowering))->reached($with, [$body], $node);
            if ($order !== [] && array_filter($order, static fn ($item): bool => $item instanceof DeferredOrderItem) !== []) {
                return new ValuesClauses($with, $body, $order, $limit);
            }
            $ordinary = array_values(array_filter($order, static fn ($item): bool => $item instanceof \SqlSemantics\Platform\MySql\Statement\Query\OrderItem));
            return $with === null && $ordinary === [] && $limit === null ? $body : new QueryExpression($with, $body, $ordinary, $limit);
        }
        if ($node->name === 'query_expression_parens') {
            return new ParenthesizedQuery($body);
        }
        return $node->name === 'query_expression_with_opt_locking_clauses' && count($node->children) === 2
            ? new QueryStatement($body, (new TailRule($inactive))->locking($form->node(1))) : $body;
    }

    /**
     * @return array<int, true> Parser occurrences in unused clauses, without skipping lexical syntax checks
     */
    public function inactive(Node $tree): array
    {
        $inactive = [];
        foreach ($tree->find('insert_query_expression') as $insert) {
            $node = $insert->children[count($insert->children) - 1];
            if (!$node instanceof Node || $this->values($node) === null) {
                continue;
            }
            while ($node->name !== 'table_value_constructor') {
                $form = $this->lowering->form($node);
                $body = $form->node(self::BODY[$form->signature]);
                foreach ($node->children as $child) {
                    if ($child instanceof Node && $child !== $body) {
                        $pending = [$child];
                        while ($pending !== []) {
                            $part = array_pop($pending);
                            $inactive[spl_object_id($part)] = true;
                            array_push($pending, ...array_values(array_filter($part->children, static fn ($value): bool => $value instanceof Node)));
                        }
                    }
                }
                $node = $body;
            }
        }
        return $inactive;
    }
}
