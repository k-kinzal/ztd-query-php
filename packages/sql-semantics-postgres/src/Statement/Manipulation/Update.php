<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Manipulation;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\QueryFact;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Relation;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Rules\Manipulation\ChangeFacts;
use SqlSemantics\Platform\PostgreSql\Rules\Manipulation\Roots;
use SqlSemantics\Platform\PostgreSql\Rules\Query\ClosedList;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Assignment\Assignment;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Assignment\RowAssignment;
use SqlSemantics\Platform\PostgreSql\Statement\Query\CommonTables;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Target;

/**
 * An UPDATE: assignments to the rows of a table that meet a condition, optionally joined with other FROM items.
 *
 * Mirrors PostgreSQL's `UpdateStmt` (relation, targetList, fromClause,
 * whereClause or `CurrentOfExpr`, returningList, withClause). The facts
 * follow PG-CHANGE-001.
 * Source: https://www.postgresql.org/docs/17/sql-update.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 * @example Refusing UPDATE without an assignment
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Update(null, new \SqlSemantics\Platform\PostgreSql\Statement\Manipulation\TargetTable(new \SqlSemantics\Platform\PostgreSql\Statement\Name\RelationReference(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('t')))), []) // throws \SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction
 */
final class Update implements Modification
{
    use Snapshot;

    /**
     * @var non-empty-list<Assignment|RowAssignment> The SET items in written order
     */
    public readonly array $assignments;

    /**
     * @var list<Target> The RETURNING list in written order
     */
    public readonly array $returning;

    /**
     * @param CommonTables|null $with The WITH clause
     * @param TargetTable $target The table updated
     * @param list<Assignment|RowAssignment> $assignments The SET items in written order; at least one
     * @param Relation|null $from The FROM item, or the list of FROM items
     * @param Scalar|CurrentOf|null $where The condition, or the cursor position
     * @param list<Target> $returning The RETURNING list in written order
     *
     * @throws InvalidConstruction When there is no SET item, or a list holds a foreign item
     */
    public function __construct(
        public readonly ?CommonTables $with,
        public readonly TargetTable $target,
        array $assignments,
        public readonly ?Relation $from = null,
        public readonly Scalar|CurrentOf|null $where = null,
        array $returning = [],
    ) {
        $this->assignments = (new ClosedList())->of($assignments, [Assignment::class, RowAssignment::class], 'UPDATE SET holds at least one assignment.', 1);
        $this->returning = Check::listOf($returning, Target::class, 'RETURNING holds output items.');
    }

    /**
     * Tells whether the statement has a RETURNING list.
     */
    public function returnsRows(): bool
    {
        return $this->returning !== [];
    }

    /**
     * Answers the WITH clause written before the statement.
     */
    public function commonTables(): ?CommonTables
    {
        return $this->with;
    }

    /**
     * Derives the statement as a root and records the rows of RETURNING as the output.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new Roots())->derive($this, $derivation);
    }

    /**
     * Derives every part against an outer environment and answers the rows of RETURNING.
     */
    public function deriveQuery(Derivation $derivation, Environment $outer): QueryFact
    {
        return (new ChangeFacts())->update($this, $derivation, $outer);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->node($this->with)->keyword('UPDATE')->node($this->target)->keyword('SET')->list($this->assignments);
        if ($this->from !== null) {
            $out->keyword('FROM')->node($this->from);
        }
        if ($this->where !== null) {
            $out->keyword('WHERE')->node($this->where);
        }
        if ($this->returning !== []) {
            $out->keyword('RETURNING')->list($this->returning);
        }
    }
}
