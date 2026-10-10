<?php

declare(strict_types=1);

namespace MySqlMemory;

use MySqlMemory\Account\Accounts;
use MySqlMemory\Dictionary\Dictionary;
use MySqlMemory\Dictionary\Schema;
use MySqlMemory\Registry\Registry;
use MySqlMemory\Session\Globals;
use MySqlMemory\Session\Session;
use MySqlMemory\System\SystemSchemas;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Variable\Catalog\SystemVariables;
use WeakReference;

/**
 * An in-memory MySQL server: its databases, its global variables, and the sessions connected to it.
 *
 * Statements are parsed by SQL Parser with the grammar of the emulated release, resolved by SQL
 * Semantics against the tables of the server, and executed over rows held in memory. Nothing is
 * written to disk; the server lives as long as this object.
 *
 * @visibility public
 * @example Running statements in a session
 *     $session = (new \MySqlMemory\Instance())->connect();
 *     $session->query('CREATE DATABASE shop');
 *     $session->query('USE shop');
 *     $session->query('CREATE TABLE items (id INT PRIMARY KEY, name VARCHAR(20))');
 *     $session->query("INSERT INTO items VALUES (1, 'pen'), (2, 'ink')");
 *     $session->query('SELECT name FROM items ORDER BY id DESC')[0]->rows // => [['ink'], ['pen']]
 */
final class Instance
{
    /**
     * The databases of the server and the tables they hold.
     */
    public readonly Dictionary $dictionary;

    /**
     * The global values of the system variables, shared by every session.
     */
    public readonly Globals $globals;

    /**
     * The system variables the emulated release knows.
     */
    public readonly SystemVariables $catalog;

    /**
     * The accounts and roles of the server, their privileges and roles.
     */
    public readonly Accounts $accounts;

    /**
     * The resource groups, foreign servers, spatial reference systems, tablespaces, binary log and prepared XA branches of the server.
     */
    public readonly Registry $registry;

    /**
     * @var array<int, WeakReference<Session>> The sessions opened, by connection id; a session no longer referenced has ended
     */
    public array $sessions = [];

    /**
     * The time the server started, in seconds since the epoch.
     */
    public float $started;

    /**
     * Whether SHUTDOWN has stopped the instance.
     */
    public bool $stopped = false;

    /**
     * @var array<string, string|int> The global configuration reloaded by RESTART
     */
    private readonly array $startup;

    /**
     * The transactions of the sessions, their row locks and the row versions their snapshots read.
     */
    public readonly Concurrency\Transactions $transactions;

    private int $connections = 0;

    /**
     * A server of MySQL 5.6 or 5.7 starts with latin1 connections, as it greets its clients in latin1
     * (verified on live 5.6.51 and 5.7.44 servers).
     *
     * @param string $version The MySQL release emulated, as `8.4.7`
     * @param array<string, string|int> $globals Global variable values the server starts with, by name
     * @param list<string> $databases Databases created at start, besides the system ones
     * @param string|null $clientHost The host every client is seen connecting from, or null for its address
     * @param bool $supervised Whether a supervisor permits SQL RESTART
     * @param array<string, array{int, int}> $routineTimestamps Installation creation and modification epochs, keyed by FUNCTION:name or PROCEDURE:name
     */
    public function __construct(public readonly string $version = '8.4.7', array $globals = [], array $databases = [], public readonly ?string $clientHost = null, public readonly bool $supervised = true, array $routineTimestamps = [])
    {
        $release = GrammarRelease::tryFrom('mysql-' . $version) ?? GrammarRelease::MySql847;
        $this->catalog = SystemVariables::of($release);
        $globals = array_change_key_case($globals, CASE_LOWER);
        if ($release === GrammarRelease::MySql5651 || $release === GrammarRelease::MySql5744) {
            $globals += ['character_set_client' => 'latin1', 'character_set_connection' => 'latin1', 'character_set_results' => 'latin1', 'collation_connection' => 'latin1_swedish_ci'];
        }
        $this->startup = $globals;
        $this->globals = new Globals($globals);
        $this->dictionary = new Dictionary();
        $this->accounts = Accounts::installed($release);
        $this->registry = new Registry();
        $this->transactions = new Concurrency\Transactions();
        $legacy = $release === GrammarRelease::MySql5651 || $release === GrammarRelease::MySql5744;
        $system = $legacy ? ['information_schema' => 'utf8mb3_general_ci', 'mysql' => 'latin1_swedish_ci', 'performance_schema' => 'utf8mb3_general_ci', 'sys' => 'utf8mb3_general_ci'] : ['information_schema' => 'utf8mb3_general_ci', 'mysql' => 'utf8mb4_0900_ai_ci', 'performance_schema' => 'utf8mb4_0900_ai_ci', 'sys' => 'utf8mb4_0900_ai_ci'];
        if ($release === GrammarRelease::MySql5651) {
            unset($system['sys']);
        }
        foreach ($system as $name => $collation) {
            $this->dictionary->schemas[$name] = new Schema($name, $collation);
        }
        if (isset($this->dictionary->schemas['sys'])) {
            \MySqlMemory\Dictionary\Program\InstalledCatalog::install($this->dictionary->schemas['sys'], $release, $routineTimestamps);
        }
        foreach ($databases as $name) {
            $this->dictionary->schemas[$name] ??= new Schema($name);
        }
        $this->dictionary->system = new SystemSchemas($this, $release);
        $this->started = microtime(true);
        $this->registry->eventScheduler->configured($this->globals, $this->catalog, $this->dictionary);
    }

    /**
     * Opens a session, as a client connection does.
     *
     * @param string $user The user name the session authenticates as
     * @param string $host The host the session connects from
     * @param string|null $database The database to use, or null for none
     * @param int|null $port The client's TCP source port, or null for a local session
     * @param int|null $threadId The identity already allocated for a wire greeting, or null to allocate one
     *
     * @throws Error\SqlError When the database does not exist
     */
    public function connect(string $user = 'root', string $host = 'localhost', ?string $database = null, ?int $port = null, ?int $threadId = null): Session
    {
        if ($this->stopped) {
            throw Error\Family\TransactionError::ServerGone->error();
        }
        ++$this->connections;
        $session = new Session($this, $this->registry->threads->allocate($threadId), $user, $host, $database, $port);
        $this->sessions[$session->id] = WeakReference::create($session);

        return $session;
    }

    /**
     * Resets a connection's session state while keeping its wire identity, peer and database.
     *
     * @visibility MySqlMemory
     * @throws Error\SqlError When the selected database no longer exists
     */
    public function reset(Session $session): Session
    {
        $session->close();
        $fresh = new Session($this, $session->id, $session->user, $session->host, $session->variables->database === '' ? null : $session->variables->database, $session->port);
        $this->sessions[$fresh->id] = WeakReference::create($fresh);

        return $fresh;
    }

    /**
     * Stops accepting sessions and disconnects every session, rolling back open transactions.
     *
     * Source: https://dev.mysql.com/doc/refman/8.4/en/shutdown.html.
     */
    public function shutdown(): void
    {
        $this->stopped = true;
        $this->registry->eventScheduler->stop();
        foreach ($this->sessions as $reference) {
            $session = $reference->get();
            if ($session !== null) {
                $session->interrupted = true;
                $session->close();
            }
        }
    }

    /**
     * Restarts under a supervisor, retaining durable tables and reloading the startup configuration.
     *
     * Open transactions roll back, session state disappears and MEMORY tables lose their rows.
     * Source: https://dev.mysql.com/doc/refman/8.4/en/restart.html,
     * https://dev.mysql.com/doc/refman/8.4/en/memory-storage-engine.html.
     *
     * @throws Error\SqlError When the instance has no supervisor
     */
    public function restart(): void
    {
        if (!$this->supervised) {
            $error = Error\Family\AdministrationError::RestartFailed;
            throw new Error\SqlError($error, $error->message('mysqld is not managed by supervisor process'), following: [[$error->value, $error->message('Restart server failed')]]);
        }
        $this->shutdown();
        $this->registry->status->reset();
        $this->registry->threads->resetMaximum();
        $this->connections = 0;
        $this->globals->values = $this->startup;
        $this->globals->caches = [];
        $this->dictionary->discardVolatileRows();
        $this->started = microtime(true);
        $this->stopped = false;
        $this->registry->eventScheduler->configured($this->globals, $this->catalog, $this->dictionary);
    }

    /**
     * Answers the number of client connections opened so far, excluding resets and internal threads.
     */
    public function connections(): int
    {
        return $this->connections;
    }

}
