<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Schema;

use SqlSemantics\Ast\Declaration\IndexDefinition as ParsedIndex;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\ExpressionRules;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Model\Definition\IndexDeclaration;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Scalar\Reference\ColumnReference;
use SqlSemantics\Model\Scalar\Reference\UnresolvedColumnReference;
use SqlSemantics\Schema\Index;
use SqlSemantics\Schema\IndexDefinition;
use SqlSemantics\Schema\IndexElement;

/**
 * Binds index keys and predicates against their destination table.
 *
 * @visibility SqlSemantics
 */
final class IndexBinder
{
    /**
     * Returns the semantic index declaration for a statement.
     */
    public static function bind(ParsedIndex|IndexDefinition $index, Scope $scope): IndexDeclaration
    {
        $definition = $index instanceof ParsedIndex ? self::definition($index, $scope) : $index;
        return new IndexDeclaration($definition);
    }

    /**
     * Binds every key and the optional partial-index predicate.
     */
    public static function definition(ParsedIndex $index, Scope $scope): IndexDefinition
    {
        $predicate = $index->predicate === null ? null : (new ExpressionBinder())->bind($index->predicate, $scope);
        if ($predicate !== null) {
            (new ExpressionRules($scope->identifiers->dialect, $scope->diagnostics()))->predicate($predicate);
        }
        foreach ($index->include as $column) {
            $scope->column([$column], $index->source);
        }
        return new IndexDefinition($index->schema, $index->name, $index->table, array_map(static fn ($element): IndexElement => self::element($element, $scope), $index->elements), $index->unique, $index->method, $index->include, $predicate, $index->source, IndexPropertiesBinder::bind($index, $scope));
    }

    /**
     * Selects a simple column key or a computed expression key.
     * @throws UnclassifiedSql
     */
    public static function element(\SqlSemantics\Ast\Declaration\IndexElement $element, Scope $scope): IndexElement
    {
        $direction = $element->direction === null ? null : Index\Direction::from($element->direction);
        $nulls = $element->nulls === null ? null : Index\NullOrder::from($element->nulls);
        $collation = $element->collation === [] ? null : new QualifiedName($element->collation);
        $operatorClass = $element->operatorClass === [] ? null : new QualifiedName($element->operatorClass);
        $parameters = StorageParameters::read($element->source, $scope);
        if ($element->column !== null) {
            $column = $scope->column([$element->column], $element->source);
            if (!$column instanceof ColumnReference && !$column instanceof UnresolvedColumnReference) {
                throw new UnclassifiedSql('An index column must resolve to a column reference.');
            }
            return new Index\ColumnKey($column, $element->prefixLength, $direction, $nulls, $collation, $operatorClass, $parameters, $element->source);
        }
        $expression = (new ExpressionBinder())->bind($element->expression ?? throw new UnclassifiedSql('An index expression requires its value.'), $scope);
        return new Index\ExpressionKey($expression, $direction, $nulls, $collation, $operatorClass, $parameters, $element->source);
    }
}
