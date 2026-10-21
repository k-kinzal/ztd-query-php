<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Problem;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A system scope keyword applied to a lexically bound local assignment.
 *
 * Optional positions format the input diagnostic. No SQL or AST is retained.
 *
 * @visibility public
 * @example Describing the conflicting scopes
 *     (new \SqlSemantics\Platform\MySql\Statement\Routine\Problem\ScopedLocalAssignment())->message() // => 'A local variable assignment cannot carry a system variable scope.'
 */
final class ScopedLocalAssignment implements Diagnostic
{
    use Snapshot;

    /**
     * @param int|null $offset The original assigned-name position, absent for direct construction
     * @param int|null $end The completed assignment boundary used by MySQL 5.6
     */
    public function __construct(public readonly ?int $offset = null, public readonly ?int $end = null)
    {
        Check::input($offset === null || $offset >= 0, 'The assigned-name position is nonnegative.');
        Check::input($end === null || ($offset !== null && $end >= $offset), 'An assignment completes after its assigned name.');
    }

    /**
     * Describes the contradictory lexical binding.
     */
    public function message(): string
    {
        return 'A local variable assignment cannot carry a system variable scope.';
    }
}
