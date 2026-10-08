<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Condition;

use MySqlMemory\Command\Command;
use MySqlMemory\Error\ErrorCode;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Plan\Planner;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use Override;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\ConditionItemName;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Resignal;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Signal;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\SqlState;
use SqlSemantics\Statement\Operation;

/**
 * Executes SIGNAL and RESIGNAL outside a stored program.
 *
 * SIGNAL raises the condition of its SQLSTATE: a class '01' value is a warning and the statement
 * succeeds, a class '02' value is the error ER_SIGNAL_NOT_FOUND and any other the error
 * ER_SIGNAL_EXCEPTION, unless MYSQL_ERRNO and MESSAGE_TEXT name the number and the text. The
 * items are read in the order of the condition information items, MYSQL_ERRNO last: a NULL
 * is refused (ER_WRONG_VALUE_FOR_VAR), MESSAGE_TEXT holds at most 128 characters and the other
 * text items 64 (ER_COND_ITEM_TOO_LONG), and MYSQL_ERRNO is rounded to an integer from 1 to
 * 65535; a decimal beyond the 64-bit range warns that it is truncated first. RESIGNAL has no
 * handler to act in outside a program (ER_RESIGNAL_WITHOUT_ACTIVE_HANDLER), and leaves the
 * conditions of the statement before in the diagnostics area (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/signal.html,
 * https://dev.mysql.com/doc/refman/8.4/en/resignal.html.
 *
 * @visibility MySqlMemory
 */
final class SignalCommand implements Command
{
    /**
     * @param bool $clears Whether the statement starts with an empty diagnostics area: a RESIGNAL passes on the area it finds unless the server refuses it while parsing
     */
    public function __construct(public readonly bool $clears = true)
    {
    }

    /**
     * Answers the command of a RESIGNAL statement: one that keeps the diagnostics area, unless its condition or its items are refused while it is parsed.
     */
    public static function resignal(Resignal $statement): self
    {
        $names = array_map(static fn ($item): string => $item->name->value, $statement->items);
        $valid = ($statement->condition === null || ($statement->condition instanceof SqlState && $statement->condition->valid())) && count(array_unique($names)) === count($names);

        return new self(!$valid);
    }

    /**
     * Answers whether the statement starts with an empty diagnostics area.
     */
    #[Override]
    public function clearsDiagnostics(): bool
    {
        return $this->clears;
    }

    /**
     * Raises the condition.
     */
    #[Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        if ($statement instanceof Resignal) {
            throw ErrorCode::ResignalWithoutHandler->error();
        }
        assert($statement instanceof Signal && $statement->condition instanceof SqlState);
        $state = $statement->condition->state->value;
        $planner = new Planner($statement, $operation->facts, $session->settings(), $connection, $session->instance->dictionary);
        $frame = new Frame($context);
        $values = [];
        foreach ($statement->items as $item) {
            $values[$item->name->value] = $planner->compiler->compile($item->value, new Scope());
        }
        $signalled = ['RETURNED_SQLSTATE' => $state];
        $message = null;
        $number = null;
        foreach (ConditionItemName::cases() as $name) {
            if (!isset($values[$name->value])) {
                continue;
            }
            $evaluable = $values[$name->value];
            $value = $evaluable->evaluate($frame);
            if ($value === null) {
                throw ErrorCode::WrongValueForVariable->error($name->value, 'NULL');
            }
            if ($name === ConditionItemName::MysqlErrno) {
                $number = $this->number($value, $evaluable->domain(), $context);
                continue;
            }
            $text = (string) Convert::toText($value, $evaluable->domain());
            if (mb_strlen($text) > ($name === ConditionItemName::MessageText ? 128 : 64)) {
                throw ErrorCode::ConditionItemTooLong->error($name->value);
            }
            $signalled[$name->value] = $text;
            if ($name === ConditionItemName::MessageText) {
                $message = $text;
            }
        }
        $class = substr($state, 0, 2);
        $code = match ($class) {
            '01' => ErrorCode::SignalWarning,
            '02' => ErrorCode::SignalNotFound,
            default => ErrorCode::SignalException,
        };
        if ($class === '01') {
            $context->diagnostics->signal($number ?? $code->value, $message ?? $code->message(), $signalled);

            return new Completion(0, 0, $context->diagnostics->count());
        }

        throw new SqlError($code, $message ?? $code->message(), null, [], $signalled, $number);
    }

    /**
     * Reads the value of MYSQL_ERRNO: an integer from 1 to 65535.
     *
     * @throws SqlError When the value is no such integer
     */
    public function number(int|float|string $value, \MySqlMemory\Typing\Domain $domain, Context $context): int
    {
        $decimal = (string) $value;
        if ($domain->kind === \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind::Decimal && is_numeric($decimal) && (bccomp($decimal, '18446744073709551615', 0) > 0 || bccomp($decimal, '-9223372036854775808', 0) < 0)) {
            $context->warning(ErrorCode::TruncatedWrongValue, 'DECIMAL', $decimal);
        }
        $number = Convert::toInteger($value, $domain, $context);
        if ($number === null || $number < 1 || $number > 65535) {
            throw ErrorCode::WrongValueForVariable->error(ConditionItemName::MysqlErrno->value, (string) Convert::toText($value, $domain));
        }

        return $number;
    }
}
