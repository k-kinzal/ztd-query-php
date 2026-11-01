<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Source;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Snapshot;

/**
 * The byte range belonging to a SQL element in its original input.
 *
 * A span carries no text and cannot supply SQL to a renderer or executor.
 *
 * @visibility public
 * @example Inspecting a byte range without retaining SQL text
 *     $span = new \SqlSemantics\Statement\Source\Span(12, 4);
 *     [$span->offset, $span->length] // => [12, 4]
 */
final class Span
{
    use Snapshot;

    /**
     * @param int $offset The first byte of the element
     * @param int $length The number of bytes through its last token
     */
    public function __construct(public readonly int $offset, public readonly int $length)
    {
        Check::input($offset >= 0 && $length >= 0, 'A source span has a nonnegative offset and length.');
        Check::input($offset <= PHP_INT_MAX - $length, 'The end of a source span fits an integer.');
    }
}
