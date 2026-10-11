<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Subquery;

use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Typing\Domain;
use Override;

/**
 * Retains a constant projection value and its diagnostics after the first read in a statement.
 *
 * MySQL 5.7 reads a resolved constant of an empty row subquery once even when an outer query
 * compares that row repeatedly. MySQL 5.6 and modern releases repeat its evaluation instead
 * (verified through SQL on MySQL 5.6.51, 5.7.44, 8.0.44, 8.4.7 and 9.1.0).
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
}
