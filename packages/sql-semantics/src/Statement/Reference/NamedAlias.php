<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Reference;

use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Projection\Field;
use SqlSemantics\Statement\Projection\Fields;

/**
 * A named projection dependency, including one reached from a correlated query.
 * @visibility public
 * @example Identifying a projection dependency without inventing a schema column
 *     is_a(\SqlSemantics\Statement\Reference\NamedAlias::class, \SqlSemantics\Statement\Expression\ScalarExpression::class, true) // => false
 */
final class NamedAlias
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * Keeps the exact output field and the namespace in which its alias is declared.
     */
    public function __construct(public readonly Fields $projection, public readonly Field $field, public readonly Name $name)
    {
        \SqlSemantics\Statement\Validation\Check::input(in_array($field, $projection->items, true), 'A projection reference must retain its actual field.');
        \SqlSemantics\Statement\Validation\Check::input($field->alias !== null && $projection->scope->catalog->columnNames->equal($field->alias->value, $name->value), 'The referenced field must declare the requested alias.');
    }
}
