<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Program;

use MySqlMemory\Command\Command;
use MySqlMemory\Command\Show\Heading;
use MySqlMemory\Command\Show\Listing;
use MySqlMemory\Error\ErrorCode;
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
        $key = strtolower($name);
        $database = ProgramSource::database($statement->name->schema, $session);
        $schema = $session->instance->dictionary->schema($database);
        $flag = ColumnFlag::NotNull->value;
        if ($statement instanceof ShowCreateTrigger) {
            if ($schema === null) {
                throw ErrorCode::BadDatabase->error($database);
            }
            foreach ($schema->triggers as $trigger) {
                if (strtolower($trigger->name) === $key) {
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

            throw ErrorCode::TriggerMissing->error();
        }
        if ($statement instanceof ShowCreateEvent) {
            $event = $schema === null ? null : ($schema->events[$key] ?? null);
            if ($event === null) {
                throw ErrorCode::EventMissing->error($name);
            }
            $text = $event->create();

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
        $function = $statement instanceof ShowCreateFunction;
        $kind = $function ? 'FUNCTION' : 'PROCEDURE';
        $routine = $schema === null ? null : (RoutineCommand::routines($schema, $function)[$key] ?? null);
        if ($routine === null) {
            throw ErrorCode::RoutineMissing->error($kind, $name);
        }
        $title = $function ? 'Function' : 'Procedure';

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
