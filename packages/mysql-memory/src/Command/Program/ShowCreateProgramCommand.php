<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Program;

use MySqlMemory\Command\Command;
use MySqlMemory\Command\Show\Heading;
use MySqlMemory\Command\Show\Listing;
use MySqlMemory\Dictionary\Schema;
use MySqlMemory\Error\Family\ProgramError;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\ColumnFlag;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use Override;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Program\ShowCreateEvent;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Program\ShowCreateFunction;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Program\ShowCreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Program\ShowCreateTrigger;
use SqlSemantics\Statement\Operation;

/**
 * Executes SHOW CREATE PROCEDURE, SHOW CREATE FUNCTION, SHOW CREATE TRIGGER and SHOW CREATE EVENT.
 *
 * A missing routine is ER_SP_DOES_NOT_EXIST, named without its database; a missing trigger
 * ER_TRG_DOES_NOT_EXIST, or ER_BAD_DB_ERROR when its database does not exist; a missing event
 * ER_EVENT_DOES_NOT_EXIST. The sql_mode column, and the statement of an event, are as long as
 * their text; the other text columns have fixed lengths (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-create-procedure.html,
 * https://dev.mysql.com/doc/refman/8.4/en/show-create-trigger.html,
 * https://dev.mysql.com/doc/refman/8.4/en/show-create-event.html.
 *
 * @visibility MySqlMemory
 */
final class ShowCreateProgramCommand implements Command
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
     * Writes the statement that creates the program.
     */
    #[Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        assert($statement instanceof ShowCreateProcedure || $statement instanceof ShowCreateFunction || $statement instanceof ShowCreateTrigger || $statement instanceof ShowCreateEvent);
        $name = $statement->name->name->value;
        $database = ProgramSource::database($statement->name->schema, $session);
        $schema = $session->instance->dictionary->schema($database);
        if ($statement instanceof ShowCreateTrigger) {
            return $this->trigger($schema, $database, $name, $context);
        }
        if ($statement instanceof ShowCreateEvent) {
            return $this->event($schema, $name, $context);
        }

        return $this->routine($statement instanceof ShowCreateFunction, $schema, $name, $context);
    }

    /**
     * Writes the statement that creates a trigger.
     *
     * @param Schema|null $schema The database of the trigger, null when it does not exist
     * @param string $database The name of the database of the trigger
     * @param string $name The name of the trigger as written
     *
     * @throws SqlError When the database or the trigger does not exist
     */
    public function trigger(?Schema $schema, string $database, string $name, Context $context): Reply
    {
        if ($schema === null) {
            throw QueryError::BadDatabase->error($database);
        }
        $flag = ColumnFlag::NotNull->value;
        foreach ($schema->triggers as $trigger) {
            if (strtolower($trigger->name) === strtolower($name)) {
                return (new Listing([
                    Heading::text('Trigger', Field::VarString, 192, $flag, 31),
                    Heading::text('sql_mode', Field::VarString, strlen($trigger->mode), $flag, 31),
                    Heading::text('SQL Original Statement', Field::VarString, 1024, 0, 31),
                    Heading::text('character_set_client', Field::VarString, 32, $flag, 31),
                    Heading::text('collation_connection', Field::VarString, 32, $flag, 31),
                    Heading::text('Database Collation', Field::VarString, 32, $flag, 31),
                    new Heading('Created', Field::Timestamp, 0, $flag | ColumnFlag::Binary->value),
                ]))->sent([[$trigger->name, $trigger->mode, $trigger->create(), ...$trigger->charsets, ...[$trigger->created]]], $context);
            }
        }

        throw ProgramError::TriggerMissing->error();
    }

    /**
     * Writes the statement that creates an event.
     *
     * @param Schema|null $schema The database of the event, null when it does not exist
     * @param string $name The name of the event as written
     *
     * @throws SqlError When the event does not exist
     */
    public function event(?Schema $schema, string $name, Context $context): Reply
    {
        $event = $schema === null ? null : ($schema->events[strtolower($name)] ?? null);
        if ($event === null) {
            throw ProgramError::EventMissing->error($name);
        }
        $text = $event->create();
        $flag = ColumnFlag::NotNull->value;

        return (new Listing([
            Heading::text('Event', Field::VarString, 64, $flag, 31),
            Heading::text('sql_mode', Field::VarString, strlen($event->mode), $flag, 31),
            Heading::text('time_zone', Field::VarString, strlen($event->zone), $flag, 31),
            Heading::text('Create Event', Field::VarString, mb_strlen($text), $flag, 31),
            Heading::text('character_set_client', Field::VarString, 32, $flag, 31),
            Heading::text('collation_connection', Field::VarString, 32, $flag, 31),
            Heading::text('Database Collation', Field::VarString, 32, $flag, 31),
        ]))->sent([[$event->name, $event->mode, $event->zone, $text, ...$event->charsets]], $context);
    }

    /**
     * Writes the statement that creates a stored procedure or function.
     *
     * @param bool $function Whether the routine is a function
     * @param Schema|null $schema The database of the routine, null when it does not exist
     * @param string $name The name of the routine as written
     *
     * @throws SqlError When the routine does not exist
     */
    public function routine(bool $function, ?Schema $schema, string $name, Context $context): Reply
    {
        $routine = $schema === null ? null : (RoutineCommand::routines($schema, $function)[strtolower($name)] ?? null);
        if ($routine === null) {
            throw ProgramError::RoutineMissing->error($function ? 'FUNCTION' : 'PROCEDURE', $name);
        }
        $title = $function ? 'Function' : 'Procedure';
        $flag = ColumnFlag::NotNull->value;

        return (new Listing([
            Heading::text($title, Field::VarString, 64, $flag, 31),
            Heading::text('sql_mode', Field::VarString, strlen($routine->mode), $flag, 31),
            Heading::text('Create ' . $title, Field::VarString, 1024, 0, 31),
            Heading::text('character_set_client', Field::VarString, 32, $flag, 31),
            Heading::text('collation_connection', Field::VarString, 32, $flag, 31),
            Heading::text('Database Collation', Field::VarString, 32, $flag, 31),
        ]))->sent([[$routine->name, $routine->mode, $routine->create(), ...$routine->charsets]], $context);
    }
}
