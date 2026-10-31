<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Query\Modern;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\Query\Shared\Block;
use SqlSemantics\Platform\MySql\Lowering\Query\Shared\TailRule;
use SqlSemantics\Platform\MySql\Lowering\Query\Shared\Trailer;
use SqlSemantics\Platform\MySql\Statement\Query\Into\IntoPosition;
use SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperator;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetQuantifier;
use SqlSemantics\Statement\Query;

/**
 * Lowers the query statements and query expressions of the 8.0 and later grammars.
 *
 * Rule: MYSQL-QUERY-MODERN-001. Scope: select_stmt, select_stmt_with_into,
 * query_expression, query_expression_body, query_expression_parens,
 * query_expression_with_opt_locking_clauses, subquery, row_subquery,
 * table_subquery, union_option. The ORDER BY and LIMIT of a query
 * expression and the INTO and locking clauses of the statement belong to
 * the query block when the body is a single one (MYSQL-SELECT-001); else
 * they make a query expression and a query statement. Set operations are
 * left-deep in written order. The parentheses a subquery requires are
 * removed; further parentheses are parenthesized queries. Constructs:
 * Select (through the query block), SetOperation, ParenthesizedQuery,
 * QueryExpression, QueryStatement. Terminates: the left spine of a set
 * operation is walked in a loop; recursion follows strictly smaller
 * operands. Source: https://dev.mysql.com/doc/refman/8.4/en/select.html,
 * https://dev.mysql.com/doc/refman/8.4/en/set-operations.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\Query
 */
final class ExpressionRule
{
    /**
     * The set operation productions by their operator.
     */
    private const OPERATIONS = [
        'query_expression_body: query_expression_body UNION_SYM union_option query_expression_body' => SetOperator::Union,
        'query_expression_body: query_expression_body EXCEPT_SYM union_option query_expression_body' => SetOperator::Except,
        'query_expression_body: query_expression_body INTERSECT_SYM union_option query_expression_body' => SetOperator::Intersect,
    ];

    /**
     * The quantifiers of a set operation.
     */
    private const QUANTIFIERS = ['union_option:' => null, 'union_option: DISTINCT' => SetQuantifier::Distinct, 'union_option: ALL' => SetQuantifier::All];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a query statement: a node of select_stmt.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function statement(Node $statement): Query
    {
        $form = $this->lowering->form($statement);
        $tail = new TailRule($this->lowering);

        return match ($form->signature) {
            'select_stmt: query_expression' => $this->expression($form->node(0), new Trailer()),
            'select_stmt: query_expression locking_clause_list' => $this->expression($form->node(0), new Trailer([], null, null, $tail->locking($form->node(1)))),
            'select_stmt: select_stmt_with_into' => $this->into($form->node(0)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers a query statement with an INTO clause after the query.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function into(Node $statement): Query
    {
        $form = $this->lowering->form($statement);
        $tail = new TailRule($this->lowering);

        return match ($form->signature) {
            'select_stmt_with_into: ( select_stmt_with_into )' => new ParenthesizedQuery($this->into($form->node(1))),
            'select_stmt_with_into: query_expression into_clause' => $this->expression($form->node(0), new Trailer([], null, null, [], $tail->into($form->node(1)), IntoPosition::AfterQuery)),
            'select_stmt_with_into: query_expression into_clause locking_clause_list' => $this->expression($form->node(0), new Trailer([], null, null, $tail->locking($form->node(2)), $tail->into($form->node(1)), IntoPosition::AfterQuery)),
            'select_stmt_with_into: query_expression locking_clause_list into_clause' => $this->expression($form->node(0), new Trailer([], null, null, $tail->locking($form->node(1)), $tail->into($form->node(2)), IntoPosition::AfterLocking)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers a query expression with the clauses the statement writes after it.
     *
     * @throws ImplementationGap When a production has no rule
     * @throws \SqlSemantics\Construction\RejectedInput When a common table expression the query reads carries a modifier the server refuses there
     */
    public function expression(Node $expression, Trailer $later): Query
    {
        $retained = $later->empty() ? (new \SqlSemantics\Platform\MySql\Lowering\Query\Shared\StoredSelections($this->lowering))->body($expression) : null;
        if ($retained !== null) {
            return $retained instanceof Query ? $retained : new \SqlSemantics\Platform\MySql\Statement\Query\With\DeferredBody($retained);
        }
        $form = $this->lowering->form($expression);
        $offset = match ($form->signature) {
            'query_expression: query_expression_body opt_order_clause opt_limit_clause' => 0,
            'query_expression: with_clause query_expression_body opt_order_clause opt_limit_clause' => 1,
            default => throw ImplementationGap::production($form),
        };
        $with = $offset === 1 ? (new PrimaryRule($this->lowering))->with($form->node(0)) : null;
        $own = new Trailer((new \SqlSemantics\Platform\MySql\Lowering\Query\Shared\StoredClauses($this->lowering))->ordering($form->node($offset + 1)), (new TailRule($this->lowering))->limit($form->node($offset + 2)));
        $body = $this->body($form->node($offset));
        if ($body instanceof Block) {
            $select = $body->then($own->then($later))->select();
            $this->reached($with, [$select], $expression);

            return $with === null ? $select : new QueryExpression($with, $select);
        }
        $this->reached($with, [$body], $expression);
        $query = $with === null ? $own->wrap($body) : new QueryExpression($with, $body, $own->orderBy, $own->limit);

        return $later->wrap($query);
    }

    /**
     * Refuses a common table expression the query reads whose blocks carry a modifier of the first block of a statement or ALL with DISTINCT, as the server does once it has read the statement, before any table is opened (ER_CANT_USE_OPTION_HERE, ER_WRONG_USAGE); a definition nobody reads is left alone.
     *
     * @param list<\SqlSemantics\Statement\Node|list<\SqlSemantics\Statement\Node>|null> $parts The parts of the query other than the WITH clause
     * @throws \SqlSemantics\Construction\RejectedInput When a reached definition carries such a modifier
     */
    public function reached(?\SqlSemantics\Platform\MySql\Statement\Query\WithClause $with, array $parts, Node $expression): void
    {
        $problem = (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\ReachedTables())->problem($with, $parts);
        if ($problem !== null) {
            throw new \SqlSemantics\Construction\RejectedInput($problem, (new \SqlSemantics\Construction\SourceRanges())->span($expression)[1] ?? 0, $this->lowering->stored());
        }
    }

    /**
     * Lowers a query expression body; a single query block stays open for the clauses written after it.
     *
     * @throws \SqlSemantics\Construction\RejectedInput When a leading operand redirects its result
     *
     * @throws ImplementationGap When a production has no rule
     * @throws \SqlSemantics\Construction\RejectedInput When a later operand carries a modifier that belongs to the first block of the statement (ER_CANT_USE_OPTION_HERE), which the server refuses once it has read the statement; the refusal ends where the operand starts, so the conditions raised while parsing the input before it come first
     */
    public function body(Node $body): Block|Query
    {
        $selection = (new \SqlSemantics\Platform\MySql\Lowering\Query\Shared\StoredSelections($this->lowering))->block($body);
        if ($selection !== null) {
            return $selection instanceof Block ? $selection : new \SqlSemantics\Platform\MySql\Statement\Query\With\DeferredBody($selection);
        }
        $steps = [];
        $form = $this->lowering->form($body);
        while (isset(self::OPERATIONS[$form->signature])) {
            $steps[] = [self::OPERATIONS[$form->signature], $this->quantifier($form->node(2)), $form->node(3)];
            $form = $this->lowering->form($form->node(0));
        }
        $first = match ($form->signature) {
            'query_expression_body: query_primary' => $steps === [] ? (new PrimaryRule($this->lowering))->primary($form->node(0)) : $this->body($form->node),
            'query_expression_body: query_expression_parens' => $this->parenthesized($form->node(0)),
            default => throw ImplementationGap::production($form),
        };
        if ($steps === []) {
            return $first;
        }
        $query = $first instanceof Block ? $first->select() : $first;
        foreach (array_reverse($steps) as [$operator, $quantifier, $right]) {
            if ($query instanceof \SqlSemantics\Platform\MySql\Statement\Query\Select && $query->into !== null) {
                $origin = $this->lowering->origins->publish()->of($query->into);
                throw new \SqlSemantics\Construction\RejectedInput(new \SqlSemantics\Platform\MySql\Statement\Query\Problem\Operand\RefusedIntoSetOperand($query, $operator), $origin === null ? 0 : $origin->offset + $origin->length);
            }
            $operand = $this->body($right);
            $operand = $operand instanceof Block ? $operand->select() : $operand;
            $problem = (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\DerivedOptions())->problem($operand, $this->lowering->profile->grammar);
            if ($problem !== null) {
                throw new \SqlSemantics\Construction\RejectedInput($problem, (new \SqlSemantics\Construction\SourceRanges())->span($right)[0] ?? 0, false);
            }
            $query = new SetOperation($query, $operator, $quantifier, $operand);
        }

        return $query;
    }

    /**
     * Lowers the quantifier of a set operation.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function quantifier(Node $option): ?SetQuantifier
    {
        $form = $this->lowering->form($option);
        if (!array_key_exists($form->signature, self::QUANTIFIERS)) {
            throw ImplementationGap::production($form);
        }

        return self::QUANTIFIERS[$form->signature];
    }

    /**
     * Lowers a parenthesized query expression keeping its parentheses.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function parenthesized(Node $parens): ParenthesizedQuery
    {
        return new ParenthesizedQuery($this->inner($parens));
    }

    /**
     * Lowers the query inside the outermost parentheses of a parenthesized query expression.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function inner(Node $parens): Query
    {
        $form = $this->lowering->form($parens);

        return match ($form->signature) {
            'query_expression_parens: ( query_expression_parens )' => $this->parenthesized($form->node(1)),
            'query_expression_parens: ( query_expression_with_opt_locking_clauses )' => $this->locked($form->node(1)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers a query expression with its optional locking clauses.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function locked(Node $query): Query
    {
        $form = $this->lowering->form($query);

        return match ($form->signature) {
            'query_expression_with_opt_locking_clauses: query_expression' => $this->expression($form->node(0), new Trailer()),
            'query_expression_with_opt_locking_clauses: query_expression locking_clause_list' => $this->expression($form->node(0), new Trailer([], null, null, (new TailRule($this->lowering))->locking($form->node(1)))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers a subquery without the parentheses its syntax requires: a node of subquery, row_subquery or table_subquery.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function subquery(Node $subquery): Query
    {
        $form = $this->lowering->form($subquery);

        return match ($form->signature) {
            'row_subquery: subquery', 'table_subquery: subquery' => $this->subquery($form->node(0)),
            'subquery: query_expression_parens' => $this->inner($form->node(0)),
            default => throw ImplementationGap::production($form),
        };
    }
}
