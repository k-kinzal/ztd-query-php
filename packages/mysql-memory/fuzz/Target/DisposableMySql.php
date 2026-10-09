<?php

declare(strict_types=1);

namespace Fuzz\Target;

use Container\MySqlContainer;
use Container\MySqlRelease;
use Override;
use Testcontainers\Containers\ReuseMode;

/**
 * A fresh Testcontainers database for a statement that stops or restarts its server.
 *
 * ADD prevents a lifecycle input from stopping a container another test or fuzz input uses.
 */
final class DisposableMySql extends MySqlContainer
{
    /**
     * Uses the repository's pinned image for the requested release.
     */
    public function __construct(string $version)
    {
        $definition = MySqlRelease::container($version);
        parent::__construct((new $definition())->image());
        if ($this->supervised()) {
            $this->withCommands(['bash', '-c', <<<'SH'
                export MYSQLD_PARENT_PID=$$
                trap 'kill -TERM "$mysql_child" 2>/dev/null; wait "$mysql_child"; exit' TERM INT
                while :; do
                    /entrypoint.sh mysqld &
                    mysql_child=$!
                    wait "$mysql_child"
                    mysql_status=$?
                    if [ "$mysql_status" -ne 16 ]; then exit "$mysql_status"; fi
                done
                SH]);
        }
    }

    /**
     * Always starts a new container.
     */
    #[Override]
    public function reuseMode(): ReuseMode
    {
        return ReuseMode::ADD();
    }

    /**
     * Runs the Oracle images under a supervisor that restarts mysqld after its documented exit code 16.
     * The Docker Official Images run directly and reject RESTART with error 3707.
     * Source: https://dev.mysql.com/doc/refman/8.4/en/restart.html.
     */
    public function supervised(): bool
    {
        return !str_starts_with($this->image(), 'mysql:');
    }
}
