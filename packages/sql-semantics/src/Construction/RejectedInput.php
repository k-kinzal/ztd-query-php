<?php

declare(strict_types=1);

namespace SqlSemantics\Construction;

use RuntimeException;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Fact\Diagnostic;

/**
 * An input operand outside a semantic constructor's domain.
 *
 * Lowering reports the typed problem before constructing a statement. This is
 * distinct from a problem of resolving names against a declaration context.
 * The diagnostic payload contains the typed problem and the integer end of
 * the operand. Neither input SQL nor a parser node is a diagnostic operand.
 *
 * @visibility public
 * @example Retaining a typed operand problem
 *     $problem = new class implements \SqlSemantics\Statement\Fact\Diagnostic { public function message(): string { return 'Invalid operand.'; } };
 *     $failure = new \SqlSemantics\Construction\RejectedInput($problem, 12);
 *     [$failure->problem === $problem, $failure->end, $failure->getMessage()] // => [true, 12, 'Invalid operand.']
 */
final class RejectedInput extends RuntimeException
{
    /**
     * @param Diagnostic $problem The typed reason the operand cannot be constructed
     * @param int $end The exclusive byte offset of the completed operand
     */
    public function __construct(public readonly Diagnostic $problem, public readonly int $end)
    {
        Check::input($end >= 0, 'An input operand ends at a nonnegative byte offset.');
        parent::__construct($problem->message());
    }
}
