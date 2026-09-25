<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Scalar;

use SqlSemantics\Model\Scalar\Text\CharacterCodes;
use SqlSemantics\Model\Scalar\Text\FullTextSearch;
use SqlSemantics\Model\Scalar\Text\InternalWeightString;
use SqlSemantics\Model\Scalar\Text\Normalization;
use SqlSemantics\Model\Scalar\Text\NormalizedPredicate;
use SqlSemantics\Model\Scalar\Text\Position;
use SqlSemantics\Model\Scalar\Text\Trim;
use SqlSemantics\Model\Scalar\Text\WeightLevel;
use SqlSemantics\Model\Scalar\Text\WeightString;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Serialization\Expressions;

/**
 * Writes string operations from their named semantic operands.
 * @visibility SqlSemantics
 */
final class TextExpressions
{
    /**
     * Writes a substring search with the needle before the searched input, a trim in its FROM form, a Unicode normalization or its test with the normal form, or a full-text search.
     */
    public static function write(Position|Trim|Normalization|NormalizedPredicate|FullTextSearch $value): Tree
    {
        if ($value instanceof Normalization) {
            return new Tree('normalize', [Build::keyword('NORMALIZE'), Build::parentheses(Build::separated([Expressions::write($value->string), Build::keyword($value->form->value)]))]);
        }
        if ($value instanceof NormalizedPredicate) {
            return Build::parentheses(new Tree('is-normalized', [Build::parentheses(Expressions::write($value->string)), Build::keyword($value->spelling())]));
        }
        if ($value instanceof Trim) {
            return new Tree('trim', [Build::keyword('TRIM'), Build::parentheses(new Tree('removal', [Build::keyword($value->side->value), ...($value->characters === null ? [] : [Expressions::write($value->characters)]), Build::keyword('FROM'), Expressions::write($value->string)]))]);
        }
        if ($value instanceof FullTextSearch) {
            return new Tree('full-text-search', [
                Build::keyword('MATCH'),
                Build::parentheses(Build::separated(array_map(Expressions::write(...), $value->columns))),
                Build::keyword('AGAINST'),
                Build::parentheses(new Tree('search', [Expressions::write($value->query), Build::keyword($value->mode->value)])),
            ]);
        }
        return new Tree('position', [Build::keyword('POSITION'), Build::parentheses(new Tree('search', [Expressions::write($value->needle), Build::keyword('IN'), Expressions::write($value->haystack)]))]);
    }

    /**
     * Writes MySQL CHAR(... USING ...) and the forms of WEIGHT_STRING from their operands.
     */
    public static function codes(CharacterCodes|WeightString|InternalWeightString $value): Tree
    {
        $identifier = static fn (string $name): Tree => Build::identifier([$name], \SqlSemantics\Dialect::MySql);
        $inner = match (true) {
            $value instanceof CharacterCodes => [Build::separated(array_map(Expressions::write(...), $value->codes)), ...($value->characterSet === null ? [] : [Build::keyword('USING'), $identifier($value->characterSet)])],
            $value instanceof InternalWeightString => [Build::separated([Expressions::write($value->operand), Build::keyword((string) $value->resultLength), Build::keyword((string) $value->codepoints), Build::keyword((string) $value->flags)])],
            default => [
                Expressions::write($value->operand),
                ...($value->padding === null ? [] : [Build::keyword('AS ' . ($value->padding->binary ? 'BINARY' : 'CHAR') . '(' . $value->padding->length . ')')]),
                ...($value->levels === [] ? [] : [Build::keyword('LEVEL'), Build::separated(array_map(static fn (WeightLevel $level): Tree => Build::keyword($level->first === $level->last ? $level->first . ($level->descending ? ' DESC' : '') . ($level->reverse ? ' REVERSE' : '') : $level->first . '-' . $level->last), $value->levels))]),
            ],
        };
        return new Tree('code-function', [Build::keyword($value->spelling()), Build::parentheses(new Tree('arguments', $inner))]);
    }
}
