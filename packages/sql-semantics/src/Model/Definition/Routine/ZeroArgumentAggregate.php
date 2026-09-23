<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine;

use SqlSemantics\Model\Relation\QualifiedName;

/**
 * Selects an aggregate without arguments, represented by the SQL star signature.
 * @visibility public
 * @example Inspecting the routine identity
 *     $target = new \SqlSemantics\Model\Definition\Routine\ZeroArgumentAggregate(new \SqlSemantics\Model\Relation\QualifiedName(['f']));
 *     $target->name->parts // => ['f']
 */
final class ZeroArgumentAggregate
{
    /**
     * Retains only operands that participate in the target's identity.
     */
    public function __construct(public readonly QualifiedName $name)
    {

    }
}
