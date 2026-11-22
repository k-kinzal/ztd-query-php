<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Binding;

use SqlSemantics\Platform\PostgreSql\Ir\Expression\BuiltinCast;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\CastDestination;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\Compound;
use SqlSemantics\Platform\PostgreSql\Ir\Select;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;

/**
 * Checks that shared native positions carry one input type throughout a query.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class ParameterSignature
{
    /**
     * Confirms that a position keeps its type when read again.
     */
    public static function typeFor(?CastDestination $current, Parameter $parameter): CastDestination
    {
        Check::input($current === null || $current === $parameter->input, 'A PostgreSQL parameter position must have one input type.');

        return $parameter->input;
    }

    /**
     * Confirms that both operands agree on their common native positions.
     */
    public static function check(Select|Compound $left, Select|Compound $right): void
    {
        $pending = [$right, $left];
        $types = [];
        while ($pending !== []) {
            $query = array_pop($pending);
            if ($query instanceof Compound) {
                $pending[] = $query->right;
                $pending[] = $query->left;
                continue;
            }
            foreach ($query->columns as $column) {
                $expression = $column->expression;
                while ($expression instanceof BuiltinCast) {
                    $expression = $expression->operand;
                }
                if ($expression instanceof Parameter) {
                    $types[$expression->position] = self::typeFor($types[$expression->position] ?? null, $expression);
                }
            }
        }
    }
}
