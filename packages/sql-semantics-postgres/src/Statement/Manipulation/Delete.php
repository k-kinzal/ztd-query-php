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
use SqlSemantics\Platform\PostgreSql\Statement\Query\CommonTables;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Target;

/**
 * A DELETE: removal of the rows of a table that meet a condition, optionally joined with USING items.
 *
 * Mirrors PostgreSQL's `DeleteStmt` (relation, usingClause, whereClause or
 * `CurrentOfExpr`, returningList, withClause). The facts follow PG-CHANGE-001.
 * Source: https://www.postgresql.org/docs/17/sql-delete.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class Delete implements Modification
{
    use Snapshot;

    /**
     * @var list<Target> The RETURNING list in written order
     */
    public readonly array $returning;

    /**
     * @param CommonTables|null $with The WITH clause
     * @param TargetTable $target The table rows are deleted from
     * @param Relation|null $using The USING item, or the list of USING items
     * @param Scalar|CurrentOf|null $where The condition, or the cursor position
     * @param list<Target> $returning The RETURNING list in written order
     *
     * @throws InvalidConstruction When RETURNING holds a foreign item
     */
    public function __construct(
        public readonly ?CommonTables $with,
        public readonly TargetTable $target,
        public readonly ?Relation $using = null,
        public readonly Scalar|CurrentOf|null $where = null,
        array $returning = [],
    ) {
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
        return (new ChangeFacts())->delete($this, $derivation, $outer);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->node($this->with)->keyword('DELETE', 'FROM')->node($this->target);
        if ($this->using !== null) {
            $out->keyword('USING')->node($this->using);
        }
        if ($this->where !== null) {
            $out->keyword('WHERE')->node($this->where);
        }
        if ($this->returning !== []) {
            $out->keyword('RETURNING')->list($this->returning);
        }
    }
}
