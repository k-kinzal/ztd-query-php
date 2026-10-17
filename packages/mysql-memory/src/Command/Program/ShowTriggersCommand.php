<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Program;

use MySqlMemory\Command\Command;
use MySqlMemory\Command\Show\Heading;
use MySqlMemory\Command\Show\Listing;
use MySqlMemory\Dictionary\Trigger;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use Override;
use SqlSemantics\Contract\GrammarRelease;
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
            if ($session->settings()->legacy()) {
                $listing = new Listing($this->headings($session->settings()->release()));
                $result = $listing->result([], $operation, $session, $context, $connection, $statement->filter, 2);
                if ($listing->constantEmpty) {
                    return $result;
                }
            }
            throw QueryError::BadDatabase->error($database);
        }
        $triggers = $schema->triggers;
        $events = ['INSERT' => 0, 'UPDATE' => 1, 'DELETE' => 2];
        $order = array_flip(array_map(spl_object_id(...), $triggers));
        usort($triggers, static fn (Trigger $left, Trigger $right): int => [$left->table, $events[$left->event] ?? 3, $left->time === 'BEFORE' ? 0 : 1, $order[spl_object_id($left)]] <=> [$right->table, $events[$right->event] ?? 3, $right->time === 'BEFORE' ? 0 : 1, $order[spl_object_id($right)]]);
        $release = $session->settings()->release();
        $rows = array_map(static fn (Trigger $trigger): array => [$trigger->name, $trigger->event, $trigger->table, $trigger->body, $trigger->time, $release === GrammarRelease::MySql5651 ? null : \MySqlMemory\System\Program\RoutineTimes::local($trigger->created, $context, 2), $trigger->mode, $trigger->definer[0] . '@' . $trigger->definer[1], ...$trigger->charsets], $triggers);
        $listing = new Listing($this->headings($release));
        $result = $listing->result($rows, $operation, $session, $context, $connection, $statement->filter, 2);

        return $listing->constantEmpty ? (new Listing($this->headings($release, true)))->sent([], $context) : $result;
    }

    /**
     * Describes the result, including the unmaterialized metadata of a constant-false filter.
     *
     * @return list<Heading>
     */
    public function headings(GrammarRelease $release, bool $direct = false): array
    {
        if ($release === GrammarRelease::MySql5651 || $release === GrammarRelease::MySql5744) {
            return $this->legacy($release);
        }
        $table = 'TRIGGERS';
        $headings = [
            Heading::text('Trigger', Field::VarString, 64, 4097, 0, 'Trigger', $table, 'triggers'),
            Heading::text('Event', Field::String, 6, 4481, 0, 'Event', $table, 'triggers'),
            Heading::text('Table', Field::VarString, 64, 4225, 0, 'Table', $table, 'tables'),
            Heading::text('Statement', Field::Blob, 4294967295, 4241, 0, 'Statement', $table, 'triggers'),
            Heading::text('Timing', Field::String, 6, 4481, 0, 'Timing', $table, 'triggers'),
            new Heading('Created', Field::Timestamp, 22, 4225, 2, $direct, 'Created', $table, 'triggers'),
            Heading::text('sql_mode', Field::String, 520, 6273, 0, 'sql_mode', $table, 'triggers'),
            Heading::text('Definer', Field::VarString, 288, 4225, 0, 'Definer', $table, 'triggers'),
            Heading::text('character_set_client', Field::VarString, 64, 4097, 0, 'character_set_client', $table, 'character_sets'),
            Heading::text('collation_connection', Field::VarString, 64, 4097, 0, 'collation_connection', $table, 'collations'),
            Heading::text('Database Collation', Field::VarString, 64, 4097, 0, 'Database Collation', $table, 'collations'),
        ];
        if (!$direct) {
            return $headings;
        }
        $origins = ['Table' => 'tbl', 'character_set_client' => 'cs_client', 'collation_connection' => 'coll_conn', 'Database Collation' => 'coll_db'];
        $flags = ['Trigger' => 16384, 'Event' => 16384, 'Table' => 16384, 'Timing' => 16384, 'Definer' => 16392, 'character_set_client' => 16388, 'collation_connection' => 16388, 'Database Collation' => 16388];

        return array_map(static fn (Heading $heading): Heading => new Heading($heading->name, $heading->field, $heading->length, $heading->flags | ($flags[$heading->name] ?? 0), $heading->decimals, $heading->text, $heading->originalName, $table, $origins[$heading->name] ?? 'trg', 'information_schema', $heading->collation), $headings);
    }

    /**
     * Describes the legacy INFORMATION_SCHEMA table used by SHOW TRIGGERS.
     *
     * @return list<Heading>
     */
    public function legacy(GrammarRelease $release): array
    {
        $table = 'TRIGGERS';
        $oldest = $release === GrammarRelease::MySql5651;

        return [
            Heading::text('Trigger', Field::VarString, 64, 1, 0, 'TRIGGER_NAME', $table, $table, 'information_schema'),
            Heading::text('Event', Field::VarString, 6, 1, 0, 'EVENT_MANIPULATION', $table, $table, 'information_schema'),
            Heading::text('Table', Field::VarString, 64, 1, 0, 'EVENT_OBJECT_TABLE', $table, $table, 'information_schema'),
            Heading::text('Statement', Field::Blob, 196605, 17, 0, 'ACTION_STATEMENT', $table, $table, 'information_schema'),
            Heading::text('Timing', Field::VarString, 6, 1, 0, 'ACTION_TIMING', $table, $table, 'information_schema'),
            new Heading('Created', Field::DateTime, $oldest ? 19 : 22, 128, $oldest ? 0 : 2, false, 'CREATED', $table, $table, 'information_schema'),
            Heading::text('sql_mode', Field::VarString, 8192, 1, 0, 'SQL_MODE', $table, $table, 'information_schema'),
            Heading::text('Definer', Field::VarString, $oldest ? 77 : 93, 1, 0, 'DEFINER', $table, $table, 'information_schema'),
            Heading::text('character_set_client', Field::VarString, 32, 1, 0, 'CHARACTER_SET_CLIENT', $table, $table, 'information_schema'),
            Heading::text('collation_connection', Field::VarString, 32, 1, 0, 'COLLATION_CONNECTION', $table, $table, 'information_schema'),
            Heading::text('Database Collation', Field::VarString, 32, 1, 0, 'DATABASE_COLLATION', $table, $table, 'information_schema'),
        ];
    }
}
