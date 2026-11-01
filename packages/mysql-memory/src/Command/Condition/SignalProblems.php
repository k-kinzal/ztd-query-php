<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Condition;

use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Error\ProgramErrors;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Walker;
use MySqlMemory\Session\Problem\Errors;
use MySqlMemory\Session\Session;
use MySqlMemory\Value\Temporal;
use SqlSemantics\Platform\MySql\Statement\Literal\TemporalForm;
use SqlSemantics\Platform\MySql\Statement\Literal\TemporalLiteral;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Resignal;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Signal;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule;
use SqlSemantics\Platform\MySql\Statement\Statement as MySqlStatement;
use SqlSemantics\Platform\MySql\Statement\Variable\Problem\UnknownSystemVariable;
use SqlSemantics\Platform\MySql\Statement\Variable\SystemVariable;
use SqlSemantics\Statement\Node;

/**
 * Raises the problems the server finds while it parses SIGNAL and RESIGNAL, in the order they are written.
 *
 * The condition comes first: a condition name no handler scope declares (ER_SP_COND_MISMATCH),
 * an SQLSTATE of the wrong form or of class '00' (ER_SP_BAD_SQLSTATE), or a condition that is
 * not an SQLSTATE. Then each information item in turn: an invalid temporal literal
 * (ER_WRONG_VALUE) or an unknown system variable in its value, in written order, then the
 * item named twice (ER_DUP_SIGNAL_SET). All come before RESIGNAL outside a handler and before
 * the names of the values are resolved (verified on live 5.6.51, 5.7.44, 8.0.44, 8.4.7 and 9.1.0
 * servers).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/signal.html,
 * https://dev.mysql.com/doc/refman/8.4/en/resignal.html.
 *
 * @visibility MySqlMemory
 */
final class SignalProblems
{
    /**
     * Raises the first parse-time problem of a SIGNAL or RESIGNAL statement.
     *
     * @throws SqlError When the statement has such a problem
     */
    public function check(MySqlStatement $operation, Session $session): void
    {
        $statement = $operation;
        if (!$statement instanceof Signal && !$statement instanceof Resignal) {
            return;
        }
        foreach ($operation->statementInformation()->diagnostics as $diagnostic) {
            if ($diagnostic instanceof ProgramProblem && in_array($diagnostic->rule, [ProgramRule::UndefinedCondition, ProgramRule::BadSqlState, ProgramRule::SignalConditionKind], true)) {
                throw (new ProgramErrors())->error($diagnostic);
            }
        }
        if (array_filter($statement->items, static fn ($item): bool => $item->value instanceof \SqlSemantics\Platform\MySql\Statement\Variable\VariableAssignment || $item->value instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Operator\DeferredAssignment) !== []) {
            return;
        }
        $seen = [];
        foreach ($statement->items as $item) {
            foreach ((new Walker())->find($item->value, Node::class) as $node) {
                $this->value($node, $operation, $session);
            }
            if (in_array($item->name, $seen, true)) {
                throw (new ProgramErrors())->error(new ProgramProblem(ProgramRule::DuplicateSignalItem, $item->name->value));
            }
            $seen[] = $item->name;
        }
    }

    /**
     * Resolves a legacy condition assignment before refusing its operand role.
     * MySQL 5.7 itemizes signal_allowed_expr inside its grammar action, earlier
     * than an ordinary SELECT expression. This does not change SELECT's phases.
     * Source: https://github.com/mysql/mysql-server/blob/mysql-5.7.44/sql/sql_yacc.yy.
     *
     * @throws SqlError When an operand cannot be resolved in this context
     */
    public function assignment(\SqlSemantics\Platform\MySql\Statement\Routine\Problem\AssignedConditionOperand $problem, MySqlStatement $operation, Session $session, ?Node $instruction = null): void
    {
        $instruction ??= $operation;
        $values = $instruction instanceof Signal || $instruction instanceof Resignal ? array_map(static fn ($item) => $item->value, $instruction->items) : [$problem->operand];
        foreach ($values as $value) {
            foreach ((new \SqlSemantics\Platform\MySql\Rules\Query\Structure\ReadDependencies())->nodes($value, childrenFirst: $session->settings()->release() === \SqlSemantics\Contract\GrammarRelease::MySql5651) as $node) {
                $this->value($node, $operation, $session);
                if (($node instanceof \SqlSemantics\Platform\MySql\Statement\Call\FunctionCall || $node instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredCall) && !\MySqlMemory\Command\Dispatcher::program($operation)) {
                    (new \MySqlMemory\Session\Access\DefaultDatabase())->calls($node, $session);
                }
            }
            if ($value === $problem->operand) {
                break;
            }
        }
    }

    /**
     * Raises the problem of one node of the value of an item: an invalid temporal literal, or a system variable the server does not know.
     *
     * @throws SqlError When the node has such a problem
     */
    public function value(Node $node, MySqlStatement $operation, Session $session): void
    {
        if ($node instanceof TemporalLiteral) {
            $form = $node->form === TemporalForm::Timestamp ? 'DATETIME' : $node->form->value;
            $modes = $session->modes();
            $value = $node->form === TemporalForm::Timestamp ? \MySqlMemory\Evaluation\Compile\Family\TimestampLiteral::value($node->text, 6, $modes, (\MySqlMemory\Value\Zone::named((string) $session->variables->read('time_zone'), $session->instance->timeZoneNames) ?? \MySqlMemory\Value\Zone::utc())) : Temporal::literal($form, $node->text, 6, $modes->has('NO_ZERO_DATE'), $modes->has('NO_ZERO_IN_DATE'), $modes->has('ALLOW_INVALID_DATES'));
            if ($value === null) {
                throw $node->form === TemporalForm::Timestamp ? \MySqlMemory\Evaluation\Compile\Family\TimestampLiteral::error($node->text, $modes) : DataError::WrongValue->error($form, substr($node->text, 0, 128));
            }
        }
        if (!$node instanceof SystemVariable) {
            return;
        }
        $name = strtolower(($node->instance === null ? '' : $node->instance->value . '.') . $node->name->value);
        foreach ($operation->statementInformation()->diagnostics as $diagnostic) {
            if ($diagnostic instanceof UnknownSystemVariable && strtolower($diagnostic->name) === $name) {
                throw (new Errors())->error($diagnostic, $session, 'field list', $operation);
            }
        }
    }
}
