<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Expression;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\OperandColumns;
use SqlSemantics\Platform\MySql\Statement\Expression\Tuple;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

/**
 * Checks the number of columns of operands and builds the facts of truth-valued operators.
 *
 * Rule: MYSQL-OPERAND-COLUMNS-001. A single value has one column; a row
 * constructor or a subquery of several columns has its width; the width of
 * a value whose type is not known is not decided and is not checked. An
 * operator that takes single values reports a row operand; a comparison,
 * an IN test and BETWEEN report operands of different widths. The truth
 * value of a comparison, a logical operator or a predicate is an integer
 * (`Item_bool_func`, a BIGINT of display width 1). Shared rule: every
 * family that derives an operand position taking one value (the arguments
 * of built-in functions, assigned values) checks the operand's fact with
 * single(), and every family that compares rows uses comparable(), so the
 * diagnostic ER_OPERAND_COLUMNS has one source. Terminates: no recursion.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/row-subqueries.html,
 * https://dev.mysql.com/doc/refman/8.4/en/comparison-operators.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Operands
{
    /**
     * Answers the number of columns of a value, or null when its type is not decided.
     */
    public function width(ScalarFact $fact): ?int
    {
        if ($fact->type instanceof Dependent || $fact->type instanceof Invalid) {
            return null;
        }

        return $fact->type instanceof Known && $fact->type->descriptor instanceof Tuple ? $fact->type->descriptor->width : 1;
    }

    /**
     * Reports a row at a position that takes a single value; answers the fact, or for a row a value without type that names the report.
     *
     * The value the position passes on (the result of `@v := (1, 2)`) is
     * not a row any more, so an enclosing position does not report the same
     * operand again.
     */
    public function single(ScalarFact $fact, Derivation $derivation): ScalarFact
    {
        $width = $this->width($fact);
        if ($width === null || $width === 1) {
            return $fact;
        }
        $problem = new OperandColumns(1, $width);
        $derivation->report($problem);

        return new ScalarFact(new Invalid($problem), $fact->nullability);
    }

    /**
     * Reports the first operand whose known width differs from the first known width.
     *
     * @param list<ScalarFact> $facts
     */
    public function comparable(array $facts, Derivation $derivation): void
    {
        $expected = null;
        foreach ($facts as $fact) {
            $width = $this->width($fact);
            if ($width === null) {
                continue;
            }
            if ($expected !== null && $width !== $expected) {
                $derivation->report(new OperandColumns($expected, $width));

                return;
            }
            $expected = $width;
        }
    }

    /**
     * Answers the facts of a truth value: an integer with the given NULL fact.
     */
    public function truth(Nullability $nullability): ScalarFact
    {
        return new ScalarFact(new Known(new Integral(IntegralKind::BigInt)), $nullability);
    }
}
