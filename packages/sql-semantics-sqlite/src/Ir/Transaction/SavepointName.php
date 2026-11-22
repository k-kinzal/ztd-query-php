<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Transaction;

use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * The decoded label of a SQLite savepoint operation.
 *
 * @visibility public
 * @example Quoting a label with an embedded quote
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Transaction\SavepointName('a"b'))->quoted() // => '"a""b"'
 */
final class SavepointName
{
    use Snapshot;

    /**
     * Accepts a decoded label in the UTF-8 profile.
     */
    public function __construct(public readonly string $value)
    {
        Check::input(!str_contains($value, "\0") && preg_match('//u', $value) === 1, 'A savepoint name must be UTF-8 without NUL.');
    }

    /**
     * Emits the exact label as a SQLite delimited identifier.
     */
    public function quoted(): string
    {
        return '"' . str_replace('"', '""', $this->value) . '"';
    }
}
