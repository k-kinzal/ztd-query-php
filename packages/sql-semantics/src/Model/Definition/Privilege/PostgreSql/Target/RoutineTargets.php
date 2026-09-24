<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Privilege\PostgreSql\Target;

use SqlSemantics\Model\Definition\Routine\RoutineByName;
use SqlSemantics\Model\Definition\Routine\RoutineBySignature;
use SqlSemantics\Model\Validation\Collections;

/**
 * Routines selected by name or by typed signature.
 * @visibility public
 * @example Reading the selection
 *     $targets = new \SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\RoutineTargets(\SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\RoutineClass::Function, [new \SqlSemantics\Model\Definition\Routine\RoutineByName(new \SqlSemantics\Model\Relation\QualifiedName(['f']))]);
 *     $targets->routines[0]->name->parts // => ['f']
 */
final class RoutineTargets
{
    /**
     * @param non-empty-list<RoutineByName|RoutineBySignature> $routines Ordered routine identities
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(public readonly RoutineClass $class, public readonly array $routines)
    {
        Collections::alternatives(Collections::nonEmpty($routines), [RoutineByName::class, RoutineBySignature::class]);
    }
}
