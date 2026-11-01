<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query\Problem\Operand;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A MySQL 5.6 UNION starting with parentheses has a later unenclosed operand.
 *
 * The optional position formats an input refusal; direct construction needs
 * only the semantic condition. Neither form retains source text or a tree.
 * Observed on native MySQL 5.6.51 and 5.7.44, including stored definitions.
 *
 * @example Refusing a negative input boundary
 *     new \SqlSemantics\Platform\MySql\Statement\Query\Problem\Operand\UnenclosedUnion(-1) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @visibility public
 * @example Describing the versioned enclosure condition
 *     (new \SqlSemantics\Platform\MySql\Statement\Query\Problem\Operand\UnenclosedUnion())->message() // => 'A UNION starting with parentheses requires every operand to be enclosed in MySQL 5.6.'
 */
final class UnenclosedUnion implements Diagnostic
{
    use Snapshot;

    /**
     * @param int|null $offset The end of the refused input operand, when known
     */
    public function __construct(public readonly ?int $offset = null)
    {
        Check::input($offset === null || $offset >= 0, 'An input operand ends at a nonnegative byte offset.');
    }

    /**
     * Describes the operand constraint independently of input formatting.
     */
    public function message(): string
    {
        return 'A UNION starting with parentheses requires every operand to be enclosed in MySQL 5.6.';
    }
}
