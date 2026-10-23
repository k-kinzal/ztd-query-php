<?php

declare(strict_types=1);

namespace MySqlMemory\Registry;

use MySqlMemory\Dictionary\Dictionary;
use MySqlMemory\Dictionary\Event;
use MySqlMemory\Session\Globals;
use SqlSemantics\Platform\MySql\Statement\Variable\Catalog\SystemVariables;

/**
 * The event scheduler's daemon identity and current wait, separate from client connections.
 *
 * ON starts a daemon; OFF or DISABLED removes it. Starting an enabled event wakes the daemon
 * into its next-activation wait. Removing or disabling that event does not immediately wake
 * it (verified through PROCESSLIST on 8.4.7). Event bodies are not executed by this model.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/events-configuration.html,
 * https://dev.mysql.com/doc/refman/8.4/en/event-scheduler-thread-states.html.
 *
 * @visibility MySqlMemory
 */
final class EventScheduler
{
    private ?int $id = null;

    private float $started = 0.0;

    private string $state = 'Waiting on empty queue';

    /**
     * @param Threads $threads The shared identity allocator and clock
     */
    public function __construct(private readonly Threads $threads)
    {
    }

    /**
     * Reads the effective global setting after startup, restart or SET.
     */
    public function configured(Globals $globals, SystemVariables $catalog, Dictionary $dictionary): void
    {
        $definition = $catalog->find('event_scheduler');
        $this->configure($definition === null ? 'OFF' : (string) $globals->value($definition), $dictionary);
    }

    /**
     * Starts or stops the daemon according to the global event_scheduler value.
     */
    public function configure(string $value, Dictionary $dictionary): void
    {
        if (!in_array(strtoupper($value), ['ON', '1'], true)) {
            $this->stop();

            return;
        }
        if ($this->id !== null) {
            return;
        }
        $this->id = $this->threads->allocate();
        $this->started = $this->threads->now();
        $this->state = 'Waiting on empty queue';
        foreach ($dictionary->schemas as $schema) {
            foreach ($schema->events as $event) {
                $this->activated($event);
            }
        }
    }

    /**
     * Records the wakeup caused by creating or altering an enabled event.
     */
    public function activated(Event $event): void
    {
        if ($this->id !== null && $event->status === 'ENABLED') {
            $this->started = $this->threads->now();
            $this->state = 'Waiting for next activation';
        }
    }

    /**
     * Ends the daemon; a subsequent start allocates a new identity.
     */
    public function stop(): void
    {
        $this->id = null;
    }

    /**
     * Answers the daemon's process-list row, or null while stopped.
     *
     * @return array<string, int|string|null>|null
     * @phpstan-impure
     */
    public function row(): ?array
    {
        return $this->id === null ? null : [
            'ID' => $this->id,
            'USER' => 'event_scheduler',
            'HOST' => 'localhost',
            'DB' => null,
            'COMMAND' => 'Daemon',
            'TIME' => (int) floor($this->threads->now()) - (int) floor($this->started),
            'STATE' => $this->state,
            'INFO' => null,
            'EXECUTION_ENGINE' => 'PRIMARY',
        ];
    }
}
