<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Subquery;

use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Typing\Domain;
use Override;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * Retains the source value of a constant projection independently of its empty-result comparison state.
 *
 * Legacy empty rows clear their directly compared values after the first read. Constant
 * conversions can retain the source value, and integer and decimal NULL tests still inspect
 * that source. NULL sources and their diagnostics are retained too. Differential tests cover
 * the source kinds, comparison kinds, output positions and SQL modes.
 *
 * @visibility MySqlMemory\Evaluation
 */
final class CachedProjection implements Evaluable
{
    /**
     * @param Evaluable $value The constant expression
     */
    public function __construct(public readonly Evaluable $value)
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
     * Reads the cleared comparison slot or its retained constant conversion after an empty row is reused.
     */
    public function compared(Frame $frame, bool $repeated, Kind $mode, bool $nullOperand): int|float|string|null
    {
        $value = $this->evaluate($frame);
        $kind = $this->domain()->kind;
        if (!$repeated || $value === null || !in_array($kind, [Kind::Integer, Kind::Decimal, Kind::Double, Kind::String], true)) {
            return $value;
        }
        if ($nullOperand) {
            return $kind === Kind::Integer || $kind === Kind::Decimal ? $value : null;
        }

        return $mode === $kind ? null : $value;
    }
}
