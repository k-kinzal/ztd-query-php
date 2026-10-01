<?php

declare(strict_types=1);

namespace SqlSemantics\Semantic;

/**
 * One decoded identifier, with an optional SQL quoting delimiter.
 * @example Reading semantic relationships
 *     $name = new \SqlSemantics\Semantic\Name('a"b', '"');
 *     $name->toString() // => '"a""b"'
 *
 * @visibility public
 */
final class Name
{
    /**
     * Constructs the value and asserts the relationships required by its fields.
     */
    public function __construct(public readonly string $value, public readonly string $quote = '')
    {
        assert($value !== '' && !str_contains($value, "\0"), 'An identifier must be nonempty and cannot contain NUL.');
        assert(in_array($quote, ['', '"', '`', '[', "'"], true), 'Invalid identifier delimiter.');
        assert($quote !== '' || preg_match('/^[\p{L}_][\p{L}\p{N}_$]*$/uD', $value) === 1, 'This identifier requires quoting.');
    }

    /**
     * Reconstructs SQL from the semantic values without consulting source syntax.
     */
    public function toString(): string
    {
        if ($this->quote === '') {
            return $this->value;
        }
        $close = $this->quote === '[' ? ']' : $this->quote;
        return $this->quote . str_replace($close, $close . $close, $this->value) . $close;
    }
}
