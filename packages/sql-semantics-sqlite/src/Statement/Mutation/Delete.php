<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Mutation;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Rules\ClosedList;
use SqlSemantics\Platform\Sqlite\Rules\Expression\ProgramOnly;
use SqlSemantics\Platform\Sqlite\Rules\Mutation\MutationScope;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Star;
use SqlSemantics\Platform\Sqlite\Statement\Query\TableStar;
use SqlSemantics\Platform\Sqlite\Statement\Query\With\WithClause;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A DELETE of the rows of a table that satisfy a predicate.
 *
 * Rule: SQLITE-DELETE-001. The predicate sees the written table and, beyond
 * it, the enclosing environment; the rest follows SQLITE-MUTATION-SCOPE-001.
 * Source: https://sqlite.org/lang_delete.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading the parts of a DELETE
 *     $delete = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('DELETE FROM t WHERE a = 1 RETURNING a');
 *     [$delete->statement->target->name->name->value, $delete->statement->where !== null, $delete->field(0)->name->value] // => ['t', true, 'a']
 */
final class Delete implements Statement
{
    use Snapshot;

    /**
     * @var list<ResultColumn|Star|TableStar> The RETURNING columns in written order
     */
    public readonly array $returning;

    /**
     * @param MutationTarget $target The written table
     * @param Scalar|null $where The row predicate
     * @param list<ResultColumn|Star|TableStar> $returning The RETURNING columns
     * @param WithClause|null $with The WITH clause of the statement
     * @throws InvalidConstruction When a RETURNING column is of no result column class
     */
    public function __construct(public readonly MutationTarget $target, public readonly ?Scalar $where = null, array $returning = [], public readonly ?WithClause $with = null)
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
        $scope = new MutationScope();
        [$base, $target] = $scope->open($this->target, $this->with, $derivation, $outer);
        if ($this->where !== null) {
            $derivation->scalar($this->where, new Environment($derivation->context, $base, [$target]));
        }

        return $scope->returning($this->returning, $target, $derivation, $base);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->node($this->with)->keyword('DELETE', 'FROM')->node($this->target);
        if ($this->where !== null) {
            $out->keyword('WHERE')->node($this->where);
        }
        if ($this->returning !== []) {
            $out->keyword('RETURNING')->list($this->returning);
        }
    }
}
