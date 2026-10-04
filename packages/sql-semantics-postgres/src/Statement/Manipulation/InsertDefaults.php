<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Manipulation;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Rules\Manipulation\InsertFacts;
use SqlSemantics\Platform\PostgreSql\Rules\Manipulation\Roots;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Conflict\ConflictDoNothing;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Conflict\ConflictDoUpdate;
use SqlSemantics\Platform\PostgreSql\Statement\Query\CommonTables;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Target;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Snapshot;

/**
 * An INSERT of one row of column defaults: `INSERT INTO table DEFAULT VALUES`.
 *
 * Mirrors PostgreSQL's `InsertStmt` without `selectStmt`. The facts follow
 * PG-INSERT-001.
 * Source: https://www.postgresql.org/docs/17/sql-insert.html. Status: Implemented.
 *
 * @visibility public
 * @example Inserting the defaults
 *     $insert = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('insert into t default values returning *');
 *     $insert->toString() // => 'INSERT INTO t DEFAULT VALUES RETURNING *'
 */
final class InsertDefaults implements Modification
{
    use Snapshot;

    /**
     * @var list<Target> The RETURNING list in written order
     */
    public readonly array $returning;

    /**
     * @param CommonTables|null $with The WITH clause
     * @param TargetTable $target The table written; never with ONLY
     * @param ConflictDoNothing|ConflictDoUpdate|null $conflict The ON CONFLICT clause
     * @param list<Target> $returning The RETURNING list in written order
     *
     * @throws InvalidConstruction When the table is written with ONLY, or RETURNING holds a foreign item
     */
    public function __construct(
        public readonly ?CommonTables $with,
        public readonly TargetTable $target,
        public readonly ConflictDoNothing|ConflictDoUpdate|null $conflict = null,
        array $returning = [],
    ) {
        Check::input(!$target->table->only, 'The table of an INSERT is written without ONLY.');
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
        return (new InsertFacts())->defaults($this, $derivation, $outer);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->node($this->with)->keyword('INSERT', 'INTO')->node($this->target)->keyword('DEFAULT', 'VALUES')->node($this->conflict);
        if ($this->returning !== []) {
            $out->keyword('RETURNING')->list($this->returning);
        }
    }
}
