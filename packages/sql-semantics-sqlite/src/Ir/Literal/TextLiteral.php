<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Literal;

use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * A decoded UTF-8 SQLite TEXT constant.
 *
 * @visibility public
 * @example Holding the value of a single-quoted SQL literal
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Literal\TextLiteral("it's"))->value // => "it's"
 */
final class TextLiteral
{
    use Snapshot;

    /**
     * Checks decoded text for the UTF-8 SQL profile.
     */
    public function __construct(public readonly string $value)
    {
        Check::input(!str_contains($value, "\0") && preg_match('//u', $value) === 1, 'TEXT literal requires valid UTF-8 without NUL.');
    }
}
