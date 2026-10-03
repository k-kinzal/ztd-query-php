<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Expression;

use SqlSemantics\Statement\Declaration\Builtin;
use SqlSemantics\Statement\Declaration\Nullability;
use SqlSemantics\Statement\Declaration\TypeDescriptor;

/**
 * A request for a UTC clock component when the database evaluates the statement.
 * @visibility public
 * @example Keeping clock evaluation in the database
 *     \SqlSemantics\Statement\Expression\SqliteCurrentTime::Timestamp->toString() // => 'CURRENT_TIMESTAMP'
 */
enum SqliteCurrentTime: string implements ScalarExpression
{
    case Date = 'CURRENT_DATE';
    case Time = 'CURRENT_TIME';
    case Timestamp = 'CURRENT_TIMESTAMP';

    /**
     * SQLite represents these UTC components as text.
     */
    public function type(): TypeDescriptor
    {
        return new TypeDescriptor(Builtin::Text);
    }

    /**
     * A clock request does not produce SQL NULL.
     */
    public function nullability(): Nullability
    {
        return Nullability::NotNull;
    }

    /**
     * No schema declaration supplies the clock value.
     * @return list<ColumnReference>
     */
    public function references(): array
    {
        return [];
    }

    /**
     * Reconstructs the request without sampling or simulating the clock.
     */
    public function toString(): string
    {
        return $this->value;
    }
}
