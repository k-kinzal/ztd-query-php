<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Problem;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A completed internal generated-column parser entry submitted by a client.
 *
 * MySQL 5.7 reads its operands, then refuses the entry before resolving them.
 * This diagnostic retains the keyword position, without a statement or parser node.
 * Observed through SQL on MySQL 5.7.44.
 *
 * @visibility public
 * @example Describing the refused entry
 *     (new \SqlSemantics\Platform\MySql\Statement\Utility\Problem\GeneratedColumnEntry(0))->message() // => 'PARSE_GCOL_EXPR is not a client statement.'
 */
final class GeneratedColumnEntry implements Diagnostic
{
    use Snapshot;

    /**
     * @param int $offset The input byte position of PARSE_GCOL_EXPR
     */
    public function __construct(public readonly int $offset)
    {
        Check::input($offset >= 0, 'A generated-column entry begins at a nonnegative byte offset.');
    }

    /**
     * Describes the client entry refusal without retaining input SQL.
     */
    public function message(): string
    {
        return 'PARSE_GCOL_EXPR is not a client statement.';
    }
}
