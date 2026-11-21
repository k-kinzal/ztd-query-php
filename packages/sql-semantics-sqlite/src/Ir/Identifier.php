<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir;

use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * A decoded SQLite identifier or result name.
 *
 * @visibility public
 * @example Quoting a name that contains a quote
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Identifier('a"b'))->quoted() // => '"a""b"'
 */
final class Identifier
{
    use Snapshot;

    /**
     * Checks the decoded name against the profile's identifier bounds.
     */
    public function __construct(public readonly string $value)
    {
        Check::input(
            strlen($value) <= 255 && !str_contains($value, "\0") && preg_match('//u', $value) === 1,
            'An IR name must be UTF-8, without NUL, and at most 255 bytes.',
        );
    }

    /**
     * Quotes this name in a position that accepts a SQLite identifier.
     */
    public function quoted(): string
    {
        return '"' . str_replace('"', '""', $this->value) . '"';
    }

    /**
     * Returns the name key under SQLite identifier comparison.
     */
    public function key(): string
    {
        return Comparison::AsciiInsensitive->fold($this->value);
    }
}
