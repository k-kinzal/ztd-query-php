<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Program;

use MySqlMemory\Command\Command;
use MySqlMemory\Command\Show\Heading;
use MySqlMemory\Command\Show\Listing;
use MySqlMemory\Dictionary\Trigger;
use MySqlMemory\Error\QueryError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use Override;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowTriggers;
use SqlSemantics\Statement\Operation;

/**
 * Executes SHOW TRIGGERS: the triggers of a database, by table, event, time and the order they fire in.
 *
 * LIKE matches the table name. A database that does not exist is ER_BAD_DB_ERROR.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-triggers.html.
 *
 * @visibility MySqlMemory
 */
final class ShowTriggersCommand implements Command
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
     * Lists the triggers.
     */
    #[Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        assert($statement instanceof ShowTriggers);
        $database = ProgramSource::database($statement->database, $session);
        $schema = $session->instance->dictionary->schema($database);
        if ($schema === null) {
            throw QueryError::BadDatabase->error($database);
        }
        $triggers = $schema->triggers;
        $events = ['INSERT' => 0, 'UPDATE' => 1, 'DELETE' => 2];
        $order = array_flip(array_map(spl_object_id(...), $triggers));
        usort($triggers, static fn (Trigger $left, Trigger $right): int => [$left->table, $events[$left->event] ?? 3, $left->time === 'BEFORE' ? 0 : 1, $order[spl_object_id($left)]] <=> [$right->table, $events[$right->event] ?? 3, $right->time === 'BEFORE' ? 0 : 1, $order[spl_object_id($right)]]);
        $rows = array_map(static fn (Trigger $trigger): array => [$trigger->name, $trigger->event, $trigger->table, $trigger->body, $trigger->time, $trigger->created, $trigger->mode, $trigger->definer[0] . '@' . $trigger->definer[1], ...$trigger->charsets], $triggers);
        $table = 'TRIGGERS';

        return (new Listing([
            Heading::text('Trigger', Field::VarString, 64, 4097, 0, 'Trigger', $table, 'triggers'),
            Heading::text('Event', Field::String, 6, 4481, 0, 'Event', $table, 'triggers'),
            Heading::text('Table', Field::VarString, 64, 4225, 0, 'Table', $table, 'tables'),
            Heading::text('Statement', Field::Blob, 4294967295, 4241, 0, 'Statement', $table, 'triggers'),
            Heading::text('Timing', Field::String, 6, 4481, 0, 'Timing', $table, 'triggers'),
            new Heading('Created', Field::Timestamp, 22, 4225, 2, false, 'Created', $table, 'triggers'),
            Heading::text('sql_mode', Field::String, 520, 6273, 0, 'sql_mode', $table, 'triggers'),
            Heading::text('Definer', Field::VarString, 288, 4225, 0, 'Definer', $table, 'triggers'),
            Heading::text('character_set_client', Field::VarString, 64, 4097, 0, 'character_set_client', $table, 'character_sets'),
            Heading::text('collation_connection', Field::VarString, 64, 4097, 0, 'collation_connection', $table, 'collations'),
            Heading::text('Database Collation', Field::VarString, 64, 4097, 0, 'Database Collation', $table, 'collations'),
        ]))->result($rows, $operation, $session, $context, $connection, $statement->filter, 2);
    }
}
