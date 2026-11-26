<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Expression;

use SqlSemantics\Platform\Sqlite\Model\Contract\TypeNameAffinity;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * The semantic type name compared by SQLite's index planner.
 *
 * @visibility public
 * @example Retaining the native spelling of an integer conversion
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Expression\CastTypeName('int'))->target()->value // => 'INTEGER'
 */
final class CastTypeName
{
    use Snapshot;

    /**
     * Checks the decoded native name within the statement profile.
     */
    public function __construct(public readonly string $value)
    {
        Check::input(!str_contains($value, "\0") && preg_match('//u', $value) === 1, 'A native CAST type name must be UTF-8 without NUL.');
        Check::input($this->payloadBytes() <= 990000000, 'A native CAST type name exceeds the SQLite statement size budget.');
    }

    /**
     * Resolves the conversion selected by the native type name.
     */
    public function target(): CastTarget
    {
        return CastTarget::from((new TypeNameAffinity())->conversion($this->value));
    }

    /**
     * Writes the type name as one SQLite type token.
     */
    public function quoted(): string
    {
        return '"' . str_replace('"', '""', $this->value) . '"';
    }

    /**
     * Counts the bytes contributed to the SQL statement.
     */
    public function payloadBytes(): int
    {
        return strlen($this->value) + substr_count($this->value, '"') + 2;
    }
}
