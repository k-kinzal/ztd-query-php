<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Transaction\Postgres;

/**
 * A global transaction identifier, distinct from an SQL identifier or a savepoint name.
 * @visibility public
 * @example Keeping case and quote bytes in a prepared transaction identifier
 *     (new \SqlSemantics\Statement\Transaction\Postgres\PreparedIdentifier("Order'A"))->toString() // => "E'Order''A'"
 */
final class PreparedIdentifier
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * Retains decoded text without PostgreSQL identifier folding or truncation.
     */
    public function __construct(public readonly string $value)
    {
        \SqlSemantics\Statement\Validation\Check::input(!str_contains($value, "\0"), 'A PostgreSQL text identifier cannot contain a zero byte.');
    }

    /**
     * PostgreSQL permits fewer than 200 bytes; a longer grammatical string remains diagnosable.
     */
    public function exceedsLength(): bool
    {
        return strlen($this->value) >= 200;
    }

    /**
     * Uses explicit escape-string syntax so backslashes do not depend on connection defaults.
     */
    public function toString(): string
    {
        return "E'" . str_replace(['\\', "'"], ['\\\\', "''"], $this->value) . "'";
    }
}
