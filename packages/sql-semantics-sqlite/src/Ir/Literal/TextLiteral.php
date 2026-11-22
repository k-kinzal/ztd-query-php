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
        Check::input(preg_match('//u', $value) === 1, 'TEXT literal requires valid UTF-8.');
    }

    /**
     * Emits the same TEXT bytes with the absence of expression affinity.
     */
    public function toString(): string
    {
        return str_contains($this->value, "\0")
            ? "(+CAST(X'" . bin2hex($this->value) . "' AS TEXT))"
            : "'" . str_replace("'", "''", $this->value) . "'";
    }

    /**
     * Measures the variable syntax payload admitted by the containing statement.
     */
    public function payloadBytes(): int
    {
        $length = strlen($this->value);

        return str_contains($this->value, "\0") ? 2 * $length : $length + substr_count($this->value, "'");
    }
}
