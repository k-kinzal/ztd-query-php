<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Schema;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\Partition\PartitionKey;
use SqlSemantics\Schema\Partition\PartitionScheme;
use SqlSemantics\Schema\Partition\PartitionStrategy;

/**
 * Binds PARTITION BY: the strategy name and each key against the columns of the partitioned table.
 *
 * @visibility SqlSemantics
 */
final class PartitionSchemeBinder
{
    /**
     * Returns null when the declaration is not partitioned.
     *
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function read(Node $declaration, Scope $scope): ?PartitionScheme
    {
        $specs = array_values(array_filter(Tree::outer($declaration, ['PartitionSpec', 'columnDef', 'TableConstraint', 'TypedTableElement', 'PartitionBoundSpec']), static fn (Node $node): bool => $node->name === 'PartitionSpec'));
        return $specs === [] ? null : self::bind($specs[0], $scope);
    }

    /**
     * An unknown strategy name and a LIST scheme with several keys are impossible requests.
     *
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function bind(Node $spec, Scope $scope): PartitionScheme
    {
        $name = Tree::child($spec, ['ColId']) ?? throw new UnclassifiedSql('PARTITION BY requires its strategy.');
        $strategy = PartitionStrategy::tryFrom(strtoupper($scope->identifiers->name($name->tokens()[0]))) ?? throw new InvalidSql(InputViolation::PartitionKey, $name);
        $keys = array_map(static fn (Node $element): PartitionKey => self::key($element, $scope), Tree::outer($spec, ['part_elem']));
        try {
            return new PartitionScheme($strategy, \SqlSemantics\Model\Validation\Collections::nonEmpty($keys));
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::PartitionKey, $spec, $error);
        }
    }

    /**
     * A key names a column, calls a function, or parenthesizes an expression, then adds a collation and an operator class.
     *
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function key(Node $element, Scope $scope): PartitionKey
    {
        $collate = Tree::child($element, ['opt_collate']);
        $collation = $collate === null ? null : Tree::child($collate, ['any_name']);
        $operator = Tree::child($element, ['opt_qualified_name']);
        $operatorClass = $operator === null ? null : Tree::child($operator, ['any_name']);
        try {
            return new PartitionKey(
                self::value($element, $scope),
                $collation === null ? null : new QualifiedName($scope->identifiers->parts($collation)),
                $operatorClass === null ? null : new QualifiedName($scope->identifiers->parts($operatorClass)),
            );
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::PartitionKey, $element, $error);
        }
    }

    /**
     * Resolves a bare column name in the table scope and binds any other key as an expression.
     *
     * @throws UnclassifiedSql
     */
    public static function value(Node $element, Scope $scope): Expression
    {
        $column = Tree::child($element, ['ColId']);
        if ($column !== null) {
            return $scope->column([$scope->identifiers->name($column->tokens()[0])], $column);
        }
        $expression = Tree::child($element, ['func_expr_windowless', 'a_expr']) ?? throw new UnclassifiedSql('A partition key requires its value.');
        return (new ExpressionBinder())->bind($expression, $scope);
    }
}
