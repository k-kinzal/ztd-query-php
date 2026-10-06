<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Query;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Rules\Expression\ProgramOnly;
use SqlSemantics\Platform\Sqlite\Rules\Query\CompoundFacts;
use SqlSemantics\Platform\Sqlite\Rules\Query\Ordinals;
use SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\OutputOrdinal;
use SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\SortTerm;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A compound query: arms combined from left to right by UNION, UNION ALL, INTERSECT and EXCEPT.
 *
 * An ORDER BY and a LIMIT written after the last arm order and limit the
 * combined rows, so they belong to the compound and not to that arm. The
 * grammar also admits them on an earlier arm, which SQLite rejects; there
 * they stay on the arm.
 *
 * Rule: SQLITE-COMPOUND-001. The facts are derived by SQLITE-COMPOUND-SCOPE-001.
 * Source: https://sqlite.org/lang_select.html#compound_select_statements.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the arms and the ordering of a compound query
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT 1 AS a UNION SELECT 2 ORDER BY a LIMIT 1');
 *     [count($query->statement->steps), count($query->statement->orderBy), $query->statement->steps[0]->query->orderBy, $query->toString()] // => [1, 1, [], 'SELECT 1 AS a UNION SELECT 2 ORDER BY a LIMIT 1']
 * @example Refusing an ordering left on the last arm
 *     $ordered = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT 1 AS a ORDER BY a')->statement;
 *     new \SqlSemantics\Platform\Sqlite\Statement\Query\Compound(new \SqlSemantics\Platform\Sqlite\Statement\Query\ValuesClause([new \SqlSemantics\Platform\Sqlite\Statement\Query\ValueRow([new \SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\NullLiteral()])]), [new \SqlSemantics\Platform\Sqlite\Statement\Query\CompoundStep(\SqlSemantics\Platform\Sqlite\Statement\Query\CompoundOperator::Union, $ordered)]) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class Compound implements Statement, Query
{
    use Snapshot;

    /**
     * @var non-empty-list<CompoundStep> The further arms in written order
     */
    public readonly array $steps;

    /**
     * @var list<SortTerm> The ORDER BY terms of the combined rows
     */
    public readonly array $orderBy;

    /**
     * @param Select|ValuesClause $first The first arm
     * @param list<CompoundStep> $steps The further arms in written order; at least one
     * @param list<SortTerm> $orderBy The ORDER BY terms of the combined rows
     * @param Limit|null $limit The LIMIT clause of the combined rows
     */
    public function __construct(public readonly Select|ValuesClause $first, array $steps, array $orderBy = [], public readonly ?Limit $limit = null)
    {
        $this->steps = Check::listOf($steps, CompoundStep::class, 'A compound query has at least two arms.', 1);
        $this->orderBy = Check::listOf($orderBy, SortTerm::class, 'ORDER BY terms are ordering terms.');
        $last = $this->steps[count($this->steps) - 1]->query;
        Check::input(!$last instanceof Select || ($last->orderBy === [] && $last->limit === null), 'ORDER BY and LIMIT after the last arm belong to the compound query.');
        Check::input($last instanceof Select || ($orderBy === [] && $limit === null), 'ORDER BY and LIMIT cannot follow a VALUES clause.');
        foreach ($this->orderBy as $term) {
            Check::input($term->expression instanceof OutputOrdinal || (new Ordinals())->value($term->expression) === null, 'An integer constant in ORDER BY is a result column position.');
        }
    }

    /**
     * Derives the compound query as a statement root and records its rows as the output.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new ProgramOnly())->outsideProgram($this, $derivation);
        $derivation->output($derivation->query($this, $derivation->environment()));
    }

    /**
     * Derives the arms, the ordering and the output fields.
     */
    public function deriveQuery(Derivation $derivation, Environment $outer): QueryFact
    {
        return (new CompoundFacts())->derive($this, $derivation, $outer);
    }

    /**
     * Writes the arms, then the ordering and the limit.
     */
    public function render(Output $out): void
    {
        $out->node($this->first);
        foreach ($this->steps as $step) {
            $out->node($step);
        }
        if ($this->orderBy !== []) {
            $out->keyword('ORDER', 'BY')->list($this->orderBy);
        }
        $out->node($this->limit);
    }
}
