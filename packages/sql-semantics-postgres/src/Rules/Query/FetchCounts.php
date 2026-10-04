<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Query;

use SqlSemantics\Platform\PostgreSql\Rules\Expression\Precedence;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\UnaryOperation;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NumericConstant;
use SqlSemantics\Statement\Scalar;

/**
 * Tells which expressions can be written as the count of FETCH FIRST and as the start of OFFSET … ROWS.
 *
 * Rule: PG-FETCH-COUNT-001. The grammar admits a primary expression
 * (`c_expr`) or a numeric constant with a sign there; a plus sign is
 * dropped by the server, a minus sign makes the constant negative.
 * Source: https://www.postgresql.org/docs/17/sql-select.html#SQL-LIMIT.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class FetchCounts
{
    /**
     * Tells whether an expression can stand in the count position.
     */
    public function admits(Scalar $count): bool
    {
        if ((new Precedence())->primary($count)) {
            return true;
        }

        return $count instanceof UnaryOperation && $count->operator->name->value === '-' && !$count->operator->explicit && $count->operator->qualifiers === []
            && $count->operand instanceof Constant && ($count->operand->value instanceof IntegerConstant || $count->operand->value instanceof NumericConstant);
    }
}
