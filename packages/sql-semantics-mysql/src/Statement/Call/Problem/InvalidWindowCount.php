<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call\Problem;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunctionKind;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A retained window invocation has a literal outside its count or offset domain.
 *
 * The owning deferred call retains the operand and window dependencies. This
 * condition is checked after window options and frames have been resolved.
 *
 * @visibility public
 * @example Describing an invalid bucket count
 *     (new \SqlSemantics\Platform\MySql\Statement\Call\Problem\InvalidWindowCount(\SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunctionKind::Tile))->message() // => 'Incorrect arguments to ntile'
 */
final class InvalidWindowCount implements Diagnostic
{
    use Snapshot;

    /**
     * @param WindowFunctionKind $function The invocation whose count or offset is invalid
     */
    public function __construct(public readonly WindowFunctionKind $function)
    {
        Check::input($function->counted() !== null || $function === WindowFunctionKind::NthValue, 'A count condition belongs to a function with a count or offset.');
    }

    /**
     * Describes the argument restriction using the native function name.
     */
    public function message(): string
    {
        return 'Incorrect arguments to ' . strtolower($this->function->value);
    }
}
