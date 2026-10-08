<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Program;

use MySqlMemory\Command\Command;
use MySqlMemory\Dictionary\Event;
use MySqlMemory\Error\DataError;
use MySqlMemory\Error\ProgramError;
use MySqlMemory\Error\QueryError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Error\StatementError;
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
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Scalar;

/**
 * Executes CREATE EVENT and ALTER EVENT.
 *
 * The schedule is evaluated first: a time that is no datetime is ER_WRONG_VALUE, an interval
 * that is not positive ER_EVENT_INTERVAL_NOT_POSITIVE_OR_TOO_BIG, a unit of microseconds
 * ER_NOT_SUPPORTED_YET, and an end before the start ER_EVENT_ENDS_BEFORE_STARTS. A recurring
 * event starts now unless STARTS says otherwise. A one-time event in the past is dropped at
 * once without ON COMPLETION PRESERVE, and disabled with it, each with a note. An event of the
 * same name is ER_EVENT_ALREADY_EXISTS, a note with IF NOT EXISTS; ALTER EVENT of a missing
 * event is ER_EVENT_DOES_NOT_EXIST. The emulator keeps events but never runs them.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-event.html,
 * https://dev.mysql.com/doc/refman/8.4/en/alter-event.html.
 *
 * @visibility MySqlMemory
 */
final class EventCommand implements Command
{
    /**
     * Answers true.
     */
    #[Override]
    public function clearsDiagnostics(): bool
    {
        return true;
    }

    /**
     * Creates or changes the event.
     */
    #[Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        assert($statement instanceof CreateEvent || $statement instanceof AlterEvent);
        $session->transaction->commit();
        $planner = new Planner($statement, $operation->facts, $session->settings(), $connection, $session->instance->dictionary);
        $schedule = $statement->schedule === null ? null : $this->schedule($statement->schedule, $planner, $context);
        $database = ProgramSource::database($statement->name->schema, $session);
        $dictionary = $session->instance->dictionary;
        $schema = $dictionary->schema($database);
        $key = strtolower($statement->name->name->value);
        if ($statement instanceof AlterEvent) {
            $event = $schema === null ? null : ($schema->events[$key] ?? null);
            if ($event === null) {
                throw ProgramError::EventMissing->error($statement->name->name->value);
            }
            $this->alter($statement, $event, $session, $schedule);
        } else {
            if ($schema !== null && isset($schema->events[$key])) {
                if (!$statement->ifNotExists) {
                    throw ProgramError::EventExists->error($statement->name->name->value);
                }
                $context->note(ProgramError::EventExists, $statement->name->name->value);

                return new Completion(0, 0, $context->diagnostics->count());
            }
            if ($schema === null) {
                throw QueryError::BadDatabase->error($database);
            }
            assert($schedule !== null);
            $now = ProgramSource::now();
            $event = new Event($database, $statement->name->name->value, ProgramSource::definer($statement->definer, $session, $context), (string) $session->variables->read('time_zone'), $schedule[0], $schedule[1], $schedule[2], $schedule[3], $this->status($statement->status), $statement->completion === EventCompletion::Preserve, $statement->comment->value ?? '', ProgramSource::of($session)->body('ev_sql_stmt'), (string) $session->variables->read('sql_mode'), $now, $now, ProgramSource::charsets($session, $database));
            $schema->events[$key] = $event;
        }
        if ($event->at !== null && $event->at < ProgramSource::now() && $schedule !== null) {
            if (!$event->preserve && $statement instanceof CreateEvent) {
                unset($dictionary->schemas[$event->schema]->events[strtolower($event->name)]);
                $context->note(ProgramError::EventDroppedInPast);
            } else {
                $event->status = 'DISABLED';
                $context->note(ProgramError::EventDisabledInPast);
            }
        }

        return new Completion(0, 0, $context->diagnostics->count());
    }

    /**
     * Changes an event as ALTER EVENT says, renaming it last.
     *
     * @param array{string|null, array{string, string}|null, string|null, string|null}|null $schedule
     *
     * @throws SqlError When the new name is the old one, names a missing database or an existing event
     */
    public function alter(AlterEvent $statement, Event $event, Session $session, ?array $schedule): void
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
            $event->body = ProgramSource::of($session)->body('ev_sql_stmt');
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
        $quantity = $planner->compiler->compile($schedule->quantity, new Scope());
        $value = $quantity->evaluate(new Frame($context));
        if ($value === null) {
            throw DataError::WrongValue->error('INTERVAL', 'NULL');
        }
        if (in_array($schedule->unit, [IntervalUnit::Microsecond, IntervalUnit::SecondMicrosecond, IntervalUnit::MinuteMicrosecond, IntervalUnit::HourMicrosecond, IntervalUnit::DayMicrosecond], true)) {
            throw StatementError::NotSupportedYet->error('MICROSECOND');
        }
        $simple = in_array($schedule->unit, [IntervalUnit::Second, IntervalUnit::Minute, IntervalUnit::Hour, IntervalUnit::Day, IntervalUnit::Week, IntervalUnit::Month, IntervalUnit::Quarter, IntervalUnit::Year], true);
        $text = (string) Convert::toText($value, $quantity->domain());
        $number = $simple ? (string) Convert::toInteger($value, $quantity->domain(), $context) : $this->composite($text, $schedule->unit);
        if ((int) $number <= 0 && !str_contains($number, "'")) {
            throw ProgramError::IntervalNotPositive->error();
        }
        $starts = $schedule->starts === null ? ProgramSource::now() : $this->time($schedule->starts, 'STARTS', $planner, $context);
        $ends = $schedule->ends === null ? null : $this->time($schedule->ends, 'ENDS', $planner, $context);
        if ($ends !== null && ($ends < $starts || $ends < ProgramSource::now())) {
            throw ProgramError::EndsBeforeStarts->error();
        }

        return [null, [$number, $schedule->unit->value], $starts, $ends];
    }

    /**
     * Writes the value of an interval of a composite unit as the server keeps it: its parts read from the right, carried into the larger units, and quoted.
     *
     * @throws SqlError When the value has more parts than the unit (ER_WRONG_VALUE) or is not positive
     */
    public function composite(string $text, IntervalUnit $unit): string
    {
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
     * Evaluates a time of a schedule into a datetime to the second.
     *
     * @throws SqlError When the value is NULL or no datetime (ER_WRONG_VALUE)
     */
    public function time(Scalar $expression, string $clause, Planner $planner, Context $context): string
    {
        $compiled = $planner->compiler->compile($expression, new Scope());
        $value = $compiled->evaluate(new Frame($context));
        $text = $value === null ? 'NULL' : (string) Convert::toText($value, $compiled->domain());
        $parts = $value === null ? null : Temporal::parseDateTime($text);
        if ($parts === null || !Temporal::valid($parts[0], $parts[1], $parts[2]) || $parts[1] === 0 || $parts[2] === 0 || $parts[3] > 23 || $parts[4] > 59 || $parts[5] > 59) {
            if ($value !== null) {
                $context->warnMessage(DataError::TruncatedWrongValue, DataError::WrongValue->message('datetime', $text));
            }

            throw DataError::WrongValue->error($clause, $text);
        }

        return Temporal::dateTime($parts[0], $parts[1], $parts[2], $parts[3], $parts[4], $parts[5], 0, 0);
    }
}
