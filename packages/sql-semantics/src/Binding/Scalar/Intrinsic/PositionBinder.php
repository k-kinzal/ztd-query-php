<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Scalar\Intrinsic;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Scalar\Text\Position;
use SqlSemantics\Model\Validation\InputViolation;

/**
 * Retains the needle and haystack roles of POSITION's keyword syntax.
 * @visibility SqlSemantics
 */
final class PositionBinder
{
    /**
     * Recognizes POSITION without intercepting a quoted or qualified function call.
     * @throws InvalidSql
     */
    public static function bind(Node $source, Scope $scope): ?Position
    {
        $first = $source->children[0] ?? null;
        if (!$first instanceof Token || !in_array($source->name, ['func_expr_common_subexpr', 'function_call_nonkeyword'], true) || strtoupper($first->text) !== 'POSITION') {
            return null;
        }
        $operands = Tree::outer($source, ['a_expr', 'b_expr', 'bit_expr', 'expr']);
        if (count($operands) !== 2) {
            throw new InvalidSql(InputViolation::FunctionArity, $source);
        }
        $binder = new ExpressionBinder();
        return new Position($source, $binder->bind($operands[0], $scope), $binder->bind($operands[1], $scope));
    }
}
