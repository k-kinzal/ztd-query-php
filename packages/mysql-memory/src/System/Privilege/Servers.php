<?php

declare(strict_types=1);

namespace MySqlMemory\System\Privilege;

use MySqlMemory\System\Reading;
use MySqlMemory\System\SystemRows;
use Override;

/**
 * The rows of mysql.servers: the foreign servers CREATE SERVER defined, by name; an option not given is empty, and 0 for the port.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-server.html.
 *
 * @visibility MySqlMemory
 */
final class Servers implements SystemRows
{
    /**
     * Answers a row for each foreign server.
     */
    #[Override]
    public function rows(Reading $reading): array
    {
        $rows = [];
        foreach ($reading->instance->registry->servers as $server) {
            $option = static fn (string $name): string => (string) ($server->options[$name] ?? '');
            $rows[] = ['Server_name' => $server->name, 'Host' => $option('host'), 'Db' => $option('database'), 'Username' => $option('user'), 'Password' => $option('password'), 'Port' => (int) ($server->options['port'] ?? 0), 'Socket' => $option('socket'), 'Wrapper' => $server->wrapper, 'Owner' => $option('owner')];
        }
        usort($rows, static fn (array $left, array $right): int => $left['Server_name'] <=> $right['Server_name']);

        return $rows;
    }
}
