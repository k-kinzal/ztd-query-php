<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Schema;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\ExpressionRules;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Write\AssignmentRules;
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
        $defaults = [];
        $generated = [];
        foreach ($target->declaration->columns as $column) {
            if ($column->defaultExpression === null && $column->generatedExpression === null) {
                continue;
            }
            $destination = $scope->column([$target->declaration->name, $column->name], $column->source);
            if ($column->defaultExpression !== null) {
                $defaults[$column->name] = $this->expression($column->defaultExpression, $scope);
                (new AssignmentRules())->check($destination, $defaults[$column->name], $scope, false);
            }
            if ($column->generatedExpression !== null) {
                $generated[$column->name] = $this->expression($column->generatedExpression, $scope);
                (new AssignmentRules())->check($destination, $generated[$column->name], $scope, false);
            }
        }
        $checks = [];
        foreach ($target->declaration->constraints as $index => $constraint) {
            if ($constraint->expression !== null) {
                $checks[$index] = (new ExpressionBinder())->bind($constraint->expression, $scope);
                (new ExpressionRules($scope->identifiers->dialect, $scope->diagnostics()))->predicate($checks[$index]);
            }
        }
        return new TableDeclaration($target->declaration, $defaults, $generated, $checks);
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
        if (strtoupper($tokens[0]->text ?? '') === 'DEFAULT') {
            array_shift($tokens);
        }
        return (new ExpressionBinder())->bind(new Node('declaration_value', 0, $tokens), $scope);
    }
}
