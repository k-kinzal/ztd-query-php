<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Manipulation;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\QueryFact;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Rules\Manipulation\InsertFacts;
use SqlSemantics\Platform\PostgreSql\Rules\Manipulation\Roots;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Assignment\ColumnTarget;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Conflict\ConflictDoNothing;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Conflict\ConflictDoUpdate;
use SqlSemantics\Platform\PostgreSql\Statement\Query\CommonTables;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Target;

/**
 * An INSERT of rows written in VALUES.
 *
 * Mirrors PostgreSQL's `InsertStmt` whose `selectStmt` is a bare VALUES
 * list: each value is assigned to its column on its own, and may be
 * DEFAULT. The facts follow PG-INSERT-001.
 * Source: https://www.postgresql.org/docs/17/sql-insert.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 * @example Refusing ONLY on the table of an INSERT
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Manipulation\InsertRows(null, new \SqlSemantics\Platform\PostgreSql\Statement\Manipulation\TargetTable(new \SqlSemantics\Platform\PostgreSql\Statement\Name\RelationReference(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('t')), true)), [], null, new \SqlSemantics\Platform\PostgreSql\Statement\Manipulation\ValueRows([new \SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\ValuesRow([new \SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral()])])) // throws \SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction
 */
final class InsertRows implements Modification
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
     * @param ValueRows|ParenthesizedRows $rows The rows
     * @param ConflictDoNothing|ConflictDoUpdate|null $conflict The ON CONFLICT clause
     * @param list<Target> $returning The RETURNING list in written order
     *
     * @throws InvalidConstruction When the table is written with ONLY, or a list holds a foreign item
     */
    public function __construct(
        public readonly ?CommonTables $with,
        public readonly TargetTable $target,
        array $columns,
        public readonly ?Overriding $overriding,
        public readonly ValueRows|ParenthesizedRows $rows,
        public readonly ConflictDoNothing|ConflictDoUpdate|null $conflict = null,
        array $returning = [],
    ) {
        Check::input(!$target->table->only, 'The table of an INSERT is written without ONLY.');
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
        return (new InsertFacts())->rows($this, $derivation, $outer);
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
        $out->node($this->rows)->node($this->conflict);
        if ($this->returning !== []) {
            $out->keyword('RETURNING')->list($this->returning);
        }
    }
}
