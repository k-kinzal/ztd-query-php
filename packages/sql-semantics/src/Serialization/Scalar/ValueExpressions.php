<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Scalar;

use SqlSemantics\Model\Scalar\Value;
use SqlSemantics\Model\Sql\Atom;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Serialization\Expressions;

/**

 * Writes literal categories and row constructors. @visibility SqlSemantics

 */
final class ValueExpressions
{
    /**
     * Writes literal, context-request, and row-constructor operands.
     */
    public static function write(Value\ContextReference|Value\Literal|Value\ConfigurationIdentifier|Value\ConfigurationKeyword|Value\RowExpression|Value\IntroducedLiteral|Value\TemporalLiteral|Value\ArrayConstructor $value): Tree
    {
        return match (true) {
            $value instanceof Value\ContextReference => self::context($value),
            $value instanceof Value\Literal => new Tree('literal', [new Atom('literal', $value->text)]),
            $value instanceof Value\TemporalLiteral => new Tree('temporal', [Build::keyword(strtoupper($value->category->value)), new Atom('literal', $value->text->text)]),
            $value instanceof Value\IntroducedLiteral => new Tree('introduced', [Build::keyword('_' . $value->characterSet), new Atom('literal', $value->literal->text)]),
            $value instanceof Value\ArrayConstructor => new Tree('array', [Build::keyword('ARRAY'), new Tree('elements', [Build::keyword('['), Build::separated(array_map(Expressions::write(...), $value->elements)), Build::keyword(']')])]),
            $value instanceof Value\ConfigurationIdentifier => Build::identifier($value->name, $value->type->dialect),
            $value instanceof Value\ConfigurationKeyword => Build::keyword($value->keyword->value),
            $value instanceof Value\RowExpression => new Tree('row', [...($value->type->dialect === \SqlSemantics\Dialect::PostgreSql ? [Build::keyword('ROW')] : []), Build::parentheses(Build::separated(array_map(Expressions::write(...), $value->items)))]),
        };
    }

    /**
     * Keeps the dialect's required call spelling for session information requests.
     */
    public static function context(Value\ContextReference $value): Tree
    {
        $call = $value->type->dialect === \SqlSemantics\Dialect::MySql && in_array($value->request, [Value\ContextValueKind::User, Value\ContextValueKind::SessionUser, Value\ContextValueKind::SystemUser, Value\ContextValueKind::CurrentRole, Value\ContextValueKind::StatementTime], true);
        return new Tree('context', [Build::keyword($value->request->value), ...($value->precision === null ? ($call ? [Build::parentheses(new Tree('arguments', []))] : []) : [Build::parentheses(Build::keyword((string) $value->precision))])]);
    }
}
