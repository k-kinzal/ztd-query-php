<?php

declare(strict_types=1);

namespace MySqlMemory\Registry;

/**
 * A foreign server CREATE SERVER defines, as a row of mysql.servers holds it.
 *
 * An option the statement does not give is empty, and PORT is 0.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-server.html.
 *
 * @visibility MySqlMemory
 */
final class ForeignServer
{
    /**
     * @param string $name The server name, as the statement that created it wrote it
     * @param string $wrapper The FOREIGN DATA WRAPPER name
     * @param array<string, string|int> $options The value of each option, by lower-case option name: host, database, user, password, socket, owner and port
     */
    public function __construct(public readonly string $name, public readonly string $wrapper, public array $options = [])
    {
    }
}
