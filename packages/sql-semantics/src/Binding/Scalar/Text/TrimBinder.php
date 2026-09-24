<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Scalar\Text;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Scalar\Text\Trim;
use SqlSemantics\Model\Scalar\Text\TrimSide;
use SqlSemantics\Model\Validation\InputViolation;

/**
 * Retains the side, removed characters and source string of TRIM's keyword syntax.
 * @visibility SqlSemantics
 */
final class TrimBinder
{
    /**
     * Recognizes TRIM without intercepting a quoted or qualified function call.
     * MySQL writes the removed characters before FROM; PostgreSQL passes the argument list with the operand before FROM appended, as `btrim` does.
     * @throws InvalidSql
     */
    public static function bind(Node $source, Scope $scope): ?Trim
    {
        $first = $source->children[0] ?? null;
        if (!$first instanceof Token || !in_array($source->name, ['func_expr_common_subexpr', 'function_call_keyword'], true) || strtoupper($first->text) !== 'TRIM') {
            return null;
        }
        $keyword = $source->children[2] ?? null;
        $side = $keyword instanceof Token ? (TrimSide::tryFrom(strtoupper($keyword->text)) ?? TrimSide::Both) : TrimSide::Both;
        $list = Tree::child($source, ['trim_list']);
        $operands = Tree::outer($list ?? $source, ['a_expr', 'expr']);
        $leading = $list?->children[0] ?? null;
        if ($leading instanceof Node && $leading->name === 'a_expr') {
            $operands = [...array_slice($operands, 1), $operands[0]];
        } elseif ($list === null) {
            $operands = array_reverse($operands);
        }
        if ($operands === [] || count($operands) > 2) {
            throw new InvalidSql(InputViolation::FunctionArity, $source);
        }
        $binder = new ExpressionBinder();
        return new Trim($source, $side, isset($operands[1]) ? $binder->bind($operands[1], $scope) : null, $binder->bind($operands[0], $scope));
    }
}
