<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine;

use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Validation\Collections;

/**
 * Selects an ordered-set aggregate with separate direct and aggregated argument declarations.
 * @visibility public
 * @example Inspecting the routine identity
 *     $target = new \SqlSemantics\Model\Definition\Routine\OrderedSetAggregate(new \SqlSemantics\Model\Relation\QualifiedName(['f']), [], [new \SqlSemantics\Model\Definition\Routine\AggregateParameter(\SqlSemantics\Type\TypeDescriptor::builtin(\SqlSemantics\Dialect::PostgreSql, 'integer'))]);
 *     $target->name->parts // => ['f']
 * @example Rejecting a missing aggregated input list
 *     new \SqlSemantics\Model\Definition\Routine\OrderedSetAggregate(new \SqlSemantics\Model\Relation\QualifiedName(['f']), [], []); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class OrderedSetAggregate
{
    /**
     * @param list<AggregateParameter> $direct
     * @param non-empty-list<AggregateParameter> $ordered
     * Retains only operands that participate in the target's identity.
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(public readonly QualifiedName $name, public readonly array $direct, public readonly array $ordered)
    {
        \SqlSemantics\Model\Definition\Catalog\CatalogInvariant::name($name, 3);
        Collections::objects($direct, AggregateParameter::class);
        Collections::objects(Collections::nonEmpty($ordered), AggregateParameter::class);
        $last = $direct === [] ? null : $direct[count($direct) - 1];
        if ($last?->mode === AggregateInputMode::Variadic && (count($ordered) !== 1 || $ordered[0]->mode !== AggregateInputMode::Variadic || $last->setOf !== $ordered[0]->setOf || !ArgumentTypeInvariant::same($last->type, $ordered[0]->type))) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('A variadic direct argument requires one variadic aggregated argument of the same declared type.');
        }
    }
}
