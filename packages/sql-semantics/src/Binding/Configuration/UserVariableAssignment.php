<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Configuration;

use SqlParser\Parser\Node;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Model\Configuration\AssignedUserVariable;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Scalar\Reference;
use SqlSemantics\Schema\VariableScope;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

/**
 * Resolves a writable variable location without assigning or fetching its value.
 * @visibility SqlSemantics
 */
final class UserVariableAssignment
{
    /**
     * An undeclared assignment target is a named location that this operation can create.
     */
    public static function bind(string $name, Expression $value, Node $source, Scope $scope): AssignedUserVariable
    {
        foreach ($scope->queries?->tables->schema->variables ?? [] as $definition) {
            if ($definition->scope === VariableScope::User && strcasecmp($definition->name, $name) === 0) {
                return new AssignedUserVariable(new Reference\VariableReference(new ExpressionFacts($definition->type, $definition->nullability), $source, $definition), $value, $source);
            }
        }
        $reference = new Reference\UnresolvedVariableReference(new ExpressionFacts(TypeDescriptor::builtin($scope->identifiers->dialect, 'unknown'), Nullability::Unknown), $source, $name, VariableScope::User);
        return new AssignedUserVariable($reference, $value, $source);
    }
}
