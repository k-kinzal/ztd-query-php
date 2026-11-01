<?php

declare(strict_types=1);

namespace MySqlMemory\Session\State;

use MySqlMemory\Session\Variables;

/**
 * The sequence and start clock of a connection's last client statement.
 *
 * SQL EXECUTE retains its outer command's sequence, while wire preparation starts a new
 * statement. A pinned timestamp also controls the process list's elapsed time, even while
 * sleeping. Verified through the public SQL and prepared-statement interfaces on 8.4.7.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/server-system-variables.html#sysvar_statement_id,
 * https://dev.mysql.com/doc/refman/8.4/en/information-schema-processlist-table.html.
 *
 * @visibility MySqlMemory
 */
final class Activity
{
    private float $started;

    /**
     * @param Variables $variables The connection's variables and server clock
     */
    public function __construct(private readonly Variables $variables)
    {
        $this->started = $variables->instance->registry->threads->now();
    }

    /**
     * Starts a client statement, including one that will fail.
     */
    public function begin(): void
    {
        $threads = $this->variables->instance->registry->threads;
        $this->started = $threads->now();
        $this->variables->session['statement_id'] = ++$threads->statements;
    }

    /**
     * Answers whole clock seconds since the last statement, possibly negative for a future timestamp.
     */
    public function elapsed(): int
    {
        return (int) floor($this->variables->instance->registry->threads->now()) - (int) floor((float) ($this->variables->session['timestamp'] ?? $this->started));
    }
}
