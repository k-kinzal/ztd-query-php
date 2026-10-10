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
     * Closes the repositories and retains the closed relay log until a change replaces it.
     */
    public function reset(): void
    {
        $this->initialized = false;
        $this->applying = false;
        $this->closed = true;
        $this->missing = false;
    }
}
