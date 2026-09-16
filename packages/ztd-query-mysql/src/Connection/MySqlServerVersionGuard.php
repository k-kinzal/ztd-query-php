<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Connection;

use RuntimeException;
use ZtdQuery\Connection\ConnectionInterface;

/**
 * Enforces the MySQL server requirement for CTE shadowing.
 */
final class MySqlServerVersionGuard
{
    /**
     * Reject unsupported servers before reflecting schema or creating a session.
     *
     * @throws RuntimeException When the server version is unavailable or unsupported.
     */
    public function validate(ConnectionInterface $connection): void
    {
        $statement = $connection->query('SELECT VERSION() AS ztd_server_version');
        $version = $statement === false ? null : ($statement->fetchAll()[0]['ztd_server_version'] ?? null);

        if (!is_string($version) || preg_match('/^(\d+\.\d+\.\d+)(?:\D.*)?$/', $version, $matches) !== 1) {
            throw new RuntimeException('Unable to determine the MySQL server version; ZTD requires MySQL 8.0.11 or later with WITH (CTE) support.');
        }

        if (stripos($version, 'MariaDB') !== false || version_compare($matches[1], '8.0.11', '<')) {
            throw new RuntimeException(sprintf(
                'ZTD requires MySQL 8.0.11 or later with WITH (CTE) support; server reported "%s".',
                $version,
            ));
        }
    }
}
