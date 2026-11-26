<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Resolution\Environment;
use SqlSemantics\Platform\MySql\Model\Statement\Fact\QueryFact;
use SqlSemantics\Platform\MySql\Model\Statement\Query;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Model\Statement\Statement;
use SqlSemantics\Platform\MySql\Rules\Query\TailFacts;
use SqlSemantics\Platform\MySql\Statement\Query\Into\IntoDestination;
use SqlSemantics\Platform\MySql\Statement\Query\Into\IntoPosition;
use SqlSemantics\Platform\MySql\Statement\Query\Locking\LockingClause;
use SqlSemantics\Platform\MySql\Statement\Query\Set\OrderedSetOperation;

/**
 * INTO and locking clauses written after a set operation, a parenthesized query or another query that is not a single query block.
 *
 * After a single query block, also one with a WITH clause, the clauses
 * belong to the block, so such a query is refused here. INTO after the query
 * is written before the locking clauses or after them.
 *
 * Rule: MYSQL-QUERY-STATEMENT-001. The rows are those of the query; the
 * INTO targets and the locking clauses are derived by MYSQL-TAIL-FACTS-001.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/select.html,
 * https://dev.mysql.com/doc/refman/8.4/en/select-into.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 * @example Refusing a query statement without clauses
 *     $one = new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'))]);
 *     new \SqlSemantics\Platform\MySql\Statement\Query\QueryStatement(new \SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery($one)) // throws \SqlSemantics\Platform\MySql\Model\Diagnostic\InvalidConstruction
 */
final class QueryStatement implements Statement, Query
{
    use Snapshot;

    /**
     * @var list<LockingClause> The locking clauses in written order
     */
    public readonly array $locking;

    /**
     * @param Query $query The query the clauses follow
     * @param list<LockingClause> $locking The locking clauses
     * @param IntoDestination|null $into The INTO destination
     * @param IntoPosition|null $intoPosition Where INTO is written: after the query or after the locking clauses; given exactly when there is a destination
     */
    public function __construct(public readonly Query $query, array $locking = [], public readonly ?IntoDestination $into = null, public readonly ?IntoPosition $intoPosition = null)
    {
        $this->locking = Check::listOf($locking, LockingClause::class, 'The locking clauses of a query are locking clauses.');
        Check::input($locking !== [] || $into !== null, 'A query statement holds an INTO or a locking clause.');
        Check::input(($into === null) === ($intoPosition === null), 'An INTO destination is written at exactly one position.');
        Check::input($intoPosition !== IntoPosition::AfterItems, 'An INTO after a whole query is written after the query or after its locking clauses.');
        Check::input($intoPosition !== IntoPosition::AfterLocking || $locking !== [], 'An INTO after the locking clauses follows at least one of them.');
        Check::input(!$query instanceof self && !$query instanceof Select && !($query instanceof QueryExpression && $query->body instanceof Select), 'The INTO and locking clauses of a single query block belong to the block.');
        Check::input(!$query instanceof OrderedSetOperation, 'A set operation ordered after its last SELECT ends its subquery.');
    }

    /**
     * Derives the query as a statement root and records its rows as the output.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $derivation->output($derivation->query($this, $derivation->environment()));
    }

    /**
     * Derives the query, the INTO targets and the locking clauses.
     */
    public function deriveQuery(Derivation $derivation, Environment $outer): QueryFact
    {
        $fact = $derivation->query($this->query, $outer);
        (new TailFacts())->derive($derivation, $outer, $fact, $this->into, $this->locking, null);

        return $fact;
    }

    /**
     * Writes the query and the clauses in written order.
     */
    public function render(Output $out): void
    {
        $out->node($this->query);
        if ($this->intoPosition === IntoPosition::AfterQuery) {
            $out->keyword('INTO')->node($this->into);
        }
        foreach ($this->locking as $clause) {
            $out->node($clause);
        }
        if ($this->intoPosition === IntoPosition::AfterLocking) {
            $out->keyword('INTO')->node($this->into);
        }
    }
}
