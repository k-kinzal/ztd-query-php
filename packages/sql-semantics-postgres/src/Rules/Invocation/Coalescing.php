<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Invocation;

use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Type\Nullability;
use SqlSemantics\Statement\Type\NullOnly;

/**
 * The NULL rule of the expressions that return NULL only when every operand is NULL.
 *
 * Rule: PG-COALESCING-NULL-001. COALESCE returns its first operand that is
 * not NULL; GREATEST and LEAST ignore NULL operands and return NULL only
 * when all operands are NULL; XMLCONCAT and XMLFOREST omit NULL values in
 * the same way. The result is not NULL when an operand is known not NULL,
 * NULL-able when every operand is, and otherwise depends on the operands.
 * Source: https://www.postgresql.org/docs/17/functions-conditional.html,
 * https://www.postgresql.org/docs/17/functions-xml.html. Termination: one pass. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class Coalescing
{
    /**
     * Answers the NULL fact of the result from the facts of the operands.
     *
     * @param list<ScalarFact> $operands
     */
    public function nullability(array $operands): Nullability
    {
        $dependent = false;
        foreach ($operands as $operand) {
            if ($operand->nullability === Nullability::NotNull && !$operand->type instanceof NullOnly) {
                return Nullability::NotNull;
            }
            $dependent = $dependent || $operand->nullability === Nullability::Dependent;
        }

        return $dependent ? Nullability::Dependent : Nullability::Nullable;
    }
}
