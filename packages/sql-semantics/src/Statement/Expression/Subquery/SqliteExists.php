<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Expression\Subquery;

use SqlSemantics\Statement\Declaration\Builtin;
use SqlSemantics\Statement\Declaration\Nullability;
use SqlSemantics\Statement\Declaration\TypeDescriptor;
use SqlSemantics\Statement\Expression\ColumnReference;
use SqlSemantics\Statement\Expression\ScalarExpression;
use SqlSemantics\Statement\Type\Invalid;

/**
 * A row-existence test, independent of the number or NULL values of projected columns.
 * @visibility public
 * @example Recognizing an existence test as an expression
 *     is_a(\SqlSemantics\Statement\Expression\Subquery\SqliteExists::class, \SqlSemantics\Statement\Expression\ScalarExpression::class, true) // => true
 */
final class SqliteExists implements ScalarExpression
{
    /**
     * Retains the query whose row existence is tested.
     */
    public function __construct(public readonly SqliteSubquery $source)
    {
    }

    /**
     * Every valid existence test has an integer truth value.
     */
    public function type(): TypeDescriptor|Invalid
    {
        return $this->source->invalid() ?? new TypeDescriptor(Builtin::Integer);
    }

    /**
     * Existence does not inherit projected NULLs.
     */
    public function nullability(): Nullability
    {
        return $this->source->invalid() === null ? Nullability::NotNull : Nullability::Unknown;
    }

    /**
     * @return list<ColumnReference>
     */
    public function references(): array
    {
        return $this->source->references();
    }

    /**
     * Reconstructs the existence test without selecting or evaluating a result value.
     */
    public function toString(): string
    {
        return 'EXISTS (' . $this->source->query->toString() . ')';
    }
}
