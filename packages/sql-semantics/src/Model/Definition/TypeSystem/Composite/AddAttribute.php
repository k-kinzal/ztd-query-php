<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\TypeSystem\Composite;

use SqlSemantics\Model\Definition\DropBehavior;

/**
 * Adds an attribute to a composite type; CASCADE extends the typed tables that use the type.
 * @visibility public
 * @example Adding an attribute
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER TYPE pair ADD ATTRIBUTE note text CASCADE');
 *     $statement->changes[0]->attribute->name // => 'note'
 *     $statement->changes[0]->behavior // => \SqlSemantics\Model\Definition\DropBehavior::Cascade
 */
final class AddAttribute implements AttributeChange
{
    /**
     * Retains the attribute declaration and the dependent-table policy.
     */
    public function __construct(public readonly CompositeAttribute $attribute, public readonly DropBehavior $behavior = DropBehavior::Default)
    {
    }
}
