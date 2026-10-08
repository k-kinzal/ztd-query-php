<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Condition;

use MySqlMemory\Command\Command;
use MySqlMemory\Error\ErrorCode;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Plan\Planner;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Diagnostics;
use MySqlMemory\Session\Session;
use MySqlMemory\Typing\Domain;
use Override;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\ConditionItemName;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\ConditionDiagnostics;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\DiagnosticsArea;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\GetDiagnostics;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\StatementItemName;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Statement\Operation;

/**
 * Executes GET DIAGNOSTICS outside a stored program: reads the diagnostics area of the statement before it into user variables.
 *
 * The statement leaves the area as it is. NUMBER counts its conditions and ROW_COUNT is the
 * row count of the statement before. A condition number that names no condition assigns
 * nothing and adds the error ER_DA_INVALID_CONDITION_NUMBER to the area, and the statement
 * still succeeds. The text items are utf8mb3 strings and the numbers integers. GET STACKED
 * DIAGNOSTICS has no handler to read outside a program (ER_GET_STACKED_DA_WITHOUT_ACTIVE_HANDLER).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/get-diagnostics.html.
 *
 * @visibility MySqlMemory
 */
final class DiagnosticsCommand implements Command
{
    /**
     * Answers false: the statement reads the area the statement before left.
     */
    #[Override]
    public function clearsDiagnostics(): bool
    {
        return false;
    }

    /**
     * Reads the items into their variables.
     */
    #[Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        assert($statement instanceof GetDiagnostics);
        if ($statement->area === DiagnosticsArea::Stacked) {
            throw ErrorCode::StackedWithoutHandler->error();
        }
        $diagnostics = $session->diagnostics;
        $planner = new Planner($statement, $operation->facts, $session->settings(), $connection, $session->instance->dictionary);
        $integer = $planner->compiler->names->stored(Domain::integer());
        $text = $planner->compiler->names->stored(Domain::string(0, Collation::known('utf8mb3_general_ci')));
        $information = $statement->information;
        $assignments = [];
        if ($information instanceof ConditionDiagnostics) {
            $number = $planner->compiler->compile($information->number, new Scope());
            $position = $this->position($number->evaluate(new Frame(new Context($context->modes, new Diagnostics(), $context->variables, $context->started))), $number->domain(), $context, $diagnostics->count());
            if ($position === null) {
                $diagnostics->error(ErrorCode::InvalidConditionNumber->value, ErrorCode::InvalidConditionNumber->message());

                return new Completion(0, 0, $diagnostics->count());
            }
            foreach ($information->items as $item) {
                $name = $item->item;
                assert($name instanceof ConditionItemName);
                $assignments[] = $name === ConditionItemName::MysqlErrno
                    ? [$item->target, $diagnostics->conditions[$position][1], $integer]
                    : [$item->target, $name === ConditionItemName::MessageText ? $diagnostics->conditions[$position][2] : $diagnostics->item($position, $name->value), $text];
            }
        } else {
            foreach ($information->items as $item) {
                $assignments[] = [$item->target, $item->item === StatementItemName::Number ? $diagnostics->count() : $session->variables->rowCount, $integer];
            }
        }
        foreach ($assignments as [$target, $value, $domain]) {
            if (!$target instanceof UserVariable) {
                throw ErrorCode::UndeclaredVariable->error($target->value);
            }
            $session->variables->assign($target->name->value, $value, $domain);
        }

        return new Completion(0, 0, $diagnostics->count());
    }

    /**
     * Answers the position in the area of the condition a number names, or null when it names none.
     */
    public function position(int|float|string|null $value, Domain $domain, Context $context, int $count): ?int
    {
        $number = Convert::toInteger($value, $domain, new Context($context->modes, new Diagnostics(), $context->variables, $context->started));
        if ($number === null || $number < 1 || $number > $count) {
            return null;
        }

        return $number - 1;
    }
}
