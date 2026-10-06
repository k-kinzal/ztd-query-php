<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Routine\Option;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\SignedNumber;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Snapshot;

/**
 * `COST number` or `ROWS number`: a planner estimate for the routine.
 *
 * Source: https://www.postgresql.org/docs/17/sql-createfunction.html.
 *
 * @visibility public
 * @example Reading the estimated cost
 *     $estimate = new \SqlSemantics\Platform\PostgreSql\Statement\Routine\Option\RoutineEstimate(\SqlSemantics\Platform\PostgreSql\Statement\Routine\Option\EstimateKind::Cost, new \SqlSemantics\Platform\PostgreSql\Statement\Literal\SignedNumber(false, new \SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant('100')));
 *     [$estimate->setting(), $estimate->value->magnitude->digits] // => ['cost', '100']
 */
final class RoutineEstimate implements RoutineOption
{
    use Snapshot;

    /**
     * @param EstimateKind $kind What the estimate states
     * @param SignedNumber $value The estimate
     */
    public function __construct(public readonly EstimateKind $kind, public readonly SignedNumber $value)
    {
    }

    /**
     * Tells that ALTER accepts the option.
     */
    public function alterable(): bool
    {
        return true;
    }

    /**
     * Answers `cost` or `rows`.
     */
    public function setting(): string
    {
        return strtolower($this->kind->value);
    }

    /**
     * Derives nothing: a number holds no expression.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
    }

    /**
     * Writes the keyword and the number.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->kind->value)->node($this->value);
    }
}
