<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Handler;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * The input position of a subquery outside HANDLER's expression domain.
 *
 * Only the diagnostic excerpt boundary is retained, without SQL or parser AST.
 *
 * @visibility public
 * @example Describing a refused handler operand
 *     (new \SqlSemantics\Platform\MySql\Statement\Dml\Handler\RefusedSubquery(26))->message() // => 'A HANDLER read cannot contain a subquery.'
 */
final class RefusedSubquery implements Diagnostic
{
    use Snapshot;

    /**
     * @param int $offset The nonnegative byte position where the syntax excerpt starts
     */
    public function __construct(public readonly int $offset)
    {
        Check::input($offset >= 0, 'A refused handler subquery begins at a nonnegative byte offset.');
    }

    /**
     * Describes the command's expression domain.
     */
    public function message(): string
    {
        return 'A HANDLER read cannot contain a subquery.';
    }
}
