<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Scalar\Intrinsic;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Scalar\Reference\ProposedColumn;

/**
 * Binds MySQL `VALUES(column)` to the column whose proposed insert value it reads.
 * @visibility SqlSemantics
 */
final class ProposedColumnBinder
{
    /**
     * Recognizes VALUES followed by a parenthesized column name.
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public static function bind(Node $source, Scope $scope): ?ProposedColumn
    {
        $first = $source->children[0] ?? null;
        $column = Tree::child($source, ['simple_ident_nospvar']);
        if ($scope->identifiers->dialect !== Dialect::MySql || !$first instanceof Token || strtoupper($first->text) !== 'VALUES' || $column === null) {
            return null;
        }
        return new ProposedColumn($source, self::destinations($scope)->column($scope->identifiers->parts($column), $column));
    }

    /**
     * Names a destination column: a proposed row named by a MySQL row alias is not searched.
     */
    public static function destinations(Scope $scope): Scope
    {
        $relations = array_values(array_filter($scope->relations, static fn (\SqlSemantics\Model\TableUse $relation): bool => !$relation instanceof \SqlSemantics\Model\Relation\ProposedRow));
        return count($relations) === count($scope->relations) ? $scope : new Scope($scope->identifiers, $relations, $scope->extensions, $scope->parent, $scope->queries, $scope->merged, $scope->outputs, $scope->detached);
    }
}
