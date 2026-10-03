<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Projection;

use SqlSemantics\Statement\Declaration\Nullability;
use SqlSemantics\Statement\Expression\ColumnReference;
use SqlSemantics\Statement\Expression\ScalarExpression;
use SqlSemantics\Statement\Reference\CandidateColumn;
use SqlSemantics\Statement\Type\Unresolved;

/**
 * A lookup whose input-column priority cannot be decided without an absent declaration.
 * @visibility public
 * @example Recognizing a conditional alias lookup as a scalar expression
 *     is_a(\SqlSemantics\Statement\Projection\ColumnOrAlias::class, \SqlSemantics\Statement\Expression\ScalarExpression::class, true) // => true
 */
final class ColumnOrAlias implements ScalarExpression
{
    /**
     * Both alternatives must belong to the same input scope; a known missing column is already decided.
     */
    public function __construct(public readonly ColumnReference $column, public readonly AliasReference $alias)
    {
        assert($column->resolution instanceof CandidateColumn, 'A conditional alias requires an unavailable column declaration.');
        assert($column->qualifier === null, 'A qualified lookup cannot name a projection alias.');
        assert($column->scope === $alias->projection->scope, 'Both lookup alternatives must have the same input scope.');
        assert($column->scope->catalog->columnNames->equal($column->name->value, $alias->name->value), 'Both alternatives must answer the same name.');
    }

    /**
     * The missing declaration determines whether the input column or projected expression wins.
     */
    public function type(): Unresolved
    {
        return Unresolved::MissingDeclaration;
    }

    /**
     * No NULL guarantee can be made for the unavailable input-column alternative.
     */
    public function nullability(): Nullability
    {
        return Nullability::Unknown;
    }

    /**
     * Keeps dependencies from both possible interpretations without choosing one.
     * @return list<ColumnReference>
     */
    public function references(): array
    {
        return [$this->column, ...$this->alias->references()];
    }

    /**
     * Retains the lookup so the database can apply its actual declaration context.
     */
    public function toString(): string
    {
        return $this->column->toString();
    }
}
