<?php

declare(strict_types=1);

namespace MySqlMemory\Server;

use MySqlMemory\Command\Show\Server\ShowVariablesCommand;
use MySqlMemory\Session\State\StatementCounters;

/**
 * The COM_STATISTICS text returned by mysqli::stat() and PDO's server-info attribute.
 *
 * Its Questions field reads global Queries, including this packet and stored program work.
 * The session Questions counter does not count this request. Uptime observes
 * SET timestamp and the average truncates to three decimal places. Diagnostics and ROW_COUNT
 * remain unchanged. Verified through the public wire interface on 8.0, 8.4 and 9.1.
 * Storage counters retain the same limitations as SHOW STATUS.
 * Source: https://dev.mysql.com/doc/c-api/8.4/en/mysql-stat.html.
 *
 * @visibility MySqlMemory
 */
final class Statistics
{
    /**
     * @param Client $client The authenticated connection
     */
    public function __construct(public readonly Client $client)
    {
    }

    /**
     * Sends the current status fields, without an OK packet or result-set wrapper.
     */
    public function answer(): bool
    {
        $session = $this->client->session();
        $session->activity->begin();
        StatementCounters::received($session, false);
        $values = array_column((new ShowVariablesCommand())->status($session, true), 1, 0);
        $uptime = $values['Uptime'] ?? '0';
        $queries = $values['Queries'] ?? '0';
        $fields = [
            'Uptime' => $uptime,
            'Threads' => $values['Threads_connected'] ?? '0',
            'Questions' => $queries,
            'Slow queries' => $values['Slow_queries'] ?? '0',
            'Opens' => $values['Opened_tables'] ?? '0',
            'Flush tables' => $values['Flush_commands'] ?? '0',
            'Open tables' => $values['Open_tables'] ?? '0',
            'Queries per second avg' => $uptime === '0' ? '0.000' : bcdiv($queries, $uptime, 3),
        ];
        $parts = [];
        foreach ($fields as $name => $value) {
            $parts[] = $name . ': ' . $value;
        }

        return $this->client->send(implode('  ', $parts));
    }
}
