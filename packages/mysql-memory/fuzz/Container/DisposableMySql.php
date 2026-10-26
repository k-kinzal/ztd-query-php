<?php

declare(strict_types=1);

namespace Fuzz\Container;

use Container\MySqlContainer;
use Container\MySqlRelease;
use Override;
use RuntimeException;
use Testcontainers\Containers\ContainerInstance;
use Testcontainers\Containers\ReuseMode;
use Testcontainers\Containers\StartupCheckStrategy\StartupCheckStrategy;
use Testcontainers\Containers\WaitStrategy\WaitStrategy;

/**
 * A fresh Testcontainers database owned by one campaign or lifecycle observation.
 *
 * ADD prevents restoration and lifecycle inputs from changing another campaign's server.
 */
final class DisposableMySql extends MySqlContainer
{
    /**
     * The Docker instance acquired before startup checks or SQL readiness can fail.
     */
    public ?ContainerInstance $startedInstance = null;

    /**
     * A companion that reached SQL readiness; its earlier acquired instance is tracked on its definition.
     */
    public ?ContainerInstance $companionInstance = null;

    /**
     * Uses the repository's pinned image for the requested release.
     *
     * @throws RuntimeException When no pinned image exists for the requested release
     */
    public function __construct(string $version, private readonly ?self $companion = null)
    {
        $definition = MySqlRelease::container($version);
        parent::__construct((new $definition())->image());
        $port = filter_var(getenv('MYSQL_MEMORY_CONTAINER_PORT'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
        if (is_int($port)) {
            $this->withPortStrategy(new \Testcontainers\Containers\PortStrategy\StaticPortStrategy($port));
        }
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
     * Stops an acquired instance immediately when startup fails. Exception traces
     * can retain SDK instances, so destruction is not a sufficient cleanup boundary.
     */
    #[Override]
    public function start(): ContainerInstance
    {
        $ready = false;
        $this->startedInstance = null;
        try {
            $instance = parent::start();
            $ready = true;
            return $instance;
        } finally {
            if (!$ready) {
                $this->dispose();
            }
        }
    }

    /**
     * Releases acquired instances even when startup never reached SQL readiness.
     * Companion cleanup is attempted even if stopping this instance fails.
     */
    public function dispose(): void
    {
        try {
            $this->startedInstance?->stop();
        } finally {
            $this->companion?->dispose();
        }
    }

    /**
     * Acquires ownership before the first startup check without replacing that check.
     *
     * @param ContainerInstance $instance The Docker instance that has just started
     */
    #[Override]
    protected function startupCheckStrategy($instance): ?StartupCheckStrategy
    {
        $this->startedInstance = $instance;
        return parent::startupCheckStrategy($instance);
    }

    /**
     * Runs both independent initializations together without skipping either SQL readiness check.
     *
     * @param ContainerInstance $instance The running first container
     */
    #[Override]
    protected function waitStrategy($instance): WaitStrategy
    {
        $readiness = parent::waitStrategy($instance);
        return $this->companion === null ? $readiness : new CompanionStartup($this, $this->companion, $readiness);
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
