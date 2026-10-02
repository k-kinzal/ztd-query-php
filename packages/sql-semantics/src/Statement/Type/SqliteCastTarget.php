<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Type;

use SqlSemantics\Statement\Declaration\Affinity;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\Quote;

/**
 * A decoded SQLite conversion target; an empty target requests numeric conversion.
 * @visibility public
 * @example Applying SQLite's substring precedence
 *     (new \SqlSemantics\Statement\Type\SqliteCastTarget('FLOATING POINT'))->affinity // => \SqlSemantics\Statement\Declaration\Affinity::Integer
 */
final class SqliteCastTarget
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * The conversion policy selected by the target name, independent of column declaration rules.
     */
    public readonly Affinity $affinity;

    /**
     * SQLite does not impose a closed vocabulary or enforce size annotations for cast targets.
     */
    public function __construct(public readonly string $name)
    {
        \SqlSemantics\Statement\Validation\Check::input(!str_contains($name, "\0"), 'A type name cannot contain NUL.');
        $upper = strtoupper($name);
        $this->affinity = match (true) {
            str_contains($upper, 'INT') => Affinity::Integer,
            str_contains($upper, 'CHAR'), str_contains($upper, 'CLOB'), str_contains($upper, 'TEXT') => Affinity::Text,
            str_contains($upper, 'BLOB') => Affinity::Blob,
            str_contains($upper, 'REAL'), str_contains($upper, 'FLOA'), str_contains($upper, 'DOUB') => Affinity::Real,
            default => Affinity::Numeric,
        };
    }

    /**
     * Quotes the decoded name so it retains exactly the same conversion policy.
     */
    public function toString(): string
    {
        return (new Name($this->name, Quote::Double))->toString();
    }
}
