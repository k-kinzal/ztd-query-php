<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine;

use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Validation\Collections;

/**
 * Selects an aggregate by a nonempty list of aggregated input declarations.
 * @visibility public
 * @example Inspecting the routine identity
 *     $target = new \SqlSemantics\Model\Definition\Routine\OrdinaryAggregate(new \SqlSemantics\Model\Relation\QualifiedName(['f']), [new \SqlSemantics\Model\Definition\Routine\AggregateParameter(\SqlSemantics\Type\TypeDescriptor::builtin(\SqlSemantics\Dialect::PostgreSql, 'integer'))]);
 *     $target->name->parts // => ['f']
 * @example Rejecting a missing aggregated input list
 *     new \SqlSemantics\Model\Definition\Routine\OrdinaryAggregate(new \SqlSemantics\Model\Relation\QualifiedName(['f']), []); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class OrdinaryAggregate
{
    /**
     * @param non-empty-list<AggregateParameter> $parameters
     * Retains only operands that participate in the target's identity; the name has at most catalog, schema and aggregate.
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(public readonly QualifiedName $name, public readonly array $parameters)
    {
        \SqlSemantics\Model\Definition\Catalog\CatalogInvariant::name($name, 3);
        Collections::objects(Collections::nonEmpty($parameters), AggregateParameter::class);
    }
}
