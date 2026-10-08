<?php

declare(strict_types=1);

namespace MySqlMemory\Registry;

use MySqlMemory\Typing\Ordering;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

/**
 * The server-wide objects of the emulated server besides databases and accounts: resource groups, foreign servers, spatial reference systems, tablespaces, the binary log and the prepared XA branches.
 *
 * The emulated server runs no replication: the default channel exists unconfigured, with its
 * receiver thread stopped. Its applier thread runs once START REPLICA SQL_THREAD starts it, until
 * STOP REPLICA.
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
     * Whether the applier thread of the default replication channel runs.
     */
    public bool $applying = false;

    /**
     * Creates the objects of a server that has just been installed.
     */
    public function __construct()
    {
        $this->resourceGroups = new ResourceGroups();
        $this->spatialCatalog = new SpatialCatalog();
        $this->binaryLog = new BinaryLog();
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
