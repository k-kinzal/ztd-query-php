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
use SqlSemantics\Platform\PostgreSql\Rules\Manipulation\MergeFacts;
use SqlSemantics\Platform\PostgreSql\Rules\Manipulation\Roots;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Merge\MergeWhen;
use SqlSemantics\Platform\PostgreSql\Statement\Query\CommonTables;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Target;

/**
 * A MERGE: the rows of a source joined to a target table, each inserted, updated, deleted or skipped by the first WHEN clause that applies.
 *
 * Mirrors PostgreSQL's `MergeStmt` (relation, sourceRelation, joinCondition,
 * mergeWhenClauses, returningList (PostgreSQL 17), withClause). The facts
 * follow PG-MERGE-001.
 * Source: https://www.postgresql.org/docs/17/sql-merge.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 * @example Refusing MERGE without a WHEN clause
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Merge(null, new \SqlSemantics\Platform\PostgreSql\Statement\Manipulation\TargetTable(new \SqlSemantics\Platform\PostgreSql\Statement\Name\RelationReference(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('t')))), new \SqlSemantics\Platform\PostgreSql\Statement\Relation\TableInput(new \SqlSemantics\Platform\PostgreSql\Statement\Name\RelationReference(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('u')))), new \SqlSemantics\Platform\PostgreSql\Statement\Literal\BooleanLiteral(true), []) // throws \SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction
 */
final class Merge implements Modification
{
    use Snapshot;

    /**
     * @var non-empty-list<MergeWhen> The WHEN clauses in written order
     */
    public readonly array $clauses;

    /**
     * @var list<Target> The RETURNING list in written order
     */
    public readonly array $returning;

    /**
     * @param CommonTables|null $with The WITH clause
     * @param TargetTable $target The table merged into
     * @param Relation $source The source of the rows: a table, a subquery or any other FROM item
     * @param Scalar $condition The join condition written after ON
     * @param list<MergeWhen> $clauses The WHEN clauses in written order; at least one
     * @param list<Target> $returning The RETURNING list in written order (PostgreSQL 17)
     *
     * @throws InvalidConstruction When there is no WHEN clause, or a list holds a foreign item
     */
    public function __construct(
        public readonly ?CommonTables $with,
        public readonly TargetTable $target,
        public readonly Relation $source,
        public readonly Scalar $condition,
        array $clauses,
        array $returning = [],
    ) {
        $this->clauses = Check::listOf($clauses, MergeWhen::class, 'MERGE holds at least one WHEN clause.', 1);
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
        return (new MergeFacts())->derive($this, $derivation, $outer);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->node($this->with)->keyword('MERGE', 'INTO')->node($this->target)->keyword('USING')->node($this->source)
            ->keyword('ON')->node($this->condition);
        foreach ($this->clauses as $clause) {
            $out->node($clause);
        }
        if ($this->returning !== []) {
            $out->keyword('RETURNING')->list($this->returning);
        }
    }
}
