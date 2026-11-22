<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Query;

use SqlSemantics\Platform\Sqlite\Model\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Rendering\Output;
use SqlSemantics\Platform\Sqlite\Model\Resolution\Environment;
use SqlSemantics\Platform\Sqlite\Model\Statement\Fact\QueryFact;
use SqlSemantics\Platform\Sqlite\Model\Statement\Query;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\Platform\Sqlite\Model\Statement\Statement;
use SqlSemantics\Platform\Sqlite\Rules\Expression\ProgramOnly;
use SqlSemantics\Platform\Sqlite\Rules\Query\CompoundFacts;
use SqlSemantics\Platform\Sqlite\Rules\Query\Ordinals;
use SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\OutputOrdinal;
use SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\SortTerm;

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
 * @visibility SqlSemantics
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
