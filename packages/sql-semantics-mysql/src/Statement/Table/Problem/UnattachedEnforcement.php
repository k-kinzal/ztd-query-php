<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Problem;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * Column enforcement has no immediately preceding CHECK or enforcement chain.
 *
 * @visibility public
 * @example Retaining only the refusal position
 *     (new \SqlSemantics\Platform\MySql\Statement\Table\Problem\UnattachedEnforcement(24))->offset // => 24
 */
final class UnattachedEnforcement implements Diagnostic
{
    use Snapshot;

    /**
     * @param int $offset The byte position at which the enforcement cannot attach
     */
    public function __construct(public readonly int $offset)
    {
        Check::input($offset >= 0, 'An enforcement refusal has a non-negative source position.');
    }

    /**
     * Describes the structural relationship missing from the attribute sequence.
     */
    public function message(): string
    {
        return 'Column enforcement follows a CHECK constraint or its enforcement chain.';
    }
}
