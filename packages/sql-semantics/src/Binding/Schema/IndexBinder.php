<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Schema;

use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\ExpressionRules;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Model\Definition\IndexDeclaration;
use SqlSemantics\Schema\IndexDefinition;

/**
 * Binds index keys and predicates against their destination table.
 *
 * @visibility SqlSemantics
 */
final class IndexBinder
{
    /**
     * Retains expression dependencies and validates partial-index predicates.
     */
    public static function bind(IndexDefinition $index, Scope $scope): IndexDeclaration
    {
        $keys = [];
        foreach ($index->elements as $element) {
            $keys[] = $element->column === null ? (new ExpressionBinder())->bind($element->expression ?? $element->source, $scope) : $scope->column([$element->column], $element->source);
        }
        $predicate = $index->predicate === null ? null : (new ExpressionBinder())->bind($index->predicate, $scope);
        if ($predicate !== null) {
            (new ExpressionRules($scope->identifiers->dialect, $scope->diagnostics()))->predicate($predicate);
        }
        foreach ($index->include as $column) {
            $scope->column([$column], $index->source);
        }
        return new IndexDeclaration($index, $keys, $predicate);
    }
}
