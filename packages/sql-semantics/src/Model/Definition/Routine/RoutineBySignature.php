<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine;

use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Validation\Collections;

/**
 * Selects a routine by its ordered argument declarations, including an explicit empty signature.
 * @visibility public
 * @example Inspecting the routine identity
 *     $target = new \SqlSemantics\Model\Definition\Routine\RoutineBySignature(new \SqlSemantics\Model\Relation\QualifiedName(['f']), []);
 *     $target->name->parts // => ['f']
 */
final class RoutineBySignature
{
    /**
     * @param list<RoutineParameter> $parameters
     * Retains only operands that participate in the target's identity.
     */
    public function __construct(public readonly QualifiedName $name, public readonly array $parameters)
    {
        Collections::objects($parameters, RoutineParameter::class);
    }
}
