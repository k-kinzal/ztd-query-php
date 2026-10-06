<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Call;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Expression\Operands;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;

/**
 * Derives an operand of a function-like expression, which takes one value.
 *
 * Rule: MYSQL-CALL-ARGUMENT-001. Every argument of a function, an aggregate,
 * a window function and every ordering or partitioning expression of a
 * window is a single value: a row of several values there is the error
 * ER_OPERAND_COLUMNS, reported by the expression family's operand rule.
 * Terminates: one derivation of a strict part.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/row-subqueries.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Arguments
{
    /**
     * Derives one operand and reports a row.
     */
    public function one(Scalar $operand, Derivation $derivation, Environment $environment): ScalarFact
    {
        return (new Operands())->single($derivation->scalar($operand, $environment), $derivation);
    }
}
