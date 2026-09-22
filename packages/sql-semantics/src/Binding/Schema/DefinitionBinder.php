<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Schema;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Model\Definition\TableDeclaration;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\TableUse;

/**
 * Binds declaration expressions against the newly declared table's column namespace.
 *
 * @visibility SqlSemantics
 */
final class DefinitionBinder
{
    /**
     * Retains default, generated, and CHECK roles instead of flattening them into arguments.
     */
    public function bind(TableUse $target, Scope $scope): TableDeclaration
    {
        return new TableDeclaration($target->declaration);
    }

    /**
     * Extracts a parsed value expression without treating the DEFAULT attribute as an operator.
     */
    public function expression(Node $source, Scope $scope): Expression
    {
        $expression = Tree::outer($source, ['a_expr', 'expr', 'signed', 'literal', 'signed_literal', 'now'])[0] ?? null;
        if ($expression !== null) {
            return (new ExpressionBinder())->bind($expression, $scope);
        }
        $tokens = $source->tokens();
        $default = array_search('DEFAULT', array_map(static fn ($token): string => strtoupper($token->text), $tokens), true);
        if ($default !== false) {
            $tokens = array_slice($tokens, $default + 1);
            if ($scope->identifiers->dialect === \SqlSemantics\Dialect::Sqlite && count($tokens) === 1 && in_array($tokens[0]->name, ['ID', 'INDEXED'], true)) {
                $literal = Expression::literal($scope->identifiers->name($tokens[0]), $scope->identifiers->dialect);
                return new \SqlSemantics\Model\Scalar\Value\Literal($literal->facts, $source, \SqlSemantics\Model\Scalar\Value\LiteralKind::Text, $literal->spelling() ?? '');
            }
        }
        return (new ExpressionBinder())->bind(new Node('declaration_value', 0, $tokens), $scope);
    }
}
