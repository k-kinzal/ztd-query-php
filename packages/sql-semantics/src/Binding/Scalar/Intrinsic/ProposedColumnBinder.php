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
        return new ProposedColumn($source, $scope->column($scope->identifiers->parts($column), $column));
    }
}
