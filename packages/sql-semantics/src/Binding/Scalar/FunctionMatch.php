<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Scalar;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Schema\FunctionSignature;
use SqlSemantics\Type\TypeDescriptor;

/**
 * Matches overload arguments without pretending unresolved types are known.
 *
 * @visibility SqlSemantics
 */
final class FunctionMatch
{
    /**
     * Checks required, optional, and repeated argument positions.
     */
    public static function arity(FunctionSignature $signature, int $count): bool
    {
        return $signature->parameters === null || ($count >= count($signature->parameters) - $signature->optionalParameters && ($signature->variadic || $count <= count($signature->parameters)));
    }

    /**

     * Returns the declared type of one argument, including a repeated final parameter.

     */
    public static function parameter(FunctionSignature $signature, int $index): ?TypeDescriptor
    {
        $parameters = $signature->parameters ?? [];
        return $parameters[$index] ?? ($signature->variadic ? $parameters[count($parameters) - 1] : null);
    }

    /**

     * @param list<Expression> $arguments

     */
    public static function score(FunctionSignature $signature, array $arguments): ?int
    {
        if (!self::arity($signature, count($arguments))) {
            return null;
        }
        $score = $signature->parameters === null ? 0 : 1;
        foreach ($arguments as $index => $argument) {
            $expected = self::parameter($signature, $index);
            if ($expected === null || $expected->name === 'unknown') {
                continue;
            }
            $actual = $argument->type;
            if ($actual->name === $expected->name) {
                $score += 100;
            } elseif ($actual->name === 'unknown') {
                $score += 1;
            } elseif ($actual->dialect !== Dialect::PostgreSql || self::compatible($actual->name, $expected->name)) {
                $score += 10;
            } else {
                return null;
            }
        }
        return $score;
    }

    /**

     * Recognizes widening numeric and character conversions for overload selection.

     */
    public static function compatible(string $actual, string $expected): bool
    {
        $numeric = ['smallint', 'integer', 'bigint', 'numeric', 'real', 'double precision'];
        $from = array_search($actual, $numeric, true);
        $to = array_search($expected, $numeric, true);
        return ($from !== false && $to !== false && $from <= $to) || (in_array($actual, ['char', 'varchar', 'text'], true) && in_array($expected, ['char', 'varchar', 'text'], true));
    }
}
