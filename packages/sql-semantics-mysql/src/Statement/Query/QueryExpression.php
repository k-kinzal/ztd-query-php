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
 *     [count($query->query->orderBy), $query->query->limit !== null] // => [1, true]
 * @example Refusing a wrapper that adds nothing
 *     new \SqlSemantics\Platform\MySql\Statement\Query\QueryExpression(null, new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'))])) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Refusing HIGH_PRIORITY in a common table expression the query reads
 *     $table = new \SqlSemantics\Platform\MySql\Statement\Query\With\CommonTableExpression(new \SqlSemantics\Statement\Identifier\Name('c'), [], new \SqlSemantics\Platform\MySql\Statement\Query\Select([\SqlSemantics\Platform\MySql\Statement\Query\SelectOption::HighPriority], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'))]));
 *     $body = new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\Star()], new \SqlSemantics\Platform\MySql\Statement\Relation\TableReference(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('c'))));
 *     new \SqlSemantics\Platform\MySql\Statement\Query\QueryExpression(new \SqlSemantics\Platform\MySql\Statement\Query\With\With(false, [$table]), $body) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class QueryExpression implements Query
{
    use \SqlSemantics\Statement\Semantics\QuerySemantics;
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @var list<OrderItem|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredOrderItem> The ORDER BY items in written order
     */
    public readonly array $orderBy;

    /**
     * @param WithClause|null $with The WITH clause
     * @param Query $body The query the clauses apply to
     * @param list<OrderItem|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredOrderItem> $orderBy The ORDER BY items
     * @param Limit|null $limit The LIMIT clause
     */
    public function __construct(public readonly ?WithClause $with, public readonly Query $body, array $orderBy = [], public readonly ?Limit $limit = null, ?QueryFact $queryInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        if ($queryInformation !== null) {
            $this->initializeQueryInformation($queryInformation);
        }
        Check::input(!$body instanceof With\DeferredBody || ($orderBy === [] && $limit === null), 'A deferred block retains its own ordering and limit.');
        $this->orderBy = (new SortScopes())->items($orderBy);
        Check::input($with !== null || $orderBy !== [] || $limit !== null, 'A query expression holds a WITH clause, an ordering or a limit.');
        Check::input(!$body instanceof Select || ($orderBy === [] && $limit === null) || ($orderBy !== [] && ($body->orderBy !== [] || $body->limit !== null) && $body->late === null), 'The ordering and the limit of a single query block belong to the block unless an ORDER BY follows a block that orders or limits its rows.');
        Check::input(!$body instanceof self && !$body instanceof QueryStatement && !$body instanceof OrderedSetOperation, 'A query expression, a query statement or an ordered set operation as a body is written in parentheses.');
        Check::input(!$body instanceof Select || $orderBy === [] || $body->orderBy === [] || ($body->from !== null && !$body->from instanceof \SqlSemantics\Platform\MySql\Statement\Relation\Dual), 'A second ordering after an ordered block requires an ordinary FROM relation.');
        Check::input((new \SqlSemantics\Platform\MySql\Rules\Query\Structure\ReachedTables())->problem($with, [$body]) === null, 'A common table expression the statement reads carries no HIGH_PRIORITY, SQL_BUFFER_RESULT, SQL_CALC_FOUND_ROWS or SQL_CACHE, and no block with ALL and DISTINCT.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
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
