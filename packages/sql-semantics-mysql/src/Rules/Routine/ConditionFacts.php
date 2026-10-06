<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Routine;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Condition;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\ConditionName;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\InformationItem;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\ErrorCode;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\GeneralCondition;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\SignalItem;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\SqlState;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;

/**
 * Derives condition values, SIGNAL and RESIGNAL, and the targets of GET DIAGNOSTICS.
 *
 * Rule: MYSQL-PROGRAM-CONDITIONS-001. An SQLSTATE value is five digits or
 * upper-case letters and does not begin with `00`, which means success
 * (ER_SP_BAD_SQLSTATE); an error code is not 0 (ER_WRONG_VALUE). Two
 * condition values are the same when they are the same general condition,
 * the same error number or the same SQLSTATE value; a condition name stands
 * for the value it is declared for. A condition name must be declared around the
 * statement (ER_SP_COND_MISMATCH); SIGNAL and RESIGNAL accept only a name
 * declared for an SQLSTATE value (ER_SIGNAL_BAD_CONDITION_TYPE). Each
 * condition information item is set at most once (ER_DUP_SIGNAL_SET); its
 * value is derived in the scope of the statement, where a name is a
 * parameter or local variable. A target of GET DIAGNOSTICS is a user
 * variable or a parameter or local variable in scope
 * (ER_SP_UNDECLARED_VAR). Terminates: the lists are finite.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/declare-condition.html,
 * https://dev.mysql.com/doc/refman/8.4/en/signal.html,
 * https://dev.mysql.com/doc/refman/8.4/en/get-diagnostics.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ConditionFacts
{
    /**
     * Checks a condition value: the form of an SQLSTATE value, the declaration of a condition name.
     */
    public function value(Condition $value, Derivation $derivation, ProgramScope $scope): void
    {
        if ($value instanceof ErrorCode && !$value->valid()) {
            $derivation->report(new ProgramProblem(ProgramRule::ZeroErrorCode));
        }
        if ($value instanceof SqlState && !$value->valid()) {
            $derivation->report(new ProgramProblem(ProgramRule::BadSqlState, $value->state->value));
        }
        if ($value instanceof ConditionName && $scope->condition($value->name) === null) {
            $derivation->report(new ProgramProblem(ProgramRule::UndefinedCondition, $value->name->value));
        }
    }

    /**
     * Answers the value a condition stands for: the declared value of a condition name, null for an undeclared name.
     */
    public function meaning(Condition $value, ProgramScope $scope): ?Condition
    {
        return $value instanceof ConditionName ? $scope->condition($value->name)?->value : $value;
    }

    /**
     * Tells whether two condition values, names already replaced by their meaning, are the same.
     */
    public function same(Condition $left, Condition $right): bool
    {
        return match (true) {
            $left instanceof GeneralCondition && $right instanceof GeneralCondition => $left->class === $right->class,
            $left instanceof ErrorCode && $right instanceof ErrorCode => $left->number() === $right->number(),
            $left instanceof SqlState && $right instanceof SqlState => $left->state->value === $right->state->value,
            default => false,
        };
    }

    /**
     * Derives the condition and the information items of SIGNAL or RESIGNAL.
     *
     * @param list<SignalItem> $items
     */
    public function signal(ConditionName|SqlState|null $condition, array $items, Derivation $derivation, ProgramScope $scope): void
    {
        if ($condition !== null) {
            $this->value($condition, $derivation, $scope);
        }
        if ($condition instanceof ConditionName && $scope->condition($condition->name)?->value instanceof ErrorCode) {
            $derivation->report(new ProgramProblem(ProgramRule::SignalConditionKind));
        }
        $seen = [];
        foreach ($items as $item) {
            if (in_array($item->name, $seen, true)) {
                $derivation->report(new ProgramProblem(ProgramRule::DuplicateSignalItem, $item->name->value));
            }
            $seen[] = $item->name;
            $derivation->scalar($item->value, $scope->environment);
        }
    }

    /**
     * Derives the targets of GET DIAGNOSTICS.
     *
     * @param list<InformationItem> $items
     */
    public function targets(array $items, Derivation $derivation, ProgramScope $scope): void
    {
        foreach ($items as $item) {
            if ($item->target instanceof UserVariable) {
                $derivation->scalar($item->target, $scope->environment);
            } else {
                (new CursorFacts())->variables([$item->target], $derivation, $scope);
            }
        }
    }
}
