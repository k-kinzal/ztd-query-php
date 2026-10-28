<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Program;

use MySqlMemory\Command\Command;
use MySqlMemory\Dictionary\Event;
use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Error\Family\ProgramError;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\Family\StatementError;
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
use MySqlMemory\Value\Temporal;
use Override;
use SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit;
use SqlSemantics\Platform\MySql\Statement\Routine\AlterEvent;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateEvent;
use SqlSemantics\Platform\MySql\Statement\Routine\Event\Completion as EventCompletion;
use SqlSemantics\Platform\MySql\Statement\Routine\Event\EventStatus;
use SqlSemantics\Platform\MySql\Statement\Routine\Event\OnceSchedule;
use SqlSemantics\Platform\MySql\Statement\Routine\Event\RecurringSchedule;
use SqlSemantics\Platform\MySql\Statement\Routine\Event\Schedule;
use SqlSemantics\Platform\MySql\Statement\Statement as MySqlStatement;
use SqlSemantics\Statement\Scalar;

/**
 * Executes CREATE EVENT and ALTER EVENT.
 *
 * The schedule is evaluated first: a time that is no datetime is ER_WRONG_VALUE, an interval
 * that is not positive ER_EVENT_INTERVAL_NOT_POSITIVE_OR_TOO_BIG, a unit of microseconds
 * ER_NOT_SUPPORTED_YET, and an end before the start ER_EVENT_ENDS_BEFORE_STARTS. A recurring
 * event starts now unless STARTS says otherwise. A one-time event in the past is dropped at
 * once without ON COMPLETION PRESERVE, and disabled with it; an explicit disabled status needs
 * no disabling note. ALTER refuses an expired replacement without PRESERVE. An event of the
 * same name is ER_EVENT_ALREADY_EXISTS, a note with IF NOT EXISTS; ALTER EVENT of a missing
 * event is ER_EVENT_DOES_NOT_EXIST, found after the note of a DEFINER that does not exist and
 * the database of RENAME TO (verified on live 8.0 and 8.4 servers). The emulator keeps events
 * but never runs them.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-event.html,
 * https://dev.mysql.com/doc/refman/8.4/en/alter-event.html.
 *
 * @visibility MySqlMemory
 */
final class EventCommand implements Command
{
    /**
     * @param bool $clears Whether the statement starts with an empty diagnostics area: a program whose body declares a handler is created leaving the area as it was (verified on a live 8.4 server)
     */
    public function __construct(public readonly bool $clears = true)
    {
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
     * Creates or changes the event.
     */
    #[Override]
    public function execute(\SqlSemantics\Statement\AnalyzedStatement $operation, Session $session, Context $context, Connection $connection): Reply
    {
        assert($operation instanceof MySqlStatement);
        $statement = $operation;
        assert($statement instanceof CreateEvent || $statement instanceof AlterEvent);
        $session->transaction->commit();
        ProgramSource::definer($statement->definer, $session, $context);
        $planner = new Planner($statement, $operation->statementInformation(), $session->settings(), $connection, $session->instance->dictionary, $session->temporaries, \MySqlMemory\Session\Statement\Catalog::snapshot($session));
        $schedule = $statement->schedule === null ? null : $this->schedule($statement->schedule, $planner, $context);
        $database = ProgramSource::database($statement->name->schema, $session);
        $dictionary = $session->instance->dictionary;
        $schema = $dictionary->schema($database);
        $key = strtolower($statement->name->name->value);
        if ($statement instanceof AlterEvent) {
            $event = $this->found($statement, $database, $session, $context, true);
            $this->alter($statement, $event, $session, $schedule, $context, ProgramText::of($operation, $session));
        } else {
            if ($schema !== null && isset($schema->events[$key])) {
                if (!$statement->ifNotExists) {
                    throw ProgramError::EventExists->error($statement->name->name->value);
                }
                $context->note(ProgramError::EventExists, $statement->name->name->value);

                return new Completion(0, 0, $context->diagnostics->raised());
            }
            if ($schema === null) {
                throw QueryError::BadDatabase->error($database);
            }
            assert($schedule !== null);
            $now = ProgramSource::now();
            $event = new Event($database, $statement->name->name->value, ProgramSource::definer($statement->definer, $session), (string) $session->variables->read('time_zone'), $schedule[0], $schedule[1], $schedule[2], $schedule[3], $this->status($statement->status), $statement->completion === EventCompletion::Preserve, $statement->comment->value ?? '', ProgramText::of($operation, $session)->body($statement->body, 'ev_sql_stmt'), (string) $session->variables->read('sql_mode'), $now, $now, ProgramSource::charsets($session, $database));
            $schema->events[$key] = $event;
        }
        if ($schedule !== null) {
            $this->lapse($event, $statement instanceof CreateEvent, $session, $context, $statement->status !== null && $statement->status !== EventStatus::Enable);
        }
        if (isset($dictionary->schema($event->schema)?->events[strtolower($event->name)])) {
            $session->instance->registry->eventScheduler->activated($event);
        }

        return new Completion(0, 0, $context->diagnostics->stale ? 0 : $context->diagnostics->raised());
    }

    /**
     * Answers the event ALTER EVENT changes, after the note of a DEFINER that does not exist and
     * the database of RENAME TO.
     *
     * @throws SqlError When the database of the new name or the event does not exist
     */
    public function found(AlterEvent $statement, string $database, Session $session, Context $context, bool $definerChecked = false): Event
    {
        if (!$definerChecked && $statement->definer !== null) {
            ProgramSource::definer($statement->definer, $session, $context);
        }
        $dictionary = $session->instance->dictionary;
        $renamed = $statement->newName === null ? null : $statement->newName->schema->value ?? $session->variables->database;
        if ($renamed !== null && $dictionary->schema($renamed) === null && $session->settings()->release() === \SqlSemantics\Contract\GrammarRelease::MySql5744) {
            throw QueryError::BadDatabase->error($renamed);
        }
        if ($statement->newName !== null && $renamed === $database && strtolower($statement->newName->name->value) === strtolower($statement->name->name->value)) {
            throw ProgramError::SameEventName->error();
        }
        if ($renamed !== null && $dictionary->schema($renamed) === null) {
            throw QueryError::BadDatabase->error($renamed);
        }
        $event = $dictionary->schema($database)?->events[strtolower($statement->name->name->value)] ?? null;
        if ($event === null) {
            throw ProgramError::EventMissing->error($statement->name->name->value);
        }

        return $event;
    }

    /**
     * Ends a one-time event whose time has passed according to the session clock: a created
     * event without ON COMPLETION PRESERVE is dropped, any other one disabled, each with its note
     * unless this statement explicitly disables a preserved event.
     * With the scheduler enabled, modern releases also disable an event without a note when
     * its final time is after the session clock but before the actual clock. A stopped
     * scheduler leaves that definition enabled (verified on live 8.0 and 8.4 servers).
     */
    public function lapse(Event $event, bool $created, Session $session, Context $context, bool $disabled = false): void
    {
        if ($event->at === null || $event->at >= Temporal::dateTime(...[...$context->local(), 0])) {
            $end = $event->at ?? $event->ends;
            $legacy = in_array($context->modes->release, [\SqlSemantics\Contract\GrammarRelease::MySql5651, \SqlSemantics\Contract\GrammarRelease::MySql5744], true);
            if (!$legacy && $session->variables->read('event_scheduler') === 'ON' && $end !== null && $end < Temporal::dateTime(...[...$context->local(microtime(true)), 0]) && $event->status === 'ENABLED') {
                $event->status = 'DISABLED';
            }

            return;
        }
        if (!$event->preserve && $created) {
            unset($session->instance->dictionary->schemas[$event->schema]->events[strtolower($event->name)]);
            $context->note(ProgramError::EventDroppedInPast);
        } elseif (!$disabled) {
            $event->status = 'DISABLED';
            $context->note(ProgramError::EventDisabledInPast);
        }
    }

    /**
     * Changes an event as ALTER EVENT says, renaming it last.
     *
     * @param array{string|null, array{string, string}|null, string|null, string|null}|null $schedule
     *
     * @throws SqlError When the new name is the old one, names a missing database or an existing event
     */
    public function alter(AlterEvent $statement, Event $event, Session $session, ?array $schedule, Context $context, ?ProgramText $text = null): void
    {
        $dictionary = $session->instance->dictionary;
        $move = null;
        $renamed = $statement->newName;
        if ($renamed !== null) {
            $database = $renamed->schema->value ?? $session->variables->database;
            $target = $dictionary->schema($database);
            if ($target === null) {
                throw QueryError::BadDatabase->error($database);
            }
            $key = strtolower($renamed->name->value);
            if ($database === $event->schema && $key === strtolower($event->name)) {
                throw ProgramError::SameEventName->error();
            }
            if (isset($target->events[$key])) {
                throw ProgramError::EventExists->error($renamed->name->value);
            }
            $move = [$target, $renamed->name->value];
        }
        $this->changing($statement, $event, $schedule, $context);
        if ($schedule !== null) {
            [$event->at, $event->every, $event->starts, $event->ends] = $schedule;
        }
        if ($statement->completion !== null) {
            $event->preserve = $statement->completion === EventCompletion::Preserve;
        }
        if ($statement->status !== null) {
            $event->status = $this->status($statement->status);
        }
        if ($statement->comment !== null) {
            $event->comment = $statement->comment->value;
        }
        if ($statement->body !== null) {
            $event->body = ($text ?? new ProgramText($session->semantics()->context()->profile))->body($statement->body, 'ev_sql_stmt');
        }
        if ($statement->definer !== null) {
            $event->definer = ProgramSource::definer($statement->definer, $session);
        }
        $event->modified = ProgramSource::now();
        if ($move !== null) {
            unset($dictionary->schemas[$event->schema]->events[strtolower($event->name)]);
            [$target, $name] = $move;
            $event->schema = $target->name;
            $event->name = $name;
            $target->events[strtolower($name)] = $event;
        }
    }

    /**
     * Rejects an expired replacement schedule before changing an unpreserved event.
     * Changing completion alone retains the existing schedule without this check.
     *
     * @param array{string|null, array{string, string}|null, string|null, string|null}|null $schedule
     * @throws SqlError When ALTER EVENT would install an expired one-time schedule
     */
    public function changing(AlterEvent $statement, Event $event, ?array $schedule, Context $context): void
    {
        $preserve = $statement->completion === null ? $event->preserve : $statement->completion === EventCompletion::Preserve;
        if (!$preserve && $schedule !== null && $schedule[0] !== null && $schedule[0] < Temporal::dateTime(...[...$context->local(), 0])) {
            throw ProgramError::EventUnchangedInPast->error();
        }
    }

    /**
     * Answers the status SHOW EVENTS shows for a status clause; an event is enabled by default.
     */
    public function status(?EventStatus $status): string
    {
        return match ($status) {
            null, EventStatus::Enable => 'ENABLED',
            EventStatus::Disable => 'DISABLED',
            EventStatus::DisableOnSlave, EventStatus::DisableOnReplica => 'REPLICA_SIDE_DISABLED',
        };
    }

    /**
     * Evaluates a schedule: the time of a one-time event, or the interval, start and end of a recurring one.
     *
     * @return array{string|null, array{string, string}|null, string|null, string|null}
     *
     * @throws SqlError When a time or the interval is refused
     */
    public function schedule(Schedule $schedule, Planner $planner, Context $context): array
    {
        if ($schedule instanceof OnceSchedule) {
            return [$this->time($schedule->at, 'AT', $planner, $context), null, null, null];
        }
        assert($schedule instanceof RecurringSchedule);
        $quantity = $this->expression($schedule->quantity, 'INTERVAL', $planner);
        $value = $quantity->evaluate(new Frame($context));
        if ($value === null) {
            throw DataError::WrongValue->error('INTERVAL', 'NULL');
        }
        $unit = $schedule->unit->canonical();
        if (in_array($unit, [IntervalUnit::Microsecond, IntervalUnit::SecondMicrosecond, IntervalUnit::MinuteMicrosecond, IntervalUnit::HourMicrosecond, IntervalUnit::DayMicrosecond], true)) {
            throw StatementError::NotSupportedYet->error('MICROSECOND');
        }
        $simple = in_array($unit, [IntervalUnit::Second, IntervalUnit::Minute, IntervalUnit::Hour, IntervalUnit::Day, IntervalUnit::Week, IntervalUnit::Month, IntervalUnit::Quarter, IntervalUnit::Year], true);
        $text = (string) Convert::toText($value, $quantity->domain());
        $number = $simple ? $this->intervalNumber($value, $quantity, $context, $unit) : $this->composite($text, $unit);
        if ((int) $number <= 0 && !str_contains($number, "'")) {
            throw ProgramError::IntervalNotPositive->error();
        }
        $now = Temporal::dateTime(...[...$context->local(), 0]);
        $starts = $schedule->starts === null ? $now : $this->time($schedule->starts, 'STARTS', $planner, $context);
        $ends = $schedule->ends === null ? null : $this->time($schedule->ends, 'ENDS', $planner, $context);
        if ($ends !== null && ($ends < $starts || $ends < $now)) {
            throw ProgramError::EndsBeforeStarts->error();
        }

        return [null, [$number, $unit->value], $starts, $ends];
    }

    /**
     * Reads SECOND through decimal conversion and the other simple units as integers. MySQL 5.6 accepts a decimal numeric prefix without warning about trailing text.
     */
    public function intervalNumber(int|float|string $value, \MySqlMemory\Evaluation\Evaluable $quantity, Context $context, IntervalUnit $unit = IntervalUnit::Second): string
    {
        $unit = $unit->canonical();
        if ($unit !== IntervalUnit::Second) {
            return (string) Convert::toInteger($value, $quantity->domain(), $context);
        }
        if (in_array($context->modes->release, [\SqlSemantics\Contract\GrammarRelease::MySql5651, \SqlSemantics\Contract\GrammarRelease::MySql5744], true) && $quantity->domain()->kind === \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind::String && preg_match('/\A[ \t\n\r\v\f]*[+-]?\.?[0-9]/', (string) $value) === 1) {
            return \MySqlMemory\Value\Decimal::round(\MySqlMemory\Value\NumericText::exact((string) $value)->number, 0);
        }

        return \MySqlMemory\Value\Decimal::round(Convert::operandDecimal($value, $quantity, $context) ?? '0', 0);
    }

    /**
     * Writes the value of an interval of a composite unit as the server keeps it: its parts read from the right, carried into the larger units, and quoted.
     *
     * @throws SqlError When the value has more parts than the unit (ER_WRONG_VALUE) or is not positive
     */
    public function composite(string $text, IntervalUnit $unit): string
    {
        $unit = $unit->canonical();
        $formats = match ($unit) {
            IntervalUnit::YearMonth => [[1, 12], ['', '-']],
            IntervalUnit::DayHour => [[1, 24], ['', ' ']],
            IntervalUnit::DayMinute => [[1, 24, 60], ['', ' ', ':']],
            IntervalUnit::DaySecond => [[1, 24, 60, 60], ['', ' ', ':', ':']],
            IntervalUnit::HourMinute, IntervalUnit::MinuteSecond => [[1, 60], ['', ':']],
            IntervalUnit::HourSecond => [[1, 60, 60], ['', ':', ':']],
            IntervalUnit::Microsecond, IntervalUnit::Second, IntervalUnit::Minute, IntervalUnit::Hour, IntervalUnit::Day, IntervalUnit::Week, IntervalUnit::Month, IntervalUnit::Quarter, IntervalUnit::Year,
            IntervalUnit::SecondMicrosecond, IntervalUnit::MinuteMicrosecond, IntervalUnit::HourMicrosecond, IntervalUnit::DayMicrosecond => [[1], ['']],
        };
        [$sizes, $separators] = $formats;
        $trimmed = ltrim($text);
        if (str_starts_with($trimmed, '-')) {
            throw ProgramError::IntervalNotPositive->error();
        }
        preg_match_all('/[0-9]+/', $trimmed, $groups);
        $parts = array_map('intval', $groups[0]);
        if (count($parts) > count($sizes)) {
            throw DataError::WrongValue->error('INTERVAL', $text);
        }
        $parts = array_pad($parts, -count($sizes), 0);
        $total = 0;
        foreach ($parts as $position => $part) {
            $total = $total * ($sizes[$position] ?? 1) + $part;
        }
        if ($total <= 0) {
            throw ProgramError::IntervalNotPositive->error();
        }
        $written = [];
        for ($position = count($sizes) - 1; $position > 0; $position--) {
            $size = $sizes[$position] ?? 1;
            $written[$position] = $total % $size;
            $total = intdiv($total, $size);
        }
        $written[0] = $total;
        $value = '';
        foreach ($separators as $position => $separator) {
            $value .= $separator . ($written[$position] ?? 0);
        }

        return "'" . $value . "'";
    }

    /**
     * Preserves legacy conversion conditions following an invalid system-variable scope.
     * Modern schedules stop during variable contextualization instead.
     *
     * @throws SqlError When an expression cannot be compiled, retaining subsequent conversion conditions
     */
    public function expression(Scalar $expression, string $clause, Planner $planner): \MySqlMemory\Evaluation\Evaluable
    {
        try {
            return $planner->compiler->compile($expression, new Scope());
        } catch (SqlError $error) {
            if (!$planner->settings->legacy() || $error->error !== \MySqlMemory\Error\Family\AdministrationError::IncorrectGlobalLocalVariable) {
                throw $error;
            }
            $conversion = $clause === 'ENDS' ? ProgramError::EndsBeforeStarts->error() : DataError::WrongValue->error($clause, 'NULL');
            throw new SqlError($error->error, $error->getMessage(), $error, [...$error->following, [$conversion->getCode(), $conversion->getMessage()]]);
        }
    }

    /**
     * Evaluates a time of a schedule into a datetime to the second.
     * Instants must fit the release's epoch range: through 2038 on MySQL 5.6/5.7,
     * through 3001 on modern releases. Bounds apply after the session zone conversion.
     *
     * @throws SqlError When the value is NULL, no datetime or outside the epoch range; an out-of-range ENDS uses ER_EVENT_ENDS_BEFORE_STARTS
     */
    public function time(Scalar $expression, string $clause, Planner $planner, Context $context): string
    {
        $compiled = $this->expression($expression, $clause, $planner);
        $value = $compiled->evaluate(new Frame($context));
        $text = $value === null ? 'NULL' : (string) Convert::toText($value, $compiled->domain());
        $parts = $value === null ? null : Temporal::parseDateTime($text);
        if ($parts === null || !Temporal::valid($parts[0], $parts[1], $parts[2]) || $parts[1] === 0 || $parts[2] === 0 || $parts[3] > 23 || $parts[4] > 59 || $parts[5] > 59) {
            if ($value !== null) {
                $context->warnMessage(DataError::TruncatedWrongValue, DataError::WrongValue->message('datetime', $text));
            }

            throw DataError::WrongValue->error($clause, $text);
        }
        $instant = $context->zone()->instant(\MySqlMemory\Value\Calendar::epoch($parts[0], $parts[1], $parts[2], $parts[3], $parts[4], $parts[5]));
        $latest = $planner->settings->legacy() ? \MySqlMemory\Value\Calendar::epoch(2038, 1, 19, 3, 14, 7) : \MySqlMemory\Value\Calendar::epoch(3001, 1, 18, 23, 59, 59);
        if ($instant < 1 || $instant > $latest) {
            throw $clause === 'ENDS' ? ProgramError::EndsBeforeStarts->error() : DataError::WrongValue->error($clause, $text);
        }

        return Temporal::dateTime($parts[0], $parts[1], $parts[2], $parts[3], $parts[4], $parts[5], 0, 0);
    }
}
