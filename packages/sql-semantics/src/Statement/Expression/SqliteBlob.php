<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Expression;

use SqlSemantics\Statement\Declaration\Builtin;
use SqlSemantics\Statement\Declaration\Nullability;
use SqlSemantics\Statement\Declaration\TypeDescriptor;
use SqlSemantics\Statement\Literal\BinaryLiteral;

/**
 * A SQLite binary literal whose decoded bytes and hexadecimal digits must agree.
 * @visibility public
 * @example Reconstructing binary bytes
 *     (new \SqlSemantics\Statement\Expression\SqliteBlob(new \SqlSemantics\Statement\Literal\BinaryLiteral("\0A")))->toString() // => "X'0041'"
 */
final class SqliteBlob implements ScalarExpression, \SqlSemantics\Statement\Construction\ScalarInput
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * Hexadecimal letter case also contributes to an unaliased expression's output name.
     */
    public readonly string $digits;

    /**
     * The optional digit spelling may vary in case, but cannot represent different bytes.
     */
    public function __construct(public readonly BinaryLiteral $value, public readonly bool $lowercasePrefix = false, ?string $digits = null)
    {
        $hex = bin2hex($value->value);
        \SqlSemantics\Statement\Validation\Check::input($digits === null || strtolower($digits) === $hex, 'Hexadecimal digits must encode exactly the supplied bytes.');
        $this->digits = $digits ?? $hex;
    }

    /**
     * The literal's storage class is binary, independently of character encoding.
     */
    public function type(): TypeDescriptor
    {
        return new TypeDescriptor(Builtin::Blob);
    }

    /**
     * Even a zero-length BLOB is a non-NULL value.
     */
    public function nullability(): Nullability
    {
        return Nullability::NotNull;
    }

    /**
     * A literal does not depend on a column declaration.
     * @return list<ColumnReference>
     */
    public function references(): array
    {
        return [];
    }

    /**
     * Reconstructs the typed binary literal while preserving its output-name spelling.
     */
    public function toString(): string
    {
        return ($this->lowercasePrefix ? 'x' : 'X') . "'" . $this->digits . "'";
    }
}
