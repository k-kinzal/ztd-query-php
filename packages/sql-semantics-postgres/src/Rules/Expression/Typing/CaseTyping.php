<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Expression\Typing;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\OperandChecks;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Unification;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Constructor\CaseExpression;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Nullability;

/**
 * Types a CASE expression.
 *
 * Rule: PG-CASE-TYPING-001. The operand, then each branch's condition and
 * result, then the ELSE result are derived in order. In a searched CASE
 * each condition must be boolean (PG-OPERAND-CHECK-001); with an operand
 * each condition is a value compared with it by `=`. The result is of the
 * common type of the results and the ELSE result (PG-UNIFICATION-001); a
 * conflict is reported. The result can be NULL when a result can, and when
 * no ELSE is written. Termination: one pass over the branches.
 * Source: https://www.postgresql.org/docs/17/functions-conditional.html#FUNCTIONS-CASE,
 * https://www.postgresql.org/docs/17/typeconv-union-case.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class CaseTyping
{
    /**
     * Derives the parts of a CASE expression and its result.
     */
    public function derive(Derivation $derivation, Environment $environment, CaseExpression $case): ScalarFact
    {
        $operand = $case->operand === null ? null : $derivation->scalar($case->operand, $environment);
        $checks = new OperandChecks();
        $results = [];
        foreach ($case->branches as $branch) {
            $condition = $derivation->scalar($branch->condition, $environment);
            if ($operand === null) {
                $checks->boolean($derivation, $condition->type, 'CASE/WHEN');
            } else {
                (new OperatorTyping())->named($derivation->context, '=', $operand->type, $condition->type);
            }
            $results[] = $derivation->scalar($branch->result, $environment);
        }
        if ($case->default !== null) {
            $results[] = $derivation->scalar($case->default, $environment);
        }
        $types = [];
        foreach ($results as $result) {
            $types[] = $result->type;
        }
        $type = (new Unification())->resolve($derivation->context, $types, 'CASE');
        if ($type instanceof Invalid && !in_array($type, $types, true)) {
            $derivation->report($type->cause);
        }
        $nullability = $checks->nullability($results);

        return new ScalarFact($type, $case->default === null ? Nullability::Nullable : $nullability);
    }
}
