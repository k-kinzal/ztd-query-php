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
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * An INSERT of rows written in a VALUES clause.
 *
 * Rule: SQLITE-INSERT-ROWS-001. The facts follow SQLITE-INSERT-001 with the
 * VALUES clause as the source of the rows.
 * Source: https://sqlite.org/lang_insert.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading the rows of an INSERT
 *     $insert = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze("INSERT INTO t (a, b) VALUES (1, 'x'), (2, 'y') RETURNING a");
 *     [count($insert->statement->rows->rows), count($insert->statement->returning), $insert->toString()] // => [2, 1, "INSERT INTO t (a, b) VALUES (1, 'x'), (2, 'y') RETURNING a"]
 */
final class InsertRows implements Statement
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
     * @param ValuesClause $rows The rows
     * @param list<Upsert> $upserts The ON CONFLICT clauses; only the last may omit its conflict target
     * @param list<ResultColumn|Star|TableStar> $returning The RETURNING columns
     * @throws InvalidConstruction When a RETURNING column is of no result column class
     */
    public function __construct(public readonly InsertInto $into, public readonly ValuesClause $rows, array $upserts = [], array $returning = [])
    {
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
        return (new InsertFacts())->derive($this->into, $this->rows, $this->upserts, $this->returning, $derivation, $outer);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->node($this->into)->node($this->rows);
        foreach ($this->upserts as $upsert) {
            $out->node($upsert);
        }
        if ($this->returning !== []) {
            $out->keyword('RETURNING')->list($this->returning);
        }
    }
}
