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
    public static function write(Value\ContextReference|Value\Literal|Value\ConfigurationIdentifier|Value\ConfigurationKeyword|Value\RowExpression $value): Tree
    {
        return match (true) {
            $value instanceof Value\ContextReference => self::context($value),
            $value instanceof Value\Literal => new Tree('literal', [new Atom('literal', $value->text)]),
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
        $call = $value->type->dialect === \SqlSemantics\Dialect::MySql && in_array($value->request, [Value\ContextValueKind::User, Value\ContextValueKind::SessionUser, Value\ContextValueKind::SystemUser, Value\ContextValueKind::CurrentRole], true);
        return new Tree('context', [Build::keyword($value->request->value), ...($value->precision === null ? ($call ? [Build::parentheses(new Tree('arguments', []))] : []) : [Build::parentheses(Build::keyword((string) $value->precision))])]);
    }
}
