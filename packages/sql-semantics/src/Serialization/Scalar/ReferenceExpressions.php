<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Scalar;

use SqlSemantics\Model\Scalar\Reference;
use SqlSemantics\Model\Sql\Atom;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Serialization\Expressions;

/**

 * Writes binding keys and access paths, preserving expression boundaries. @visibility SqlSemantics

 */
final class ReferenceExpressions
{
    /**
     * Writes resolved or unresolved access paths with explicit identifier boundaries.
     */
    public static function write(Reference\TriggerColumn|Reference\ColumnReference|Reference\UnresolvedColumnReference|Reference\Wildcard|Reference\Parameter|Reference\CursorPosition|Reference\FieldAccess|Reference\ElementAccess|Reference\SliceAccess|Reference\VariableReference|Reference\UnresolvedVariableReference|Reference\VariableAssignment|Reference\JsonPathExtraction|Reference\ProposedColumn|\SqlSemantics\Model\Scalar\Composite\RowExpansion $value): Tree
    {
        $dialect = $value->type->dialect;
        return match (true) {
            $value instanceof Reference\TriggerColumn => Build::identifier($value->referenceParts(), $dialect),
            $value instanceof Reference\ColumnReference,
            $value instanceof Reference\UnresolvedColumnReference => Build::identifier($value->name, $dialect),
            $value instanceof Reference\Wildcard => new Tree('wildcard', [...($value->qualifier === [] ? [] : [Build::identifier($value->qualifier, $dialect), Build::keyword('.')]), Build::keyword('*')]),
            $value instanceof \SqlSemantics\Model\Scalar\Composite\RowExpansion => new Tree('row-expansion', [Build::parentheses(Expressions::write($value->composite)), Build::keyword('.'), Build::keyword('*')]),
            $value instanceof Reference\Parameter => new Tree('parameter', [new Atom('parameter', $value->name)]),
            $value instanceof Reference\CursorPosition => new Tree('cursor', [Build::keyword('CURRENT OF'), Build::identifier($value->cursor, $dialect)]),
            $value instanceof Reference\FieldAccess => new Tree('field', [Build::parentheses(Expressions::write($value->base)), Build::keyword('.'), Build::identifier([$value->field], $dialect)]),
            $value instanceof Reference\ElementAccess => new Tree('element', [self::subscripted($value->base), Build::keyword('['), Expressions::write($value->index), Build::keyword(']')]),
            $value instanceof Reference\SliceAccess => new Tree('slice', [self::subscripted($value->base), Build::keyword('['), ...($value->lower === null ? [] : [Expressions::write($value->lower)]), Build::keyword(':'), ...($value->upper === null ? [] : [Expressions::write($value->upper)]), Build::keyword(']')]),
            $value instanceof Reference\VariableReference => self::variable($value->definition->name, $value->definition->scope, $dialect),
            $value instanceof Reference\UnresolvedVariableReference => self::variable($value->name, $value->scope, $dialect),
            $value instanceof Reference\ProposedColumn => new Tree('proposed', [Build::keyword('VALUES'), Build::parentheses(Expressions::write($value->column))]),
            $value instanceof Reference\JsonPathExtraction => Build::parentheses(new Tree('json-path', [Expressions::write($value->column), Build::keyword($value->spelling()), Expressions::write($value->path)])),
            $value instanceof Reference\VariableAssignment => Build::parentheses(new Tree('assign', [self::write($value->target), Build::keyword(':='), Expressions::write($value->value)])),
        };
    }

    /**
     * Writes a subscripted base; anything but a column path or another subscript needs parentheses before brackets.
     */
    public static function subscripted(\SqlSemantics\Model\Expression $base): Tree
    {
        $bare = $base instanceof Reference\ColumnReference || $base instanceof Reference\UnresolvedColumnReference || $base instanceof Reference\TriggerColumn || $base instanceof Reference\ElementAccess || $base instanceof Reference\SliceAccess || $base instanceof Reference\Parameter;
        return $bare ? Expressions::write($base) : Build::parentheses(Expressions::write($base));
    }

    /**
     * Writes a quoted variable name with its user, session, or global scope prefix.
     */
    public static function variable(string $name, \SqlSemantics\Schema\VariableScope $scope, \SqlSemantics\Dialect $dialect): Tree
    {
        $prefix = $scope === \SqlSemantics\Schema\VariableScope::User ? '@' : '@@' . ($scope === \SqlSemantics\Schema\VariableScope::Global ? 'GLOBAL.' : 'SESSION.');
        return new Tree('variable', [new Atom('variable', $prefix . Build::identifier([$name], $dialect)->toString())]);
    }
}
