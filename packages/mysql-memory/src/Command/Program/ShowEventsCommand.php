<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Program;

use MySqlMemory\Command\Command;
use MySqlMemory\Command\Show\Heading;
use MySqlMemory\Command\Show\Listing;
use MySqlMemory\Dictionary\Event;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use Override;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowEvents;
use SqlSemantics\Statement\Operation;

/**
 * Executes SHOW EVENTS: the events of a database, by name.
 *
 * LIKE matches the event name. A database that does not exist has no events. When the server
 * holds no event, or one and the statement has no LIKE clause, it reads the dictionary table of
 * events as a constant table and
 * describes the columns otherwise: the computed ones with 31 decimals, the times as text, and
 * with the keys of the dictionary tables (verified on a live 8.4 server). The originator is the
 * server id, 1.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-events.html.
 *
 * @visibility MySqlMemory
 */
final class ShowEventsCommand implements Command
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
     * Lists the events.
     */
    #[Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        assert($statement instanceof ShowEvents);
        $database = ProgramSource::database($statement->database, $session);
        $events = $session->instance->dictionary->schema($database)->events ?? [];
        usort($events, static fn (Event $left, Event $right): int => strtolower($left->name) <=> strtolower($right->name));
        $rows = array_map(static fn (Event $event): array => [
            $event->schema, $event->name, $event->definer[0] . '@' . $event->definer[1], $event->zone, $event->every === null ? 'ONE TIME' : 'RECURRING',
            $event->at, $event->every[0] ?? null, $event->every[1] ?? null, $event->starts, $event->ends, $event->status, 1, ...$event->charsets,
        ], $events);

        $total = array_sum(array_map(static fn ($schema): int => count($schema->events), $session->instance->dictionary->schemas));

        return (new Listing($this->headings($total === 0 || ($total === 1 && !$statement->filter instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowLike), $session->settings()->release() === GrammarRelease::MySql8044)))->result($rows, $operation, $session, $context, $connection, $statement->filter, 1);
    }

    /**
     * Answers the columns of the statement, as the server describes them when it reads the events as a constant table and otherwise.
     *
     * MySQL 8.0 sends Status as the ENUM column of the events it is, of 18 characters; later
     * releases compute it, as a string of 21 (verified on live 8.0 and 8.4 servers).
     *
     * @param bool $enumerated Whether Status is the ENUM column of the events, as in MySQL 8.0
     * @return list<Heading>
     */
    public function headings(bool $constant, bool $enumerated = false): array
    {
        $table = 'EVENTS';
        $schema = $constant ? 'information_schema' : '';
        $key = $constant ? 16384 : 0;
        $events = $constant ? 'evt' : 'events';
        $computed = $constant ? 31 : 0;
        $nameFlags = $constant ? 20485 : 4097;
        $time = static fn (string $name): Heading => $constant ? Heading::text($name, Field::DateTime, 19, 128, 0, $name, $table) : new Heading($name, Field::DateTime, 19, 128, 0, false, $name, $table);

        return [
            Heading::text('Db', Field::VarString, 64, 4225 | $key, 0, 'Db', $table, $constant ? 'sch' : 'schemata', $schema),
            Heading::text('Name', Field::VarString, 64, 4097 | $key, 0, 'Name', $table, $events, $schema),
            Heading::text('Definer', Field::VarString, 288, 4225 | $key | ($constant ? 8 : 0), 0, 'Definer', $table, $events, $schema),
            Heading::text('Time zone', Field::VarString, 64, 4225, 0, 'Time zone', $table, $events, $schema),
            Heading::text('Type', Field::VarString, 9, 1, $computed, 'Type', $table),
            $time('Execute at'),
            Heading::text('Interval value', Field::VarString, 256, 0, $computed, 'Interval value', $table),
            Heading::text('Interval field', Field::String, 18, 384, 0, 'Interval field', $table, $events, $schema),
            $time('Starts'),
            $time('Ends'),
            $enumerated ? Heading::text('Status', Field::String, 18, 4481, 0, 'Status', $table, $events, $schema) : Heading::text('Status', Field::VarString, 21, 129, $computed, 'Status', $table),
            new Heading('Originator', Field::Long, 10, 36897, 0, false, 'Originator', $table, $events, $schema),
            Heading::text('character_set_client', Field::VarString, 64, $nameFlags, 0, 'character_set_client', $table, $constant ? 'cs_client' : 'character_sets', $schema),
            Heading::text('collation_connection', Field::VarString, 64, $nameFlags, 0, 'collation_connection', $table, $constant ? 'coll_conn' : 'collations', $schema),
            Heading::text('Database Collation', Field::VarString, 64, $nameFlags, 0, 'Database Collation', $table, $constant ? 'coll_db' : 'collations', $schema),
        ];
    }
}
