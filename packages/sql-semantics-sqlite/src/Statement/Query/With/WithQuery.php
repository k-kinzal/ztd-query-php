<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Query\With;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Rules\Query\CommonTables;
use SqlSemantics\Platform\Sqlite\Statement\Query\Compound;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Query\Values;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A query with its own WITH clause.
 *
 * Rule: SQLITE-WITH-QUERY-001. The common tables are bound by
 * SQLITE-COMMON-TABLE-001 and are visible in the body and in every query
 * nested in it. The output is that of the body.
 * Source: https://sqlite.org/lang_with.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading the body of a query with common tables
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('WITH c AS (SELECT 1 AS x) SELECT x FROM c');
 *     [$query->statement->body->columns[0]->expression->name->value, $query->field('x')->type->descriptor] // => ['x', \SqlSemantics\Platform\Sqlite\Statement\Type\Storage::Integer]
 */
final class WithQuery implements Statement, Query
{
    use Snapshot;

    /**
     * @param WithClause $with The WITH clause
     * @param Select|Values|Compound $body The query the clause belongs to
     */
    public function __construct(public readonly WithClause $with, public readonly Select|Values|Compound $body)
    {
    }

    /**
     * Derives the query as a statement root and records its rows as the output.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $derivation->output($derivation->query($this, $derivation->environment()));
    }

    /**
     * Binds the common tables and derives the body where they are visible.
     */
    public function deriveQuery(Derivation $derivation, Environment $outer): QueryFact
    {
        $fact = $derivation->query($this->body, (new CommonTables())->bind($this->with, $derivation, $outer));

        return new QueryFact($fact->projection, $fact->names);
    }

    /**
     * Writes the clause and the body.
     */
    public function render(Output $out): void
    {
        $out->node($this->with)->node($this->body);
    }
}
