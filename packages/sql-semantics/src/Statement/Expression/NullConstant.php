<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Expression;

use SqlSemantics\Statement\Declaration\Nullability;
use SqlSemantics\Statement\Type\NullDomain;

/**
 * The SQL NULL constant, independent of any missing or ambiguous name lookup.
 * @visibility public
 * @example Reading a known NULL fact
 *     (new \SqlSemantics\Statement\Expression\NullConstant())->nullability() === \SqlSemantics\Statement\Declaration\Nullability::AlwaysNull // => true
 */
final class NullConstant implements ScalarExpression, \SqlSemantics\Statement\Construction\ScalarInput
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * Keyword case can determine the label of an unaliased result column.
     */
    public function __construct(public readonly string $keyword = 'NULL')
    {
        \SqlSemantics\Statement\Validation\Check::input(\SqlSemantics\Statement\Identifier\Ascii::upper($keyword) === 'NULL', 'A NULL constant can only carry the NULL keyword.');
    }

    /**
     * Leaves data type coercion to the operation consuming this known NULL value.
     */
    public function type(): NullDomain
    {
        return NullDomain::Null;
    }

    /**
     * The literal is always NULL regardless of schema declarations.
     */
    public function nullability(): Nullability
    {
        return Nullability::AlwaysNull;
    }

    /**
     * A constant has no column references to resolve.
     * @return list<ColumnReference>
     */
    public function references(): array
    {
        return [];
    }

    /**
     * Reconstructs the SQL constant.
     */
    public function toString(): string
    {
        return $this->keyword;
    }
}
