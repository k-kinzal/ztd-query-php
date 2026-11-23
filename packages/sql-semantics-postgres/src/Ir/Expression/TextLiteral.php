<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Expression;

use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * A decoded string with the PostgreSQL text type.
 *
 * @visibility public
 * @example Preserving an apostrophe in a text value
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Expression\TextLiteral("it's here"))->value // => "it's here"
 */
final class TextLiteral
{
    use Snapshot;

    /**
     * Validates the bytes that PostgreSQL text can store in the UTF-8 profile.
     */
    public function __construct(public readonly string $value)
    {
        Check::input(!str_contains($value, "\0") && preg_match('//u', $value) === 1, 'A PostgreSQL text value requires valid UTF-8 without a zero byte.');
    }

    /**
     * Emits a native text constant under standard string syntax.
     */
    public function toString(): string
    {
        return "CAST('" . str_replace("'", "''", $this->value) . "' AS pg_catalog.text)";
    }
}
