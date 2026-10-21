<?php

declare(strict_types=1);

namespace Fuzz\Structure\Audit\Table;

use Container\Endpoint;
use mysqli;
use mysqli_result;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Installs the pinned container's zone catalog as explicit initial fixture state.
 * The native public utility loads its operating-system data; Memory receives the
 * corresponding named-catalog setting through the normal fixture discovery.
 */
final class TimeZoneCatalog
{
    /**
     * Loads the native catalog before the observed client session is created.
     *
     * @throws RuntimeException When the public loader fails or UTC remains unavailable
     */
    public function install(string $container, Endpoint $endpoint): void
    {
        $load = new Process(['docker', 'exec', '-e', 'MYSQL_PWD=' . $endpoint->password, $container, 'sh', '-c', 'mysql_tzinfo_to_sql /usr/share/zoneinfo | mysql --user=root mysql']);
        $load->setTimeout(60);
        $load->mustRun();
        $connection = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
        try {
            $result = $connection->query("SELECT CONVERT_TZ('2024-01-01 00:00:00','UTC','+00:00')");
            if (!$result instanceof mysqli_result || $result->fetch_row() !== ['2024-01-01 00:00:00']) {
                throw new RuntimeException('The native time-zone catalog fixture did not supply UTC.');
            }
        } finally {
            $connection->close();
        }
    }
}
