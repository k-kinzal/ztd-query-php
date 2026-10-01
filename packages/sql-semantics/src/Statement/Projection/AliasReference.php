<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Projection;

use SqlSemantics\Statement\Declaration\Nullability;
use SqlSemantics\Statement\Declaration\TypeDescriptor;
use SqlSemantics\Statement\Expression\ColumnReference;
use SqlSemantics\Statement\Expression\ScalarExpression;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\NullDomain;
use SqlSemantics\Statement\Type\SqliteNumericDomain;
use SqlSemantics\Statement\Type\Unresolved;

/**
 * A resolved SQLite expression alias retaining the exact projected field it names.
 * @visibility public
 * @example Reading the target expression's facts through its alias
 *     $scope = new \SqlSemantics\Statement\Relation\Scope(new \SqlSemantics\Statement\Schema\Catalog(new \SqlSemantics\Statement\Schema\SearchPath(new \SqlSemantics\Statement\Identifier\Name('main'))));
 *     $field = new \SqlSemantics\Statement\Projection\Field(new \SqlSemantics\Statement\Expression\NullConstant(), new \SqlSemantics\Statement\Identifier\Name('answer'));
 *     $fields = new \SqlSemantics\Statement\Projection\Fields($scope, $field);
 *     (new \SqlSemantics\Statement\Projection\AliasReference($fields, $field, new \SqlSemantics\Statement\Identifier\Name('answer')))->type() // => \SqlSemantics\Statement\Type\NullDomain::Null
 */
final class AliasReference implements ScalarExpression
{
    /**
     * The target must be an actual field of this projection and carry the referenced alias.
     */
    public function __construct(public readonly Fields $projection, public readonly Field $field, public readonly Name $name)
    {
        assert(in_array($field, $projection->items, true), 'An alias must reference a field of its actual projection.');
        assert($field->alias !== null && $projection->scope->catalog->columnNames->equal($field->alias->value, $name->value), 'An alias lookup must name the referenced field.');
    }

    /**
     * Alias substitution preserves the target expression's result domain.
     */
    public function type(): TypeDescriptor|NullDomain|Unresolved|Invalid|SqliteNumericDomain
    {
        return $this->field->expression->type();
    }

    /**
     * The NULL fact belongs to the target expression, not to a fictitious input column.
     */
    public function nullability(): Nullability
    {
        return $this->field->expression->nullability();
    }

    /**
     * Exposes the actual table-column dependencies behind the projection alias.
     * @return list<ColumnReference>
     */
    public function references(): array
    {
        return $this->field->expression->references();
    }

    /**
     * Writes SQLite's substituted expression so generated output labels cannot capture the alias.
     */
    public function toString(): string
    {
        return '(' . $this->field->expression->toString() . ')';
    }
}
