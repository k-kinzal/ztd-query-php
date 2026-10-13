<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Query\Result\SetOutput;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\StatementQuery;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Snapshot;

/**
 * A query submitted as a statement, separately from a query used as an operand.
 *
 * Query bodies also occur in subqueries and INSERT sources, whose grammar
 * positions admit different forms. Only this wrapper implements the statement
 * role. Its checks concern that grammatical position, not execution eligibility.
 *
 * @visibility public
 * @example Reading the body of a SELECT statement
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT 1');
 *     $operation->statement->query::class // => \SqlSemantics\Platform\MySql\Statement\Query\Select::class
 */
final class QueryRoot implements Explainable, \SqlSemantics\Statement\Selection
{
    use Snapshot;

    /**
     * @param Query $query The query in the statement grammar position
     */
    public function __construct(public readonly Query $query)
    {
        (new StatementQuery())->check($query);
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives the query and its externally returned fields.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $derivation->output((new SetOutput())->derive($this->query, $derivation));
    }

    /**
     * Answers the input relation of the query body, when it has one.
     */
    public function input(): ?\SqlSemantics\Statement\Relation
    {
        return $this->query instanceof \SqlSemantics\Statement\Selection ? $this->query->input() : null;
    }

    /**
     * Writes the query in its statement position.
     */
    public function render(Output $out): void
    {
        $out->node($this->query);
    }
}
