<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Identifier;

/**
 * A decoded identifier; quoting preserves case and separates it from keywords.
 * @visibility public
 * @example Reconstructing a quoted identifier
 *     (new \SqlSemantics\Statement\Identifier\Name('a"b', \SqlSemantics\Statement\Identifier\Quote::Double))->toString() // => '"a""b"'
 */
final class Name
{
    /**
     * Keeps the name itself, never a parser token or a fragment of SQL.
     */
    public function __construct(public readonly string $value, public readonly Quote $quote = Quote::None)
    {
        assert(!str_contains($value, "\0"), 'An identifier cannot contain NUL.');
        assert($quote !== Quote::None || ($value !== '' && preg_match('/^[A-Za-z_\x80-\xff][A-Za-z0-9_$\x80-\xff]*$/D', $value) === 1), 'A bare identifier must have an identifier spelling.');
        assert($quote !== Quote::Bracket || !str_contains($value, ']'), 'Bracket quoting cannot escape a closing bracket.');
    }

    /**
     * Writes the decoded identifier under its quoting convention.
     */
    public function toString(): string
    {
        if ($this->quote === Quote::None) {
            return $this->value;
        }
        $close = $this->quote === Quote::Bracket ? ']' : $this->quote->value;
        return $this->quote->value . str_replace($close, $close . $close, $this->value) . $close;
    }
}
