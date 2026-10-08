<?php

declare(strict_types=1);

namespace MySqlMemory\System\Server;

use MySqlMemory\Session\Session;
use MySqlMemory\System\Reading;
use MySqlMemory\System\SystemRows;
use Override;

/**
 * The rows of INFORMATION_SCHEMA.PROCESSLIST and performance_schema.processlist: one for each session connected, by connection id.
 *
 * The session that reads the table is running a Query, executing the statement it reads it
 * with; every other session is in Sleep, with an empty state and no statement. The emulator
 * runs no background thread, so the event scheduler daemon the server lists is not listed, and
 * it does not count the seconds a session has spent in its state (verified on live 5.7.44,
 * 8.0.44 and 8.4.7 servers).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/information-schema-processlist-table.html,
 * https://dev.mysql.com/doc/refman/8.4/en/performance-schema-processlist-table.html.
 *
 * @visibility MySqlMemory
 */
final class Processlist implements SystemRows
{
    /**
     * Answers a row for each session.
     */
    #[Override]
    public function rows(Reading $reading): array
    {
        $rows = [];
        foreach ($reading->sessions() as $id => $session) {
            $rows[] = self::row($session, $id === $reading->connection->id, 'executing');
        }

        return $rows;
    }

    /**
     * Answers the row of a session.
     *
     * @param bool $current Whether the session is the one that reads the list
     * @param string $state The state of the session that reads the list
     *
     * @return array<string, int|string|null>
     */
    public static function row(Session $session, bool $current, string $state): array
    {
        return [
            'ID' => $session->id,
            'USER' => $session->user,
            'HOST' => $session->host,
            'DB' => $session->variables->database === '' ? null : $session->variables->database,
            'COMMAND' => $current ? 'Query' : 'Sleep',
            'TIME' => 0,
            'STATE' => $current ? $state : '',
            'INFO' => $current ? $session->text : null,
            'EXECUTION_ENGINE' => 'PRIMARY',
        ];
    }
}
