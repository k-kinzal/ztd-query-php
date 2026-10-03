<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Mutation;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Rules\ClosedList;
use SqlSemantics\Platform\Sqlite\Rules\Expression\ProgramOnly;
use SqlSemantics\Platform\Sqlite\Rules\Mutation\InsertFacts;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Star;
use SqlSemantics\Platform\Sqlite\Statement\Query\TableStar;
use SqlSemantics\Platform\Sqlite\Statement\Query\ValuesClause;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * An INSERT of the rows a query returns.
 *
 * Rule: SQLITE-INSERT-SELECT-001. The facts follow SQLITE-INSERT-001 with
 * the query as the source of the rows. A bare VALUES clause is not this
 * statement but an insert of written rows.
 * Source: https://sqlite.org/lang_insert.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading the query of an INSERT
 *     $insert = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('INSERT INTO t (a) SELECT b FROM u');
 *     [$insert->statement->query->from->name->name->value, $insert->toString()] // => ['u', 'INSERT INTO t (a) SELECT b FROM u']
 * @example Refusing a bare VALUES clause as the query
 *     $rows = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('VALUES (1)')->statement;
 *     new \SqlSemantics\Platform\Sqlite\Statement\Mutation\InsertSelect(new \SqlSemantics\Platform\Sqlite\Statement\Mutation\InsertInto(new \SqlSemantics\Platform\Sqlite\Statement\Mutation\MutationTarget(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')))), $rows) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class InsertSelect implements Statement
{
    use Snapshot;

    /**
     * @var list<Upsert> The ON CONFLICT clauses in written order
     */
    public readonly array $upserts;

    /**
     * @var list<ResultColumn|Star|TableStar> The RETURNING columns in written order
     */
    public readonly array $returning;

    /**
     * @param InsertInto $into The head: verb, written table and column list
     * @param Query $query The query that supplies the rows; not a bare VALUES clause
     * @param list<Upsert> $upserts The ON CONFLICT clauses; only the last may omit its conflict target
     * @param list<ResultColumn|Star|TableStar> $returning The RETURNING columns
     * @throws InvalidConstruction When a RETURNING column is of no result column class
     */
    public function __construct(public readonly InsertInto $into, public readonly Query $query, array $upserts = [], array $returning = [])
    {
        Check::input(!$query instanceof ValuesClause, 'An INSERT of a bare VALUES clause is an insert of written rows.');
        $this->upserts = Check::listOf($upserts, Upsert::class, 'The ON CONFLICT clauses of an INSERT are upsert clauses.');
        foreach ($this->upserts as $position => $upsert) {
            Check::input($upsert->target !== null || $position === count($this->upserts) - 1, 'Only the last ON CONFLICT clause may omit the conflict target.');
        }
        $this->returning = (new ClosedList())->of($returning, [ResultColumn::class, Star::class, TableStar::class], 'A RETURNING column is an expression, a star or a qualified star.');
    }

    /**
     * Derives the statement as a root and records the rows of RETURNING as the output.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new ProgramOnly())->outsideProgram($this, $derivation);
        $fact = $this->deriveWithin($derivation, $derivation->environment());
        if ($fact !== null) {
            $derivation->output($fact);
        }
    }

    /**
     * Derives the statement inside an environment, as a trigger program does, and answers the rows it returns.
     */
    public function deriveWithin(Derivation $derivation, Environment $outer): ?QueryFact
    {
        return (new InsertFacts())->derive($this->into, $this->query, $this->upserts, $this->returning, $derivation, $outer);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->node($this->into)->node($this->query);
        foreach ($this->upserts as $upsert) {
            $out->node($upsert);
        }
        if ($this->returning !== []) {
            $out->keyword('RETURNING')->list($this->returning);
        }
    }
}
