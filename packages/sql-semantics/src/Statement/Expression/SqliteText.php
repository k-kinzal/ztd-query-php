<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Expression;

use SqlSemantics\Statement\Declaration\Builtin;
use SqlSemantics\Statement\Declaration\Nullability;
use SqlSemantics\Statement\Declaration\TypeDescriptor;
use SqlSemantics\Statement\Literal\StringLiteral;

/**
 * A SQLite string literal with decoded text and a known non-NULL text result.
 * @visibility public
 * @example Reconstructing a decoded string
 *     (new \SqlSemantics\Statement\Expression\SqliteText(new \SqlSemantics\Statement\Literal\StringLiteral("a'b")))->toString() // => "'a''b'"
 */
final class SqliteText implements ScalarExpression, \SqlSemantics\Statement\Construction\ScalarInput
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * The literal form has neither a character-set introducer nor an embedded NUL byte.
     */
    public function __construct(public readonly StringLiteral $value)
    {
        \SqlSemantics\Statement\Validation\Check::input($value->characterSet === null, 'This string literal does not have a character-set introducer.');
        \SqlSemantics\Statement\Validation\Check::input(!str_contains($value->value, "\0"), 'A SQL string literal cannot contain a NUL byte.');
    }

    /**
     * A string literal has the text storage class, independently of table declarations.
     */
    public function type(): TypeDescriptor
    {
        return new TypeDescriptor(Builtin::Text);
    }

    /**
     * Even an empty string is a non-NULL text value.
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
     * Escapes apostrophes without interpreting backslashes as escape sequences.
     */
    public function toString(): string
    {
        return "'" . str_replace("'", "''", $this->value->value) . "'";
    }
}
