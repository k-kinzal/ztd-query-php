<?php

declare(strict_types=1);

namespace MySqlMemory\System\Program;

use MySqlMemory\Dictionary\Trigger;
use MySqlMemory\System\Listed;
use MySqlMemory\System\Reading;
use MySqlMemory\System\SystemRows;
use Override;

/**
 * The rows of INFORMATION_SCHEMA.TRIGGERS: one for each trigger of each database.
 *
 * The triggers of a database are listed as SHOW TRIGGERS lists them: by table, event and
 * timing, in the order they fire, which ACTION_ORDER numbers from 1 for each table, event and
 * timing (verified on a live 8.4.7 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/information-schema-triggers-table.html.
 *
 * @visibility MySqlMemory
 */
final class Triggers implements SystemRows
{
    /**
     * Answers a row for each trigger.
     */
    #[Override]
    public function rows(Reading $reading): array
    {
        $events = ['INSERT' => 0, 'UPDATE' => 1, 'DELETE' => 2];
        $rows = [];
        foreach (Listed::schemas($reading) as $schema) {
            $triggers = $schema->triggers;
            $order = array_flip(array_map(spl_object_id(...), $triggers));
            usort($triggers, static fn (Trigger $left, Trigger $right): int => [$left->table, $events[$left->event] ?? 3, $left->time === 'BEFORE' ? 0 : 1, $order[spl_object_id($left)]] <=> [$right->table, $events[$right->event] ?? 3, $right->time === 'BEFORE' ? 0 : 1, $order[spl_object_id($right)]]);
            $positions = [];
            foreach ($triggers as $trigger) {
                $key = $trigger->table . "\0" . $trigger->event . "\0" . $trigger->time;
                $positions[$key] = ($positions[$key] ?? 0) + 1;
                $rows[] = [
                    'TRIGGER_CATALOG' => 'def',
                    'TRIGGER_SCHEMA' => $schema->name,
                    'TRIGGER_NAME' => $trigger->name,
                    'EVENT_MANIPULATION' => $trigger->event,
                    'EVENT_OBJECT_CATALOG' => 'def',
                    'EVENT_OBJECT_SCHEMA' => $schema->name,
                    'EVENT_OBJECT_TABLE' => $trigger->table,
                    'ACTION_ORDER' => $positions[$key],
                    'ACTION_CONDITION' => null,
                    'ACTION_STATEMENT' => $trigger->body,
                    'ACTION_ORIENTATION' => 'ROW',
                    'ACTION_TIMING' => $trigger->time,
                    'ACTION_REFERENCE_OLD_TABLE' => null,
                    'ACTION_REFERENCE_NEW_TABLE' => null,
                    'ACTION_REFERENCE_OLD_ROW' => 'OLD',
                    'ACTION_REFERENCE_NEW_ROW' => 'NEW',
                    'CREATED' => $trigger->created,
                    'SQL_MODE' => $trigger->mode,
                    'DEFINER' => $trigger->definer[0] . '@' . $trigger->definer[1],
                    'CHARACTER_SET_CLIENT' => $trigger->charsets[0],
                    'COLLATION_CONNECTION' => $trigger->charsets[1],
                    'DATABASE_COLLATION' => $trigger->charsets[2],
                ];
            }
        }

        return $rows;
    }
}
