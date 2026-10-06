<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Manipulation;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Rules\Manipulation\InsertFacts;
use SqlSemantics\Platform\PostgreSql\Rules\Manipulation\Roots;
use SqlSemantics\Platform\PostgreSql\Rules\Manipulation\Sources;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Assignment\ColumnTarget;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Conflict\ConflictDoNothing;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Conflict\ConflictDoUpdate;
use SqlSemantics\Platform\PostgreSql\Statement\Query\CommonTables;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Target;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Snapshot;

/**
 * An INSERT of the rows of a query.
 *
 * Mirrors PostgreSQL's `InsertStmt` whose `selectStmt` is a query other
 * than a bare VALUES list (a VALUES list with WITH, ORDER BY, LIMIT or
 * locking clauses is such a query). The query does not see the target
 * table; its output columns are assigned to the target columns in order.
 * The facts follow PG-INSERT-001.
 * Source: https://www.postgresql.org/docs/17/sql-insert.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading the query of an INSERT
 *     $insert = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('INSERT INTO t (a) SELECT 1 ON CONFLICT DO NOTHING');
 *     [$insert->statement->source instanceof \SqlSemantics\Platform\PostgreSql\Statement\Query\Select, $insert->toString()] // => [true, 'INSERT INTO t (a) SELECT 1 ON CONFLICT DO NOTHING']
 * @example Refusing a bare VALUES list, which is an INSERT of rows
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Manipulation\InsertSelect(null, new \SqlSemantics\Platform\PostgreSql\Statement\Manipulation\TargetTable(new \SqlSemantics\Platform\PostgreSql\Statement\Name\RelationReference(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')))), [], null, new \SqlSemantics\Platform\PostgreSql\Statement\Query\ValuesList([new \SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\ValuesRow([new \SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral()])])) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class InsertSelect implements Modification
{
    use Snapshot;

    /**
     * @var list<ColumnTarget> The column list in written order
     */
    public readonly array $columns;

    /**
     * @var list<Target> The RETURNING list in written order
     */
    public readonly array $returning;

    /**
     * @param CommonTables|null $with The WITH clause
     * @param TargetTable $target The table written; never with ONLY
     * @param list<ColumnTarget> $columns The column list in written order
     * @param Overriding|null $overriding Whose values identity columns receive
     * @param Query $source The query whose rows are inserted: a selection, TABLE, a set operation, a query with clauses, or a parenthesized query that is not VALUES alone
     * @param ConflictDoNothing|ConflictDoUpdate|null $conflict The ON CONFLICT clause
     * @param list<Target> $returning The RETURNING list in written order
     *
     * @throws InvalidConstruction When the table is written with ONLY, the source is not such a query, or a list holds a foreign item
     */
    public function __construct(
        public readonly ?CommonTables $with,
        public readonly TargetTable $target,
        array $columns,
        public readonly ?Overriding $overriding,
        public readonly Query $source,
        public readonly ConflictDoNothing|ConflictDoUpdate|null $conflict = null,
        array $returning = [],
    ) {
        Check::input(!$target->table->only, 'The table of an INSERT is written without ONLY.');
        Check::input((new Sources())->insertable($source), 'The query of an INSERT is a query statement other than VALUES alone.');
        $this->columns = Check::listOf($columns, ColumnTarget::class, 'The column list of an INSERT holds column targets.');
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
        return (new InsertFacts())->select($this, $derivation, $outer);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->node($this->with)->keyword('INSERT', 'INTO')->node($this->target);
        if ($this->columns !== []) {
            $out->symbol('(')->list($this->columns)->symbol(')');
        }
        if ($this->overriding !== null) {
            $out->keyword('OVERRIDING', $this->overriding->value, 'VALUE');
        }
        $out->node($this->source)->node($this->conflict);
        if ($this->returning !== []) {
            $out->keyword('RETURNING')->list($this->returning);
        }
    }
}
