<?php

declare(strict_types=1);

namespace MySqlMemory\System;

use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Instance;
use MySqlMemory\Session\Session;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Table\Catalog\SystemTable;

/**
 * What the rows of a system table are computed from: the server, the connection that reads the table, and the table read.
 *
 * @visibility MySqlMemory
 */
final class Reading
{
    /**
     * @param Instance $instance The server
     * @param Connection $connection The connection whose statement reads the table
     * @param SystemTable $table The table read
     * @param GrammarRelease $release The release emulated
     */
    public function __construct(public readonly Instance $instance, public readonly Connection $connection, public readonly SystemTable $table, public readonly GrammarRelease $release)
    {
    }

    /**
     * Answers the session that reads the table, or null when it is no longer open.
     */
    public function session(): ?Session
    {
        return ($this->instance->sessions[$this->connection->id] ?? null)?->get();
    }

    /**
     * Answers the open sessions, by connection id in ascending order: those not closed and still referenced.
     *
     * @return array<int, Session>
     */
    public function sessions(): array
    {
        $sessions = [];
        foreach ($this->instance->sessions as $id => $reference) {
            $session = $reference->get();
            if ($session !== null && isset($this->instance->registry->threads->connected[$id])) {
                $sessions[$id] = $session;
            }
        }
        ksort($sessions);

        return $sessions;
    }

    /**
     * Tells whether the release emulated is MySQL 8.0 or later, whose INFORMATION_SCHEMA is a set of views over the data dictionary.
     */
    public function dictionary(): bool
    {
        return $this->release !== GrammarRelease::MySql5651 && $this->release !== GrammarRelease::MySql5744;
    }
}
