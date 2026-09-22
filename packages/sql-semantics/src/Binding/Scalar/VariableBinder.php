<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Scalar;

use SqlParser\Parser\Node;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Scalar\Reference;
use SqlSemantics\Schema\VariableScope;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

/**

 * Resolves a variable's declared binding location without reading or assigning a value. @visibility SqlSemantics

 */
final class VariableBinder
{
    public function bind(Node $node, Scope $scope): Reference\VariableReference|Reference\UnresolvedVariableReference
    {
        $tokens = $node->tokens();
        $system = isset($tokens[1]) && $tokens[1]->text === '@';
        $position = $system ? 2 : 1;
        $namespace = $system ? VariableScope::Session : VariableScope::User;
        if ($system && isset($tokens[$position + 1]) && $tokens[$position + 1]->text === '.' && in_array(strtoupper($tokens[$position]->text), ['GLOBAL', 'SESSION', 'LOCAL'], true)) {
            $namespace = strtoupper($tokens[$position]->text) === 'GLOBAL' ? VariableScope::Global : VariableScope::Session;
            $position += 2;
        }
        $name = implode('.', array_map(static fn ($token): string => $scope->identifiers->name($token), array_values(array_filter(array_slice($tokens, $position), static fn ($token): bool => $token->text !== '.'))));
        foreach ($scope->queries?->tables->schema->variables ?? [] as $definition) {
            if ($definition->scope === $namespace && strcasecmp($definition->name, $name) === 0) {
                return new Reference\VariableReference(new ExpressionFacts($definition->type, $definition->nullability), $node, $definition);
            }
        }
        $scope->diagnostics()->report('unknown-variable', 'Cannot resolve variable: ' . $name, $node);
        return new Reference\UnresolvedVariableReference(new ExpressionFacts(TypeDescriptor::builtin($scope->identifiers->dialect, 'unknown'), Nullability::Unknown), $node, $name, $namespace);
    }
    /**
     * Binds a variable reference or assignment at its expression boundary.
     */
    public function expression(Node $node, Scope $scope): ?\SqlSemantics\Model\Expression
    {
        if (in_array($node->name, ['rvalue_system_or_user_variable', 'rvalue_system_variable'], true) || ($scope->identifiers->dialect === \SqlSemantics\Dialect::MySql && ($node->children[0] ?? null) instanceof \SqlParser\Lexer\Token && $node->children[0]->text === '@')) {
            $assignment = \SqlSemantics\Ast\Tree::child($node, ['expr']);
            if ($assignment !== null && in_array(':=', array_column($node->tokens(), 'text'), true)) {
                $tokens = $node->tokens();
                $position = array_search(':=', array_column($tokens, 'text'), true);
                if ($position === false) {
                    \SqlSemantics\Ast\Tree::invalid($node, 'variable assignment');
                }
                $target = $this->bind(new Node('variable', 0, array_slice($tokens, 0, $position)), $scope);
                $value = (new \SqlSemantics\Binding\ExpressionBinder())->bind($assignment, $scope);
                return new Reference\VariableAssignment($value->facts, $node, $target, $value);
            }
            return $this->bind($node, $scope);
        }
        return null;
    }
}
