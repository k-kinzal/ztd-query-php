<?php

declare(strict_types=1);

namespace MySqlMemory\Registry;

use MySqlMemory\Typing\Ordering;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

/**
 * The server-wide objects of the emulated server besides databases and accounts: resource groups, foreign servers, spatial reference systems, tablespaces, the binary log, the prepared XA branches, and the client threads with their user-level locks.
 *
 * The emulated server runs no replication: the default channel exists unconfigured, with its
 * receiver thread stopped. Its connection repository must be initialized before the applier
 * thread can start.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/replication-channels.html.
 *
 * @visibility MySqlMemory
 */
final class Registry
{
    /**
     * The resource groups and the assignments of the session threads.
     */
    public readonly ResourceGroups $resourceGroups;

    /**
     * The spatial reference systems.
     */
    public readonly SpatialCatalog $spatialCatalog;

    /**
     * The binary log files.
     */
    public readonly BinaryLog $binaryLog;

    /**
     * Global and per-connection SQL execution counters.
     */
    public readonly StatusCounters $status;

    /**
     * The sessions connected, their user-level locks and the clock of the server.
     */
    public readonly Threads $threads;

    /**
     * The event scheduler daemon's identity and wait state.
     */
    public readonly EventScheduler $eventScheduler;

    /**
     * @var array<string, ForeignServer> The foreign servers, by the key of their name
     */
    public array $servers = [];

    /**
     * @var array<string, Tablespace> The general and undo tablespaces statements created, by name
     */
    public array $tablespaces = [];

    /**
     * @var array<string, PreparedBranch> The prepared XA branches, by the key of their XID
     */
    public array $prepared = [];

    /**
     * The connection repository, relay log and applier of the default replication channel.
     */
    public readonly Replication $replication;

    /**
     * @var array<array-key, Replication> Named replication channels, separate from the permanent default channel; PHP stores numeric names as integer keys
     */
    public array $channels = [];

    /**
     * Creates the objects of a server that has just been installed.
     *
     * @param bool $resourcePriorities Whether the emulated platform applies thread priorities
     * @param positive-int $resourceProcessors The number of logical processors visible to resource groups
     * @param bool $replicationAvailableAtStartup Whether a nonzero startup server id initialized legacy replication; later SET GLOBAL does not change this state
     */
    public function __construct(bool $resourcePriorities = true, int $resourceProcessors = 8, string $version = '8.4.7', public readonly bool $replicationAvailableAtStartup = true)
    {
        $this->resourceGroups = new ResourceGroups($resourceProcessors, $resourcePriorities);
        $this->spatialCatalog = new SpatialCatalog($version);
        $this->binaryLog = new BinaryLog();
        $this->status = new StatusCounters();
        $this->threads = new Threads();
        $this->eventScheduler = new EventScheduler($this->threads);
        $this->replication = new Replication();
    }

    /**
     * Releases connection registrations and, for MySQL 5.6, its prepared XA branches.
     */
    public function disconnect(int $connection, bool $discardPrepared): void
    {
        $this->status->clear($connection);
        $this->threads->disconnect($connection);
        if ($discardPrepared) {
            foreach ($this->prepared as $key => $branch) {
                if ($branch->owner === $connection) {
                    unset($this->prepared[$key]);
                }
            }
        }
    }

    /**
     * Answers the key of an object name that compares in utf8mb3_general_ci, without regard to letter case and accents.
     *
     * @example Names that compare equal
     *     \MySqlMemory\Registry\Registry::key('Admin_É') === \MySqlMemory\Registry\Registry::key('admin_e') // => true
     */
    public static function key(string $name): string
    {
        return preg_match('/[\x80-\xFF]/', $name) === 1 ? Ordering::of(Collation::known('utf8mb3_general_ci'))->folded($name) : strtoupper($name);
    }
}
