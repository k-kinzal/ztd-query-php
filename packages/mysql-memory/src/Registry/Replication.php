<?php

declare(strict_types=1);

namespace MySqlMemory\Registry;

/**
 * The connection repository and relay log lifecycle of the default replication channel.
 *
 * CHANGE REPLICATION SOURCE initializes the repository after validating GTID settings, even
 * when a later option fails. RESET closes the repository and relay log. A subsequent change
 * normally replaces that log; an explicit relay position keeps it. These transitions were
 * observed through SQL on MySQL 8.0.44, 8.4.7 and 9.1.0, without reading server implementation.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/change-replication-source-to.html,
 * https://dev.mysql.com/doc/refman/8.4/en/reset-replica.html.
 *
 * @visibility MySqlMemory
 */
final class Replication
{
    /**
     * Whether the connection and relay repositories have been initialized.
     */
    public bool $initialized = false;

    /**
     * Whether the applier thread runs.
     */
    public bool $applying = false;

    /**
     * Whether the retained relay log ends with a Stop event.
     */
    public bool $closed = false;

    /**
     * Whether the configured relay filename is absent from the index.
     */
    public bool $missing = false;

    /**
     * Whether GTID auto-positioning is configured.
     */
    public bool $autoPosition = false;

    /**
     * Whether the channel requires row events.
     */
    public bool $requireRowFormat = false;

    /**
     * Whether the channel persists only GTID positions.
     */
    public bool $gtidOnly = false;

    /**
     * Whether automatic source failover is enabled.
     */
    public bool $autoFailover = false;

    /**
     * The unsigned connection port, including a value retained after repository storage fails.
     */
    public int $port = 3306;

    /**
     * Whether a failed reset prevents opening connection metadata for another change.
     */
    public bool $connectionFailure = false;

    /**
     * @var array<string, float> The effective heartbeat period of each configured channel
     */
    public array $heartbeatPeriods = [];

    /**
     * Closes the repositories and retains the closed relay log until a change replaces it.
     */
    public function reset(bool $all = false): void
    {
        if ($all) {
            $this->heartbeatPeriods = [];
            $this->port = 3306;
            $this->connectionFailure = false;
            $this->autoPosition = false;
            $this->requireRowFormat = false;
            $this->gtidOnly = false;
            $this->autoFailover = false;
        }
        $this->initialized = false;
        $this->applying = false;
        $this->closed = true;
        $this->missing = false;
    }
}
