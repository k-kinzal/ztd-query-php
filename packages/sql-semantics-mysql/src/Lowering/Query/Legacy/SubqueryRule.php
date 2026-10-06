<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Query\Legacy;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\Query\Modern\ExpressionRule;
use SqlSemantics\Platform\MySql\Lowering\Query\Shared\Block;
use SqlSemantics\Platform\MySql\Lowering\Query\Shared\Trailer;
use SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Statement\Query;

/**
 * Lowers the subqueries of the 5.x grammars.
 *
 * Rule: MYSQL-SUBQUERY-LEGACY-001. Scope: subselect, query_expression_body
 * and query_specification (5.x), select_paren_derived. The query body is a
 * left-recursive union walked in a loop and combined by
 * MYSQL-UNION-LEGACY-001; an ORDER BY or LIMIT written after a
 * parenthesized operand belongs to it. Constructs: the query block,
 * SetOperation, ParenthesizedQuery, QueryExpression. Terminates: the union
 * is walked in a loop; recursion follows nested parentheses. Source:
 * https://dev.mysql.com/doc/refman/5.7/en/subqueries.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\Query
 */
final class SubqueryRule
{
    /**
     * The union productions of a 5.x query body: whether they carry an ORDER BY or LIMIT after the operand.
     */
    private const UNIONS = [
        'query_expression_body: query_expression_body UNION_SYM union_option query_specification opt_union_order_or_limit' => true,
        'query_expression_body: query_expression_body UNION_SYM union_option query_specification' => false,
    ];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a 5.x subquery: a node of subselect.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function subselect(Node $subselect): Query
    {
        $form = $this->lowering->form($subselect);
        if ($form->signature === 'subselect: subselect_start query_expression_body subselect_end') {
            $this->lowering->options->skip($form->node(0));
            $this->lowering->options->skip($form->node(2));

            return $this->body($form->node(1));
        }
        if ($form->signature !== 'subselect: query_expression_body') {
            throw ImplementationGap::production($form);
        }

        return $this->body($form->node(0));
    }

    /**
     * Lowers a 5.x query body, walking the union to its left in a loop.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function body(Node $body): Query
    {
        $steps = [];
        $form = $this->lowering->form($body);
        $expressions = new ExpressionRule($this->lowering);
        while (isset(self::UNIONS[$form->signature])) {
            $steps[] = [$expressions->quantifier($form->node(2)), $this->operand($form->node(3), self::UNIONS[$form->signature] ? $form->node(4) : null)];
            $form = $this->lowering->form($form->node(0));
        }
        $first = match ($form->signature) {
            'query_expression_body: query_specification opt_union_order_or_limit' => $this->operand($form->node(0), $form->node(1)),
            'query_expression_body: query_specification' => $this->operand($form->node(0), null),
            default => throw ImplementationGap::production($form),
        };
        $operands = [$first];
        $quantifiers = [];
        foreach (array_reverse($steps) as [$quantifier, $operand]) {
            $quantifiers[] = $quantifier;
            $operands[] = $operand;
        }

        return (new Chain($operands, $quantifiers, $this->lowering->profile->grammar))->query();
    }

    /**
     * Lowers one operand of a 5.x query body with the ORDER BY or LIMIT written after it.
     *
     * @return array{Block|Query, Trailer}
     * @throws ImplementationGap When a production has no rule
     */
    public function operand(Node $specification, ?Node $ordering): array
    {
        $form = $this->lowering->form($specification);
        $trailer = $ordering === null ? new Trailer() : (new UnionRule($this->lowering))->ordering($ordering);
        $blocks = new BlockRule($this->lowering);

        return match ($form->signature) {
            'query_specification: SELECT_SYM select_init2_derived' => [$blocks->derived($form->node(1)), $trailer],
            'query_specification: SELECT_SYM select_part2_derived table_expression' => [$blocks->derived($form->node(1), $form->node(2)), $trailer],
            'query_specification: ( select_paren_derived )' => [$this->paren($form->node(1)), $trailer],
            'query_specification: ( select_paren_derived ) opt_union_order_or_limit' => [$this->paren($form->node(1)), (new UnionRule($this->lowering))->ordering($form->node(3))->then($trailer)],
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers a parenthesized 5.x subquery operand, keeping its parentheses.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function paren(Node $paren): ParenthesizedQuery
    {
        $form = $this->lowering->form($paren);
        $blocks = new BlockRule($this->lowering);

        return new ParenthesizedQuery(match ($form->signature) {
            'select_paren_derived: SELECT_SYM select_part2_derived' => $blocks->derived($form->node(1))->select(),
            'select_paren_derived: SELECT_SYM select_part2_derived table_expression' => $blocks->derived($form->node(1), $form->node(2))->select(),
            'select_paren_derived: ( select_paren_derived )' => $this->paren($form->node(1)),
            default => throw ImplementationGap::production($form),
        });
    }
}
