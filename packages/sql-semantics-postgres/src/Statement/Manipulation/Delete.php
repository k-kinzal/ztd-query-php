<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Manipulation;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Rules\Manipulation\ChangeFacts;
use SqlSemantics\Platform\PostgreSql\Rules\Manipulation\Roots;
use SqlSemantics\Platform\PostgreSql\Statement\Query\CommonTables;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Target;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * A DELETE: removal of the rows of a table that meet a condition, optionally joined with USING items.
 *
 * Mirrors PostgreSQL's `DeleteStmt` (relation, usingClause, whereClause or
 * `CurrentOfExpr`, returningList, withClause). The facts follow PG-CHANGE-001.
 * Source: https://www.postgresql.org/docs/17/sql-delete.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading the parts of a DELETE
 *     $delete = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('DELETE FROM ONLY t USING u WHERE t.a = u.a RETURNING *');
 *     [$delete->statement->target->table->only, $delete->toString()] // => [true, 'DELETE FROM ONLY t USING u WHERE t.a = u.a RETURNING *']
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
