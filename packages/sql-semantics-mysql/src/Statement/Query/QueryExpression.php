<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Query\ExpressionFacts;
use SqlSemantics\Platform\MySql\Rules\Query\QueryTails;
use SqlSemantics\Platform\MySql\Rules\Query\SortScopes;
use SqlSemantics\Platform\MySql\Statement\Query\Set\OrderedSetOperation;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A query expression: a WITH clause, or an ORDER BY and LIMIT that apply to a whole set operation or parenthesized query.
 *
 * It exists only when it holds a WITH clause, an ordering or a limit. Over a
 * single query block the ordering and the limit belong to the block itself,
 * so a body that is a selection takes no ordering or limit here, unless an
 * ORDER BY follows a block that orders or limits its rows itself: a 5.6
 * subquery accepts that, and the server orders the rows of the block in a
 * query level of their own (the `order_clause` action adds a fake query
 * block). The body is
 * not itself a query expression or a query statement, which are merged into
 * or wrapped around this one.
 *
 * Rule: MYSQL-QUERY-EXPRESSION-001. The facts are derived by
 * MYSQL-QUERY-EXPRESSION-FACTS-001. Source:
 * https://dev.mysql.com/doc/refman/8.4/en/union.html,
 * https://dev.mysql.com/doc/refman/8.4/en/with.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading the ordering of a union
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT a FROM t UNION SELECT b FROM u ORDER BY 1 LIMIT 2');
 *     [count($query->statement->orderBy), $query->statement->limit !== null] // => [1, true]
 * @example Refusing a wrapper that adds nothing
 *     new \SqlSemantics\Platform\MySql\Statement\Query\QueryExpression(null, new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'))])) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class QueryExpression implements Statement, Query
{
    use Snapshot;

    /**
     * @var list<OrderItem> The ORDER BY items in written order
     */
    public readonly array $orderBy;

    /**
     * @param WithClause|null $with The WITH clause
     * @param Query $body The query the clauses apply to
     * @param list<OrderItem> $orderBy The ORDER BY items
     * @param Limit|null $limit The LIMIT clause
     */
    public function __construct(public readonly ?WithClause $with, public readonly Query $body, array $orderBy = [], public readonly ?Limit $limit = null)
    {
        $this->orderBy = Check::listOf($orderBy, OrderItem::class, 'ORDER BY holds ordering items.');
        (new SortScopes())->check($this->orderBy);
        Check::input($with !== null || $orderBy !== [] || $limit !== null, 'A query expression holds a WITH clause, an ordering or a limit.');
        Check::input(!$body instanceof Select || ($orderBy === [] && $limit === null) || ($orderBy !== [] && ($body->orderBy !== [] || $body->limit !== null) && $body->late === null), 'The ordering and the limit of a single query block belong to the block unless an ORDER BY follows a block that orders or limits its rows.');
        Check::input(!$body instanceof self && !$body instanceof QueryStatement && !$body instanceof OrderedSetOperation, 'A query expression, a query statement or an ordered set operation as a body is written in parentheses.');
    }

    /**
     * Derives the query expression as a statement root and records its rows as the output.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $derivation->output($derivation->query($this, $derivation->environment()));
    }

    /**
     * Binds the common tables and derives the body, the ordering and the limit.
     */
    public function deriveQuery(Derivation $derivation, Environment $outer): QueryFact
    {
        return (new ExpressionFacts())->derive($this, $derivation, $outer);
    }

    /**
     * Writes the clauses in grammar order.
     */
    public function render(Output $out): void
    {
        $out->node($this->with)->node($this->body);
        (new QueryTails())->expression($this, $out);
    }
}
