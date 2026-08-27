<?php

declare(strict_types=1);

namespace ZtdQuery\Adapter\Mysqli;

use mysqli;

/**
 * Reads a mysqli connection's properties off the connection itself.
 *
 * mysqli answers its properties from the extension rather than declaring them,
 * so there is no reaching one by a name held in a variable: every name it has
 * is written out here.
 */
final class MysqliProperties implements ConnectionProperties
{
    /**
     * Every property mysqli answers, and nothing else.
     *
     * @var array<string, true>
     */
    private const NAMES = [
        'affected_rows' => true,
        'client_info' => true,
        'client_version' => true,
        'connect_errno' => true,
        'connect_error' => true,
        'errno' => true,
        'error' => true,
        'error_list' => true,
        'field_count' => true,
        'host_info' => true,
        'info' => true,
        'insert_id' => true,
        'protocol_version' => true,
        'server_info' => true,
        'server_version' => true,
        'sqlstate' => true,
        'thread_id' => true,
        'warning_count' => true,
    ];

    /**
     * Binds the reader to the connection it reads from.
     *
     * @param mysqli $connection Connection whose properties are answered
     */
    public function __construct(private readonly mysqli $connection)
    {
    }

    /**
     * {@inheritDoc}
     *
     * A name mysqli has no property under is answered as nothing rather than
     * read, because reading one raises a warning and answers nothing anyway.
     *
     * @param string $name Property as it was written
     *
     * @return mixed What the connection has under that name, or null where mysqli has no such property
     */
    public function named(string $name): mixed
    {
        return isset(self::NAMES[$name]) ? $this->connection->{$name} : null;
    }
}
