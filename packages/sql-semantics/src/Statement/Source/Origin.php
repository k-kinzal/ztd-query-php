<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Source;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * The byte range of one semantic occurrence in the original parser input.
 *
 * An origin describes the input, not the SQL rendered from the semantic structure. It is kept
 * outside that structure so formatting and rebuilding a statement cannot change its meaning.
 *
 * @visibility public
 * @example Keeping a literal's input byte range
 *     $literal = new \SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral('1');
 *     $origin = new \SqlSemantics\Statement\Source\Origin($literal, 7, 1);
 *     [$origin->offset, $origin->length] // => [7, 1]
 */
final class Origin
{
    use Snapshot;

    /**
     * @param Node $node The particular occurrence that was lowered
     * @param int $offset Its first byte in the parser input
     * @param int $length The number of bytes through its last token
     */
    public function __construct(public readonly Node $node, public readonly int $offset, public readonly int $length)
    {
        Check::input($offset >= 0 && $length >= 0, 'A source range has a nonnegative byte offset and length.');
    }
}
