<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Expression\Conversion;

use SqlSemantics\Statement\Declaration\Nullability;
use SqlSemantics\Statement\Declaration\TypeDescriptor;
use SqlSemantics\Statement\Expression\ColumnReference;
use SqlSemantics\Statement\Expression\ScalarExpression;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\SemanticGraph;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\NullDomain;
use SqlSemantics\Statement\Type\SqliteNumericDomain;
use SqlSemantics\Statement\Type\Unresolved;

/**
 * An explicit SQLite collation choice that preserves its operand's value and storage domain.
 * @visibility public
 * @example Choosing comparison behavior independently of storage
 *     $operand = new \SqlSemantics\Statement\Expression\SqliteText(new \SqlSemantics\Statement\Literal\StringLiteral('A'));
 *     (new \SqlSemantics\Statement\Expression\Conversion\SqliteCollated($operand, new \SqlSemantics\Statement\Identifier\Name('NOCASE')))->toString() // => "('A') COLLATE NOCASE"
 */
final class SqliteCollated implements ScalarExpression
{
    /**
     * The name requests a collation from the database; analysis does not execute its comparison function.
     */
    public function __construct(public readonly ScalarExpression $operand, public readonly Name $collation)
    {
        assert((new SemanticGraph())->containsOnlyValues($operand), 'A collated operand contains only semantic values.');
    }

    /**
     * Collation changes comparison behavior without converting the stored value.
     */
    public function type(): TypeDescriptor|NullDomain|Unresolved|Invalid|SqliteNumericDomain
    {
        return $this->operand->type();
    }

    /**
     * Applying a collation does not change NULL propagation.
     */
    public function nullability(): Nullability
    {
        return $this->operand->nullability();
    }

    /**
     * Keeps the actual operand lookup objects.
     * @return list<ColumnReference>
     */
    public function references(): array
    {
        return $this->operand->references();
    }

    /**
     * Groups the operand so the collation applies at its original expression boundary.
     */
    public function toString(): string
    {
        return '(' . $this->operand->toString() . ') COLLATE ' . $this->collation->toString();
    }
}
