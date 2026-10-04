<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Query\Limit;
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Shape\Field;

/**
 * Derives the facts of a query expression.
 *
 * Rule: MYSQL-QUERY-EXPRESSION-FACTS-001. The WITH clause binds its common
 * tables (MYSQL-WITH-001) for the body, the ordering and the limit. The
 * ORDER BY items see the output columns of the body by name and position
 * (MYSQL-SORT-SCOPE-001) and, beyond them, the enclosing queries; they see no
 * table of the body. The output is that of the body. Terminates: the body is
 * a strict part. Source: https://dev.mysql.com/doc/refman/8.4/en/union.html
 * ("ORDER BY ... cannot use column references that include a table name"),
 * https://dev.mysql.com/doc/refman/8.4/en/with.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ExpressionFacts
{
    /**
     * Derives every part and answers the output of the body.
     */
    public function derive(QueryExpression $expression, Derivation $derivation, Environment $outer): QueryFact
    {
        $environment = $expression->with === null ? $outer : $expression->with->bind($derivation, $outer);
        $fact = $derivation->query($expression->body, $environment);
        $this->ordering($fact, $expression->orderBy, $expression->limit, $derivation, $environment);

        return $fact;
    }

    /**
     * Derives an ORDER BY and a LIMIT that apply to the rows of a query in an environment.
     *
     * @param list<OrderItem> $orderBy
     */
    public function ordering(QueryFact $fact, array $orderBy, ?Limit $limit, Derivation $derivation, Environment $environment): void
    {
        $named = [];
        foreach ($fact->projection as $item) {
            if ($item instanceof Field && $item->name !== null) {
                $named[] = $item;
            }
        }
        $results = new Environment($derivation->context, $environment, [], [], $named);
        (new SortScopes())->derive($orderBy, $derivation, $results, $fact->projection, true);
        (new TailFacts())->limit($limit, $derivation, $environment);
    }
}
