<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Scalar\Intrinsic;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Scalar\Temporal\Extract;
use SqlSemantics\Model\Scalar\Temporal\MySqlUnit;
use SqlSemantics\Model\Validation\InputViolation;

/**
 * Binds EXTRACT's unit separately from its temporal operand.
 * @visibility SqlSemantics
 */
final class ExtractBinder
{
    /**
     * Recognizes the language operation without intercepting a quoted function name.
     * @throws InvalidSql
     */
    public static function bind(Node $source, Scope $scope): ?Extract
    {
        $first = $source->children[0] ?? null;
        if (!$first instanceof Token || !in_array($source->name, ['func_expr_common_subexpr', 'function_call_nonkeyword'], true) || strtoupper($first->text) !== 'EXTRACT') {
            return null;
        }
        $fieldNode = Tree::outer($source, ['extract_arg', 'interval'])[0] ?? null;
        $valueNode = Tree::outer($source, ['a_expr', 'expr'])[0] ?? null;
        if ($fieldNode === null || $valueNode === null) {
            throw new InvalidSql(InputViolation::FunctionArity, $source);
        }
        $token = $fieldNode->tokens()[0];
        $name = FieldSpelling::read($token, $scope->identifiers);
        $field = $scope->identifiers->dialect === Dialect::PostgreSql ? ExtractionField::postgres($name) : MySqlUnit::spelled($name);
        if ($field === null) {
            throw new InvalidSql(InputViolation::ExtractionField, $fieldNode);
        }
        return new Extract($source, $field, (new ExpressionBinder())->bind($valueNode, $scope));
    }
}
