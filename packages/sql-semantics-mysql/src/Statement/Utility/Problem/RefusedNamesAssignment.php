<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Problem;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A NAMES assignment has no SET-item meaning; only its diagnostic positions escape lowering.
 *
 * The offset points to the assignment operator and end follows the expression.
 * A visible local variable named names changes the diagnostic, but does not
 * make the unquoted assignment valid. Quoted variable assignments use NameAssignment.
 *
 * @visibility public
 * @example Retaining an input refusal without an expression or parser tree
 *     $problem = new \SqlSemantics\Platform\MySql\Statement\Utility\Problem\RefusedNamesAssignment(10, 13);
 *     [$problem->offset, $problem->end, $problem->shadowed] // => [10, 13, false]
 */
final class RefusedNamesAssignment implements Diagnostic
{
    use Snapshot;

    /**
     * @param int $offset The byte offset of the assignment operator
     * @param int $end The exclusive byte offset of the completed expression
     * @param bool $shadowed Whether an enclosing scope declares the variable names
     */
    public function __construct(public readonly int $offset, public readonly int $end, public readonly bool $shadowed = false)
    {
        Check::input($offset >= 0 && $end >= $offset, 'A refused NAMES assignment has ordered nonnegative input positions.');
    }

    /**
     * Describes the rejected meaning independently of its displayed syntax excerpt.
     */
    public function message(): string
    {
        return ($this->shadowed ? UtilityRule::NamesShadow : UtilityRule::NamesExpression)->value;
    }
}
