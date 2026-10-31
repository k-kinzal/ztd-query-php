<?php

declare(strict_types=1);

namespace MySqlMemory\Dictionary;

/**
 * A scheduled event: when it runs, what it does, and what becomes of it after its last run.
 *
 * The emulator keeps events but never runs them. A one-time event has its time; a recurring
 * one its interval, the time it starts and the time it ends, if any. Event names are not
 * case-sensitive.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-event.html.
 *
 * @visibility MySqlMemory
 */
final class Event
{
    /**
     * @param string $schema The database of the event
     * @param string $name The name as it was created or renamed
     * @param array{string, string} $definer The user and host of the definer
     * @param string $zone The time zone the times are in
     * @param string|null $at When a one-time event runs, as `YYYY-MM-DD hh:mm:ss`; null for a recurring one
     * @param array{string, string}|null $every The quantity and the unit of the interval of a recurring event; null for a one-time one
     * @param string|null $starts When a recurring event starts
     * @param string|null $ends When a recurring event ends, or null for never
     * @param string $status ENABLED, DISABLED or SLAVESIDE_DISABLED
     * @param bool $preserve Whether the event is kept after its last run
     * @param string $comment The comment
     * @param string $body The text of the body
     * @param string $mode The sql_mode the event was created under
     * @param string $created When the event was created, as `YYYY-MM-DD hh:mm:ss`
     * @param string $modified When the event was last changed
     * @param array{string, string, string} $charsets The character_set_client, collation_connection and database collation the event was created with
     */
    public function __construct(
        public string $schema,
        public string $name,
        public array $definer,
        public readonly string $zone,
        public ?string $at,
        public ?array $every,
        public ?string $starts,
        public ?string $ends,
        public string $status,
        public bool $preserve,
        public string $comment,
        public string $body,
        public string $mode,
        public readonly string $created,
        public string $modified,
        public array $charsets,
    ) {
    }

    /**
     * Answers the statement SHOW CREATE EVENT writes for the event.
     */
    public function create(): string
    {
        $text = 'CREATE DEFINER=' . Routine::quoted($this->definer[0]) . '@' . Routine::quoted($this->definer[1]) . ' EVENT ' . Routine::quoted($this->name) . ' ON SCHEDULE ';
        if ($this->every === null) {
            $text .= "AT '" . $this->at . "'";
        } else {
            $text .= 'EVERY ' . $this->every[0] . ' ' . $this->every[1] . " STARTS '" . $this->starts . "'" . ($this->ends === null ? '' : " ENDS '" . $this->ends . "'");
        }
        $text .= ' ON COMPLETION ' . ($this->preserve ? 'PRESERVE' : 'NOT PRESERVE') . ' ' . match ($this->status) {
            'ENABLED' => 'ENABLE',
            'DISABLED' => 'DISABLE',
            default => 'DISABLE ON REPLICA',
        };
        if ($this->comment !== '') {
            $text .= ' COMMENT ' . Routine::literal($this->comment);
        }

        return $text . ' DO ' . $this->body;
    }
}
