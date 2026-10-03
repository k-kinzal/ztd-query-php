<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Mutation;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Rules\ClosedList;
use SqlSemantics\Platform\Sqlite\Rules\Expression\ProgramOnly;
use SqlSemantics\Platform\Sqlite\Rules\Mutation\InsertFacts;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Star;
use SqlSemantics\Platform\Sqlite\Statement\Query\TableStar;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * An INSERT of one row made of the default values of the columns.
 *
 * Rule: SQLITE-INSERT-DEFAULTS-001. The facts follow SQLITE-INSERT-001
 * without a source of rows.
 * Source: https://sqlite.org/lang_insert.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading an INSERT of default values
 *     $insert = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('INSERT INTO t DEFAULT VALUES RETURNING *');
 *     [$insert->statement->into->target->name->name->value, count($insert->statement->returning), $insert->toString()] // => ['t', 1, 'INSERT INTO t DEFAULT VALUES RETURNING *']
 */
final class InsertDefaults implements Statement
{
    use Snapshot;

    /**
     * @var list<ResultColumn|Star|TableStar> The RETURNING columns in written order
     */
    public readonly array $returning;

    /**
     * @param InsertInto $into The head: verb, written table and column list
     * @param list<ResultColumn|Star|TableStar> $returning The RETURNING columns
     * @throws InvalidConstruction When a RETURNING column is of no result column class
     */
    public function __construct(public readonly InsertInto $into, array $returning = [])
    {
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
        return (new InsertFacts())->derive($this->into, null, [], $this->returning, $derivation, $outer);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->node($this->into)->keyword('DEFAULT', 'VALUES');
        if ($this->returning !== []) {
            $out->keyword('RETURNING')->list($this->returning);
        }
    }
}
