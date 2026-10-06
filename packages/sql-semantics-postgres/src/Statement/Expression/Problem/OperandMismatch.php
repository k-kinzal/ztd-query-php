<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Expression\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * An operand whose type the construct does not accept.
 *
 * Source: https://www.postgresql.org/docs/17/functions-logical.html.
 *
 * @visibility public
 * @example Reading the message
 *     (new \SqlSemantics\Platform\PostgreSql\Statement\Expression\Problem\OperandMismatch('AND', 'boolean', 'integer'))->message() // => 'Argument of AND must be type boolean, not type integer.'
 */
final class OperandMismatch implements Diagnostic
{
    use Snapshot;

    /**
     * @param string $construct The construct, such as 'AND'
     * @param string $expected The type the construct needs
     * @param string $actual The type of the operand
     */
    public function __construct(public readonly string $construct, public readonly string $expected, public readonly string $actual)
    {
    }

    /**
     * Describes the problem.
     */
    public function message(): string
    {
        return 'Argument of ' . $this->construct . ' must be type ' . $this->expected . ', not type ' . $this->actual . '.';
    }
}
