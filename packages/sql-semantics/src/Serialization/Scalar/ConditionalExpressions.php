<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Scalar;

use SqlSemantics\Model\Scalar\Conditional;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Serialization\Expressions;

/**

 * Writes alternatives with their predicate/value pairing intact. @visibility SqlSemantics

 */
final class ConditionalExpressions
{
    public static function write(Conditional\JsonMembership|Conditional\Coalesce|Conditional\NullIf|Conditional\Between|Conditional\InList|Conditional\PatternMatch|Conditional\SimpleCase|Conditional\SearchedCase $value): Tree
    {
        if ($value instanceof Conditional\JsonMembership) {
            return Build::parentheses(new Tree('json-membership', [Expressions::write($value->value), Build::keyword('MEMBER OF'), Build::parentheses(Expressions::write($value->array))]));
        }
        if ($value instanceof Conditional\Coalesce || $value instanceof Conditional\NullIf) {
            $arguments = $value instanceof Conditional\Coalesce ? $value->arguments : [$value->left, $value->right];
            return new Tree('function', [Build::keyword($value instanceof Conditional\Coalesce ? 'COALESCE' : 'NULLIF'), Build::parentheses(Build::separated(array_map(Expressions::write(...), $arguments)))]);
        }
        if ($value instanceof Conditional\SimpleCase || $value instanceof Conditional\SearchedCase) {
            $parts = [Build::keyword('CASE'), ...($value instanceof Conditional\SimpleCase ? [Expressions::write($value->value)] : [])];
            foreach ($value->branches as $branch) {
                array_push($parts, Build::keyword('WHEN'), Expressions::write($branch->test), Build::keyword('THEN'), Expressions::write($branch->result));
            }
            return new Tree('case', [...$parts, ...($value->otherwise === null ? [] : [Build::keyword('ELSE'), Expressions::write($value->otherwise)]), Build::keyword('END')]);
        }
        if ($value instanceof Conditional\Between) {
            return Build::parentheses(new Tree('between', [Expressions::write($value->value), Build::keyword(($value->negated ? 'NOT ' : '') . 'BETWEEN' . ($value->symmetric ? ' SYMMETRIC' : '')), Expressions::write($value->lower), Build::keyword('AND'), Expressions::write($value->upper)]));
        }
        if ($value instanceof Conditional\InList) {
            return Build::parentheses(new Tree('membership', [Expressions::write($value->value), Build::keyword($value->negated ? 'NOT IN' : 'IN'), Build::parentheses(Build::separated(array_map(Expressions::write(...), $value->choices)))]));
        }
        return Build::parentheses(new Tree('like', [Expressions::write($value->value), Build::keyword(($value->negated ? 'NOT ' : '') . $value->operator->value), Expressions::write($value->pattern), ...($value->escape === null ? [] : [Build::keyword('ESCAPE'), Expressions::write($value->escape)])]));
    }
}
