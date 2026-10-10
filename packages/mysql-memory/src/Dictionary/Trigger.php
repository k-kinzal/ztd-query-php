<?php

declare(strict_types=1);

namespace MySqlMemory\Dictionary;

use SqlSemantics\Platform\MySql\Statement\Routine\CreateTrigger;

/**
 * A trigger: the table, time and event it fires on, its order among the triggers of the same time and event, and its body.
 *
 * Trigger names are not case-sensitive and are unique in a database; the body is kept as it was
 * written.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-trigger.html.
 *
 * @visibility MySqlMemory
 */
final class Trigger
{
    /**
     * @param string $schema The database of the trigger and of its table
     * @param string $name The name as it was created
     * @param string $table The name of the table
     * @param string $time BEFORE or AFTER
     * @param string $event INSERT, UPDATE or DELETE
     * @param array{string, string} $definer The user and host of the definer
     * @param string $body The text of the body
     * @param string $mode The sql_mode the trigger was created under
     * @param string $created When the trigger was created, as `YYYY-MM-DD hh:mm:ss.ff`
     * @param array{string, string, string} $charsets The character_set_client, collation_connection and database collation the trigger was created with
     * @param CreateTrigger $statement The statement that created the trigger
     */
    public function __construct(
        public readonly string $schema,
        public readonly string $name,
        public readonly string $table,
        public readonly string $time,
        public readonly string $event,
        public readonly array $definer,
        public readonly string $body,
        public readonly string $mode,
        public readonly string $created,
        public readonly array $charsets,
        public readonly CreateTrigger $statement,
    ) {
    }

    /**
     * Answers the statement SHOW CREATE TRIGGER writes for the trigger.
     */
    public function create(): string
    {
        return 'CREATE DEFINER=' . Routine::quoted($this->definer[0]) . '@' . Routine::quoted($this->definer[1]) . ' TRIGGER ' . Routine::quoted($this->name) . ' ' . $this->time . ' ' . $this->event . ' ON ' . Routine::quoted($this->table) . ' FOR EACH ROW ' . $this->body;
    }
}
