<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine;

use SqlSemantics\Model\Relation\QualifiedName;

/**
 * Selects the unique routine of this name without specifying its argument list.
 * @visibility public
 * @example Inspecting the routine identity
 *     $target = new \SqlSemantics\Model\Definition\Routine\RoutineByName(new \SqlSemantics\Model\Relation\QualifiedName(['f']));
 *     $target->name->parts // => ['f']
 */
final class RoutineByName
{
    /**
     * Retains only operands that participate in the target's identity.
     */
    public function __construct(public readonly QualifiedName $name)
    {

    }
}
