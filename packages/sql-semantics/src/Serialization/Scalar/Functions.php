<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Scalar;

use SqlSemantics\Model\Scalar\Function;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Parts;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Serialization\Expressions;

/**

 * Writes invocation arguments, aggregate filters, and windows from typed fields. @visibility SqlSemantics

 */
final class Functions
{
    public static function write(Function\FunctionCall|Function\AggregateCall|Function\AllRowsAggregate|Function\OrderedSetCall|Function\WindowCall $value): Tree
    {
        if ($value instanceof Function\WindowCall) {
            return new Tree('window-call', [self::write($value->function), Build::keyword('OVER'), Windows::write($value->window, $value->type->dialect)]);
        }
        $name = $value->function->name()->parts;
        $function = count($name) === 1 && preg_match('/^[a-zA-Z_][a-zA-Z_0-9]*$/D', $name[0]) === 1 ? Build::keyword($name[0]) : Build::identifier($name, $value->type->dialect);
        if ($value instanceof Function\AllRowsAggregate) {
            $call = new Tree('all-rows', [$function, Build::parentheses(Build::keyword('*'))]);
        } elseif ($value instanceof Function\OrderedSetCall) {
            $call = new Tree('ordered-set', [$function, Build::parentheses(Build::separated(array_map(Expressions::write(...), $value->directArguments))), Build::keyword('WITHIN GROUP'), Build::parentheses(Parts::ordering($value->withinGroup))]);
        } else {
            $arguments = [Build::separated(array_map(Expressions::write(...), $value->arguments))];
            if ($value instanceof Function\AggregateCall) {
                $arguments = [...($value->mode === Function\ArgumentMode::Distinct || ($value->function instanceof Function\UnresolvedFunction && ($value->arguments !== [] || $value->type->dialect === \SqlSemantics\Dialect::Sqlite)) ? [Build::keyword($value->mode->value)] : []), ...$arguments, Parts::ordering($value->orderBy)];
            }
            $call = new Tree('call', [$function, Build::parentheses(new Tree('arguments', $arguments))]);
        }
        $filter = $value instanceof Function\FunctionCall ? null : $value->filter;
        return new Tree('invocation', [$call, ...($filter === null ? [] : [Build::keyword('FILTER'), Build::parentheses(new Tree('filter', [Build::keyword('WHERE'), Expressions::write($filter)]))])]);
    }
}
