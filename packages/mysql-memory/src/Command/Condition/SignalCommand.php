<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Condition;

use MySqlMemory\Command\Command;
use MySqlMemory\Error\Family\AdministrationError;
use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Error\Family\ProgramError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Evaluable;
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
 * Executes SIGNAL and RESIGNAL.
 *
 * SIGNAL raises the condition of its SQLSTATE: a class '01' value is a warning and the statement
 * succeeds, a class '02' value is the error ER_SIGNAL_NOT_FOUND and any other the error
 * ER_SIGNAL_EXCEPTION, unless MYSQL_ERRNO and MESSAGE_TEXT name the number and the text. The
 * items are read in the order of the condition information items, MYSQL_ERRNO last: a NULL
 * is refused (ER_WRONG_VALUE_FOR_VAR), MESSAGE_TEXT holds at most 128 characters and the other
 * text items 64 (ER_COND_ITEM_TOO_LONG), and MYSQL_ERRNO is rounded to an integer from 1 to
 * 65535; a decimal beyond the 64-bit range warns that it is truncated first. RESIGNAL has no
 * handler to act in outside a handler (ER_RESIGNAL_WITHOUT_ACTIVE_HANDLER), and leaves the
 * conditions of the statement before in the diagnostics area. Inside a handler, RESIGNAL starts
 * from the conditions the handler handles: with an SQLSTATE it adds its condition to them as
 * SIGNAL does, and without one it raises the handled condition again (verified on a live 8.4
 * server).
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
        assert($statement instanceof Signal || $statement instanceof Resignal);
        $stacked = $session->program === null ? [] : $session->program->stacked;
        if ($statement instanceof Resignal && $stacked === []) {
            throw ProgramError::ResignalWithoutHandler->error();
        }
        $planner = new Planner($statement, $operation->facts, $session->settings(), $connection, $session->instance->dictionary);
        $values = [];
        foreach ($statement->items as $item) {
            $values[$item->name->value] = $planner->compiler->compile($item->value, new Scope());
        }
        if ($statement instanceof Resignal) {
            $area = $stacked[count($stacked) - 1];
            $context->diagnostics->conditions = $area->conditions;
            $context->diagnostics->signalled = $area->signalled;
            if ($statement->condition === null) {
                return $this->again($area, $values, $context);
            }
        }
        assert($statement->condition instanceof SqlState);
        $state = $statement->condition->state->value;
        [$signalled, $number] = $this->items($state, $values, $context);
        $message = $signalled[ConditionItemName::MessageText->value] ?? null;
        $class = substr($state, 0, 2);
        $code = match ($class) {
            '01' => ProgramError::SignalWarning,
            '02' => ProgramError::SignalNotFound,
            default => ProgramError::SignalException,
        };
        if ($class === '01') {
            $context->diagnostics->signal($number ?? $code->value, $message ?? $code->message(), $signalled);

            return new Completion(0, 0, $context->diagnostics->count());
        }

        throw new SqlError($code, $message ?? $code->message(), null, [], $signalled, $number);
    }

    /**
     * Raises again the condition a handler handles, as RESIGNAL without SQLSTATE does: the last condition of its area, with the items the statement sets changed in place.
     *
     * The area of the handler becomes the diagnostics area; a warning is raised again as a
     * warning, any other condition as an error (verified on a live 8.4 server).
     * Source: https://dev.mysql.com/doc/refman/8.4/en/resignal.html.
     *
     * @param array<string, Evaluable> $values The compiled value of each item the statement sets, by item name
     *
     * @throws SqlError When the condition is an error, or an item is refused
     */
    public function again(\MySqlMemory\Session\Diagnostics $area, array $values, Context $context): Reply
    {
        $position = count($area->conditions) - 1;
        if ($position < 0) {
            throw ProgramError::ResignalWithoutHandler->error();
        }
        [$level, $code, $text] = $area->conditions[$position];
        $state = $area->item($position, 'RETURNED_SQLSTATE');
        [$signalled, $number] = $this->items($state, $values, $context);
        $kept = $area->signalled[$position] ?? ['RETURNED_SQLSTATE' => $state];
        $signalled = [...$kept, ...$signalled];
        $message = $signalled[ConditionItemName::MessageText->value] ?? $text;
        $number ??= $code;
        $context->diagnostics->conditions[$position] = [$level, $number, $message];
        $context->diagnostics->signalled[$position] = $signalled;
        if ($level !== 'Error') {
            return new Completion(0, 0, $context->diagnostics->count());
        }

        throw new SqlError(ProgramError::SignalException, $message, null, [], $signalled, $number, true);
    }

    /**
     * Evaluates the condition information items in the order of their names: a NULL is refused,
     * MESSAGE_TEXT holds at most 128 characters and the other text items 64, and MYSQL_ERRNO is read as a number.
     *
     * @param array<string, Evaluable> $values The compiled value of each item the statement sets, by item name
     * @return array{array<string, string>, int|null} The text items signalled, RETURNED_SQLSTATE first, and the MYSQL_ERRNO or null when it is not set
     * @throws SqlError When an item is NULL, too long, or MYSQL_ERRNO is no valid number
     */
    public function items(string $state, array $values, Context $context): array
    {
        $frame = new Frame($context);
        $signalled = ['RETURNED_SQLSTATE' => $state];
        $number = null;
        foreach (ConditionItemName::cases() as $name) {
            if (!isset($values[$name->value])) {
                continue;
            }
            $evaluable = $values[$name->value];
            $value = $evaluable->evaluate($frame);
            if ($value === null) {
                throw AdministrationError::WrongValueForVariable->error($name->value, 'NULL');
            }
            if ($name === ConditionItemName::MysqlErrno) {
                $number = $this->number($value, $evaluable->domain(), $context);
                continue;
            }
            $text = (string) Convert::toText($value, $evaluable->domain());
            if (mb_strlen($text) > ($name === ConditionItemName::MessageText ? 128 : 64)) {
                throw ProgramError::ConditionItemTooLong->error($name->value);
            }
            $signalled[$name->value] = $text;
        }

        return [$signalled, $number];
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
            $context->warning(DataError::TruncatedWrongValue, 'DECIMAL', $decimal);
        }
        $number = Convert::toInteger($value, $domain, $context);
        if ($number === null || $number < 1 || $number > 65535) {
            throw AdministrationError::WrongValueForVariable->error(ConditionItemName::MysqlErrno->value, (string) Convert::toText($value, $domain));
        }

        return $number;
    }
}
