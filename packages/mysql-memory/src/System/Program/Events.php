<?php

declare(strict_types=1);

namespace MySqlMemory\System\Program;

use MySqlMemory\Dictionary\Event;
use MySqlMemory\System\Listed;
use MySqlMemory\System\Reading;
use MySqlMemory\System\SystemRows;
use Override;

/**
 * The rows of INFORMATION_SCHEMA.EVENTS: one for each event of each database, by name without regard to case.
 *
 * A one-time event has its time and no interval, a recurring one its interval and the times it
 * starts and ends. The event scheduler of the emulator runs no event, so no event has been
 * executed; the originator is the server_id of the server (verified on live 5.7.44 and 8.4.7
 * servers).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/information-schema-events-table.html.
 *
 * @visibility MySqlMemory
 */
final class Events implements SystemRows
{
    /**
     * Answers a row for each event.
     */
    #[Override]
    public function rows(Reading $reading): array
    {
        $rows = [];
        foreach (Listed::schemas($reading) as $schema) {
            $events = array_values($schema->events);
            usort($events, static fn (Event $left, Event $right): int => strtolower($left->name) <=> strtolower($right->name));
            foreach ($events as $event) {
                $rows[] = [
                    'EVENT_CATALOG' => 'def',
                    'EVENT_SCHEMA' => $schema->name,
                    'EVENT_NAME' => $event->name,
                    'DEFINER' => $event->definer[0] . '@' . $event->definer[1],
                    'TIME_ZONE' => $event->zone,
                    'EVENT_BODY' => 'SQL',
                    'EVENT_DEFINITION' => $event->body,
                    'EVENT_TYPE' => $event->every === null ? 'ONE TIME' : 'RECURRING',
                    'EXECUTE_AT' => $event->at,
                    'INTERVAL_VALUE' => $event->every[0] ?? null,
                    'INTERVAL_FIELD' => $event->every[1] ?? null,
                    'SQL_MODE' => $event->mode,
                    'STARTS' => $event->starts,
                    'ENDS' => $event->ends,
                    'STATUS' => $event->status,
                    'ON_COMPLETION' => $event->preserve ? 'PRESERVE' : 'NOT PRESERVE',
                    'CREATED' => $event->created,
                    'LAST_ALTERED' => $event->modified,
                    'LAST_EXECUTED' => null,
                    'EVENT_COMMENT' => $event->comment,
                    'ORIGINATOR' => (int) $reading->connection->variables->read('server_id'),
                    'CHARACTER_SET_CLIENT' => $event->charsets[0],
                    'COLLATION_CONNECTION' => $event->charsets[1],
                    'DATABASE_COLLATION' => $event->charsets[2],
                ];
            }
        }

        return $rows;
    }
}
