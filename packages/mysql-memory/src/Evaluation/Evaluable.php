<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation;

use MySqlMemory\Typing\Domain;

/**
 * An expression ready to be evaluated: its resolved domain and how to compute its value from a frame.
 *
 * The value is held as the kind of the domain says (Kind), or is null for SQL NULL.
 *
 * @visibility MySqlMemory
 */
interface Evaluable
{
    /**
     * Answers the resolved domain of the expression.
     */
    public function domain(): Domain;

    /**
     * Computes the value for the row of a frame.
     *
     * Evaluation can advance random state, assign variables and publish diagnostics.
     * Repeated reads of the same expression and frame need not return the same value.
     *
     * @phpstan-impure
     * @throws \MySqlMemory\Error\SqlError When the evaluation is an error
     */
    public function evaluate(Frame $frame): int|float|string|null;
}
