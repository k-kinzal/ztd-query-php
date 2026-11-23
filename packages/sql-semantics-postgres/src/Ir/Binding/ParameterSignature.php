<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Binding;

use SqlSemantics\Platform\PostgreSql\Ir\Expression\BuiltinCast;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\CastDestination;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\Compound;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\LocalProjection;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\Values\ValuesQuery;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\With\WithProjection;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\With\WithRead;
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
    public static function check(Select|ValuesQuery|Compound $left, Select|ValuesQuery|Compound $right): void
    {
        self::checkQueries([$right, $left]);
    }

    /**
     * Confirms a common input type for every position in the owning query.
     * @param list<Select|Compound|LocalProjection|ValuesQuery|WithProjection> $pending Independent query members
     */
    public static function checkQueries(array $pending): void
    {
        $types = [];
        while ($pending !== []) {
            $query = array_pop($pending);
            if ($query instanceof Compound) {
                $pending[] = $query->right;
                $pending[] = $query->left;
                continue;
            }
            if ($query instanceof LocalProjection) {
                $pending[] = $query->definition;
                continue;
            }
            if ($query instanceof WithProjection) {
                if (!$query->body instanceof WithRead) {
                    $pending[] = $query->body;
                }
                foreach ($query->definitions as $definition) {
                    if (!$definition->body instanceof WithRead) {
                        $pending[] = $definition->body;
                    }
                }
                continue;
            }
            if ($query instanceof ValuesQuery) {
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
