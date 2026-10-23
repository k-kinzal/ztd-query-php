<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Subquery;

use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Typing\Domain;
use Override;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * Retains a projection source independently of its empty-result comparison state.
 *
 * Legacy empty rows clear their directly compared values after the first read. Constant
 * conversions can retain the source value, and integer and decimal NULL tests still inspect
 * that source. NULL sources and their diagnostics are retained too. Differential tests cover
 * the source kinds, comparison kinds, output positions and SQL modes. MySQL 5.7 also
 * clears the first runtime projection without repeating its effects.
 * MySQL 5.6 retains computed sources for typed inspection while direct runtime slots clear.
 * Constant DATE projections also keep their initial NULL state separate from their computed value.
 * DATE comparisons clear temporal reads while numeric conversions retain their source.
 *
 * @visibility MySqlMemory\Evaluation
 */
final class CachedProjection implements Evaluable
{
    /**
     * @param Evaluable $value The retained expression
     * @param bool $inspectSource Whether a repeated comparison can still inspect the retained source or its conversion
     * @param bool $initialTemporal Whether NULL inspection observes the initial state of a MySQL 5.6 constant DATE
     */
    public function __construct(public readonly Evaluable $value, public readonly bool $inspectSource = true, public readonly bool $initialTemporal = false)
    {
    }

    /**
     * Answers the unchanged expression domain.
     */
    #[Override]
    public function domain(): Domain
    {
        return $this->value->domain();
    }

    /**
     * Evaluates at the first read and retains NULL as well as non-NULL results for this statement.
     */
    #[Override]
    public function evaluate(Frame $frame): int|float|string|null
    {
        $kept = $frame->context->kept;
        if (!isset($kept[$this])) {
            $kept[$this] = [$this->value->evaluate($frame)];
        }

        return $kept[$this][0];
    }

    /**
     * Reads the cleared comparison slot or its retained source conversion after an empty row is reused.
     */
    public function compared(Frame $frame, bool $repeated, Kind $mode, bool $nullOperand): ProjectionRead
    {
        if ($this->initialTemporal && $nullOperand) {
            $initial = !isset($frame->context->kept[$this]);
            $value = $this->evaluate($frame);

            return new ProjectionRead($value, $repeated || (!$initial && $value === null));
        }
        if ($repeated && !$this->inspectSource) {
            return new ProjectionRead(null);
        }
        $value = $this->evaluate($frame);
        $kind = $this->domain()->kind;
        if ($repeated && $kind === Kind::Date && $mode->temporal() && !$nullOperand) {
            return new ProjectionRead(null);
        }
        if (!$repeated || $value === null || !in_array($kind, [Kind::Integer, Kind::Decimal, Kind::Double, Kind::String], true)) {
            return new ProjectionRead($value);
        }
        if ($nullOperand) {
            return new ProjectionRead($kind === Kind::Integer || $kind === Kind::Decimal ? $value : null);
        }

        return new ProjectionRead($mode === $kind ? null : $value);
    }
}
