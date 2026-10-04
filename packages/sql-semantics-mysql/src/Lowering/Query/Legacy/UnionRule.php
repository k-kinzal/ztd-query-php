<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Query\Legacy;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\Query\Modern\ExpressionRule;
use SqlSemantics\Platform\MySql\Lowering\Query\Shared\Block;
use SqlSemantics\Platform\MySql\Lowering\Query\Shared\ClauseRule;
use SqlSemantics\Platform\MySql\Lowering\Query\Shared\TailRule;
use SqlSemantics\Platform\MySql\Lowering\Query\Shared\Trailer;
use SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Statement\Query;

/**
 * Lowers the statements, views and unions of the 5.x grammars.
 *
 * Rule: MYSQL-QUERY-LEGACY-001. Scope: select, select_init, select_init2,
 * select_paren, union_clause, opt_union_clause, union_list, union_opt,
 * opt_union_order_or_limit, union_order_or_limit, order_or_limit,
 * view_select_aux, create_view_select_paren, create_view_select, and the
 * union that follows create_select. The grammar nests a union to the
 * right; it is walked in a loop and combined by MYSQL-UNION-LEGACY-001.
 * Parenthesized selects are parenthesized queries. Constructs: the query
 * block, SetOperation, ParenthesizedQuery, QueryExpression, QueryStatement.
 * Terminates: the union is walked in a loop; recursion follows nested
 * parentheses, which are strict subtrees. Source:
 * https://dev.mysql.com/doc/refman/5.7/en/union.html,
 * https://dev.mysql.com/doc/refman/5.7/en/create-view.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\Query
 */
final class UnionRule
{
    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a 5.x query statement: a node of select.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function statement(Node $select): Query
    {
        $form = $this->lowering->form($select);
        if ($form->signature !== 'select: select_init') {
            throw ImplementationGap::production($form);
        }
        [$first, $union] = $this->operand($form->node(0));

        return $this->chain($first, $union);
    }

    /**
     * Lowers one operand of a statement-level union and answers it with the node of the union that follows it.
     *
     * @return array{Block|Query, Node}
     * @throws ImplementationGap When a production has no rule
     */
    public function operand(Node $init): array
    {
        $form = $this->lowering->form($init);
        $blocks = new BlockRule($this->lowering);
        switch ($form->signature) {
            case 'select_init: SELECT_SYM select_init2':
                $second = $this->lowering->form($form->node(1));
                if ($second->signature !== 'select_init2: select_part2 union_clause') {
                    throw ImplementationGap::production($second);
                }

                return [$blocks->part($second->node(0)), $second->node(1)];
            case 'select_init: ( select_paren ) union_opt':
                return [new ParenthesizedQuery($this->paren($form->node(1))), $form->node(3)];
            case 'select_init: SELECT_SYM select_part2 opt_union_clause':
                return [$blocks->part($form->node(1)), $form->node(2)];
            default:
                throw ImplementationGap::production($form);
        }
    }

    /**
     * Lowers the query inside the parentheses of a statement-level select.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function paren(Node $paren): Query
    {
        $form = $this->lowering->form($paren);

        return match ($form->signature) {
            'select_paren: SELECT_SYM select_part2' => (new BlockRule($this->lowering))->part($form->node(1))->select(),
            'select_paren: ( select_paren )', 'create_view_select_paren: ( create_view_select_paren )' => new ParenthesizedQuery($this->paren($form->node(1))),
            'create_view_select_paren: create_view_select' => $this->view($form->node(0))->select(),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Combines a first operand with the union written after it.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function chain(Block|Query $first, ?Node $union): Query
    {
        $operands = [[$first, new Trailer()]];
        $quantifiers = [];
        $trailer = new Trailer();
        $expressions = new ExpressionRule($this->lowering);
        while ($union !== null) {
            $form = $this->lowering->form($union);
            switch ($form->signature) {
                case 'union_clause:':
                case 'opt_union_clause:':
                case 'union_opt:':
                    $union = null;
                    break;
                case 'union_clause: union_list':
                case 'opt_union_clause: union_list':
                case 'union_opt: union_list':
                    $list = $this->lowering->form($form->node(0));
                    if ($list->signature !== 'union_list: UNION_SYM union_option select_init') {
                        throw ImplementationGap::production($list);
                    }
                    $quantifiers[] = $expressions->quantifier($list->node(1));
                    [$operand, $union] = $this->operand($list->node(2));
                    $operands[] = [$operand, new Trailer()];
                    break;
                case 'union_opt: union_order_or_limit':
                    $trailer = $this->ordering($form->node(0));
                    $union = null;
                    break;
                default:
                    throw ImplementationGap::production($form);
            }
        }

        return (new Chain($operands, $quantifiers, $this->lowering->profile->grammar, $trailer))->query();
    }

    /**
     * Lowers the ORDER BY and LIMIT written after a parenthesized operand; an absent clause is empty.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function ordering(Node $clause): Trailer
    {
        $form = $this->lowering->form($clause);
        $tail = new TailRule($this->lowering);

        return match ($form->signature) {
            'opt_union_order_or_limit:' => new Trailer(),
            'opt_union_order_or_limit: union_order_or_limit', 'union_order_or_limit: order_or_limit' => $this->ordering($form->node(0)),
            'order_or_limit: order_clause opt_limit_clause_init', 'order_or_limit: order_clause opt_limit_clause' => new Trailer((new ClauseRule($this->lowering))->ordering($form->node(0)), $tail->limit($form->node(1))),
            'order_or_limit: limit_clause' => new Trailer([], $tail->limit($form->node(0))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the query of a 5.x view definition: a node of view_select_aux.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function viewQuery(Node $aux): Query
    {
        $form = $this->lowering->form($aux);

        return match ($form->signature) {
            'view_select_aux: create_view_select union_clause', 'view_select_aux: create_view_select opt_union_clause' => $this->chain($this->view($form->node(0)), $form->node(1)),
            'view_select_aux: ( create_view_select_paren ) union_opt' => $this->chain(new ParenthesizedQuery($this->paren($form->node(1))), $form->node(3)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the query block of a 5.x view definition.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function view(Node $select): Block
    {
        $form = $this->lowering->form($select);
        if ($form->signature !== 'create_view_select: SELECT_SYM select_part2') {
            throw ImplementationGap::production($form);
        }

        return (new BlockRule($this->lowering))->part($form->node(1));
    }
}
