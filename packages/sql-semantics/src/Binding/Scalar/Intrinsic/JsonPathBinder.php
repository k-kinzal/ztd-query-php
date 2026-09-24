<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Scalar\Intrinsic;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Scalar\Reference\JsonPathExtraction;
use SqlSemantics\Model\Scalar\Value\Literal;

/**
 * Retains the column and the path literal of MySQL's inline JSON path operators.
 * @visibility SqlSemantics
 */
final class JsonPathBinder
{
    /**
     * Recognizes `column->'path'` and `column->>'path'`.
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public static function bind(Node $source, Scope $scope): ?JsonPathExtraction
    {
        $operator = $source->children[1] ?? null;
        $column = Tree::child($source, ['simple_ident']);
        $path = Tree::child($source, ['TEXT_STRING_literal']);
        if ($scope->identifiers->dialect !== Dialect::MySql || !$operator instanceof Token || !in_array($operator->text, ['->', '->>'], true) || $column === null || $path === null) {
            return null;
        }
        $literal = (new ExpressionBinder())->bind($path, $scope);
        if (!$literal instanceof Literal) {
            return null;
        }
        return new JsonPathExtraction($source, (new ExpressionBinder())->bind($column, $scope), $literal, $operator->text === '->>');
    }
}
