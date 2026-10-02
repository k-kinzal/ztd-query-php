<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Expression;

use SqlSemantics\Statement\Declaration\Nullability;
use SqlSemantics\Statement\Declaration\TypeDescriptor;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\NullDomain;
use SqlSemantics\Statement\Type\SqliteChoiceDomain;
use SqlSemantics\Statement\Type\SqliteNumericDomain;
use SqlSemantics\Statement\Type\Unresolved;

/**
 * A scalar SQL expression and the facts derived from its operands and references.
 * @visibility public
 * @example Reading the scalar expression contract
 *     is_a(\SqlSemantics\Statement\Expression\ColumnReference::class, \SqlSemantics\Statement\Expression\ScalarExpression::class, true) // => true
 */
interface ScalarExpression
{
    /**
     * Returns the expression type, or the specific fact preventing its determination.
     */
    public function type(): TypeDescriptor|NullDomain|Unresolved|Invalid|SqliteNumericDomain|SqliteChoiceDomain;

    /**
     * Returns the SQL NULL fact for this expression at its lookup site.
     */
    public function nullability(): Nullability;

    /**
     * Returns the column dependencies the database must resolve for this expression.
     * @return list<ColumnReference>
     */
    public function references(): array;

    /**
     * Reconstructs SQL from the operation and its semantic operands.
     */
    public function toString(): string;
}
