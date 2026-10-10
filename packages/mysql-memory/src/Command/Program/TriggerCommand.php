<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Program;

use MySqlMemory\Command\Command;
use MySqlMemory\Dictionary\Schema;
use MySqlMemory\Dictionary\Trigger;
use MySqlMemory\Error\Family\ProgramError;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use Override;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateTrigger;
use SqlSemantics\Platform\MySql\Statement\Routine\Trigger\OrderPlacement;
use SqlSemantics\Statement\Operation;

/**
 * Executes CREATE TRIGGER.
 *
 * The table is looked up in the database of the trigger: a missing table is ER_NO_SUCH_TABLE and
 * a view ER_WRONG_OBJECT. A trigger of the same name is ER_TRG_ALREADY_EXISTS; with IF NOT
 * EXISTS it is a note on the same table and an error on another. FOLLOWS and PRECEDES place
 * the trigger after or before another one of the same table, time and event
 * (ER_REFERENCED_TRG_DOES_NOT_EXIST otherwise); without them it fires after those that exist.
 * The emulator keeps triggers but does not fire them.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-trigger.html.
 *
 * @visibility MySqlMemory
 */
final class TriggerCommand implements Command
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
     * Creates the trigger.
     */
    #[Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        assert($statement instanceof CreateTrigger);
        $session->transaction->commit();
        $database = ProgramSource::database($statement->name->schema, $session);
        $located = $statement->table->name->schema->value ?? $database;
        $schema = $session->instance->dictionary->schema($located);
        if ($schema === null) {
            throw QueryError::BadDatabase->error($located);
        }
        $table = $statement->table->name->name->value;
        if (isset($schema->views[$table])) {
            throw SchemaError::WrongObject->error($located, $table, 'BASE TABLE');
        }
        if ($schema->table($table) === null) {
            throw QueryError::NoSuchTable->error($located, $table);
        }
        if ($located !== $database) {
            throw ProgramError::TriggerInWrongSchema->error();
        }
        $name = $statement->name->name->value;
        foreach ($schema->triggers as $trigger) {
            if (strtolower($trigger->name) !== strtolower($name)) {
                continue;
            }
            if (!$statement->ifNotExists) {
                throw ProgramError::TriggerExists->error();
            }
            if ($trigger->table !== $table) {
                throw ProgramError::TriggerExistsElsewhere->error($database, $name);
            }
            $context->note(ProgramError::TriggerExistsOnTable, $name, $database, $table);

            return new Completion(0, 0, $context->diagnostics->count());
        }
        $position = $this->position($statement, $schema, $table);
        $trigger = new Trigger($database, $name, $table, $statement->time->value, $statement->event->value, ProgramSource::definer($statement->definer, $session, $context), ProgramSource::of($session)->body('sp_proc_stmt'), (string) $session->variables->read('sql_mode'), ProgramSource::now(2), ProgramSource::charsets($session, $database), $statement);
        array_splice($schema->triggers, $position, 0, [$trigger]);

        return new Completion();
    }

    /**
     * Answers the position of a new trigger among the triggers of its database: after or before the trigger FOLLOWS or PRECEDES names, of the same table, time and event, or after all of them without one.
     *
     * @throws \MySqlMemory\Error\SqlError When the trigger FOLLOWS or PRECEDES names does not exist (ER_REFERENCED_TRG_DOES_NOT_EXIST)
     */
    public function position(CreateTrigger $statement, Schema $schema, string $table): int
    {
        if ($statement->order === null) {
            return count($schema->triggers);
        }
        $position = null;
        foreach ($schema->triggers as $index => $trigger) {
            if ($trigger->table === $table && $trigger->time === $statement->time->value && $trigger->event === $statement->event->value && strtolower($trigger->name) === strtolower($statement->order->other->value)) {
                $position = $statement->order->placement === OrderPlacement::Follows ? $index + 1 : $index;
            }
        }
        if ($position === null) {
            throw ProgramError::ReferencedTriggerMissing->error($statement->order->other->value);
        }

        return $position;
    }
}
