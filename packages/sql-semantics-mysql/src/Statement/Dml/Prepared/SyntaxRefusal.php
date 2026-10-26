<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Prepared;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Snapshot;

/**
 * A lexical interpretation rejected by the grammar at the initial SQL boundary.
 *
 * The bounded excerpt is diagnostic data only. No exception, parser node or
 * input buffer is retained, and the excerpt cannot be used to rebuild a statement.
 *
 * @visibility public
 * @example Retaining a diagnostic position without a parser exception
 *     (new \SqlSemantics\Platform\MySql\Statement\Dml\Prepared\SyntaxRefusal(12, 2, 'FROM'))->line // => 2
 * @example Rejecting an unbounded input excerpt
 *     new \SqlSemantics\Platform\MySql\Statement\Dml\Prepared\SyntaxRefusal(0, 1, str_repeat('a', 81)) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Rejecting a negative source offset
 *     new \SqlSemantics\Platform\MySql\Statement\Dml\Prepared\SyntaxRefusal(-1, 1, 'FROM') // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class SyntaxRefusal
{
    use Snapshot;

    /**
     * @param int $offset The rejected input's zero-based byte offset
     * @param int $line The one-based line reported by the parser
     * @param string $near At most 80 input bytes for the SQL error message
     */
    public function __construct(public readonly int $offset, public readonly int $line, public readonly string $near)
    {
        Check::input($offset >= 0 && $line > 0, 'A syntax refusal has a valid source position.');
        Check::input(strlen($near) <= 80, 'A syntax refusal retains only its bounded diagnostic excerpt.');
    }
}
