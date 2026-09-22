<?php

declare(strict_types=1);

namespace SqlSemantics\Schema;

use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

/**

 * A declared variable's binding key and static facts, independent of any runtime value. @visibility public

 */
final class VariableDefinition
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly string $name, public readonly VariableScope $scope, public readonly TypeDescriptor $type, public readonly Nullability $nullability = Nullability::Unknown)
    {
        if ($name === '') {
            throw new InvalidStructure('A variable requires a name.');
        }
    }
}
