<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Expression\Subquery;

use SqlSemantics\Statement\Declaration\Nullability;
use SqlSemantics\Statement\Expression\ColumnReference;
use SqlSemantics\Statement\Expression\ScalarExpression;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\NullDomain;
use SqlSemantics\Statement\Type\SqliteChoiceDomain;

/**
 * SQLite's first-row scalar selection, returning NULL when its query produces no row.
 * @visibility public
 * @example Recognizing a scalar query as an expression
 *     is_a(\SqlSemantics\Statement\Expression\Subquery\SqliteScalarSubquery::class, \SqlSemantics\Statement\Expression\ScalarExpression::class, true) // => true
 */
final class SqliteScalarSubquery implements ScalarExpression
{
    /**
     * Keeps the query's cardinality rule distinct from EXISTS and membership.
     */
    public function __construct(public readonly SqliteSubquery $source)
    {
    }

    /**
     * A scalar query requires one output; an absent first row contributes the NULL domain.
     */
    public function type(): NullDomain|Invalid|SqliteChoiceDomain
    {
        $invalid = $this->source->invalid();
        if ($invalid !== null) {
            return $invalid;
        }
        if ($this->source->width() !== 1) {
            return Invalid::ScalarSubqueryWidth;
        }
        $type = $this->source->outputs()[0]->type();
        return $type instanceof Invalid || $type instanceof NullDomain ? $type : new SqliteChoiceDomain($type, NullDomain::Null);
    }

    /**
     * Empty query results can be NULL even when the projected declaration is NOT NULL.
     */
    public function nullability(): Nullability
    {
        $type = $this->type();
        return $type instanceof Invalid ? Nullability::Unknown : ($type instanceof NullDomain ? Nullability::AlwaysNull : Nullability::MaybeNull);
    }

    /**
     * @return list<ColumnReference>
     */
    public function references(): array
    {
        return $this->source->references();
    }

    /**
     * Reconstructs the scalar query from its semantic relation.
     */
    public function toString(): string
    {
        return '(' . $this->source->query->toString() . ')';
    }
}
