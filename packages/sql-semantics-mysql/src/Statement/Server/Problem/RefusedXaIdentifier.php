<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Problem;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * An XA identifier exceeds its byte-length or release-specific numeric domain.
 *
 * The refusal retains only a diagnostic position. With a format operand,
 * MySQL quotes that operand; otherwise it quotes the following token.
 * Observed through native MySQL 5.6, 5.7, 8.0, 8.4 and 9.1 servers.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/xa-statements.html.
 *
 * @visibility public
 * @example Describing a refused identifier
 *     (new \SqlSemantics\Platform\MySql\Statement\Server\Problem\RefusedXaIdentifier(12))->message() // => 'An XA identifier exceeds its representation limits.'
 */
final class RefusedXaIdentifier implements Diagnostic
{
    use Snapshot;

    /**
     * @param int $offset The format operand's start, or the complete identifier's end
     * @param bool $following Whether the excerpt starts at the following token
     */
    public function __construct(public readonly int $offset, public readonly bool $following = false)
    {
        Check::input($offset >= 0, 'An XA refusal has a nonnegative input position.');
    }

    /**
     * Describes the domain without retaining SQL or a parser node.
     */
    public function message(): string
    {
        return 'An XA identifier exceeds its representation limits.';
    }
}
