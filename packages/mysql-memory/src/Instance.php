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
    public readonly float $started;

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
     */
    public function __construct(public readonly string $version = '8.4.7', array $globals = [], array $databases = [], public readonly ?string $clientHost = null)
    {
        $release = GrammarRelease::tryFrom('mysql-' . $version) ?? GrammarRelease::MySql847;
        $this->catalog = SystemVariables::of($release);
        $globals = array_change_key_case($globals, CASE_LOWER);
        if ($release === GrammarRelease::MySql5651 || $release === GrammarRelease::MySql5744) {
            $globals += ['character_set_client' => 'latin1', 'character_set_connection' => 'latin1', 'character_set_results' => 'latin1', 'collation_connection' => 'latin1_swedish_ci'];
        }
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
        foreach ($databases as $name) {
            $this->dictionary->schemas[$name] ??= new Schema($name);
        }
        $this->dictionary->system = new SystemSchemas($this, $release);
        $this->started = microtime(true);
    }

    /**
     * Opens a session, as a client connection does.
     *
     * @param string $user The user name the session authenticates as
     * @param string $host The host the session connects from
     * @param string|null $database The database to use, or null for none
     *
     * @throws Error\SqlError When the database does not exist
     */
    public function connect(string $user = 'root', string $host = 'localhost', ?string $database = null): Session
    {
        $session = new Session($this, ++$this->connections, $user, $host, $database);
        $this->sessions[$session->id] = WeakReference::create($session);

        return $session;
    }

    /**
     * Answers the number of sessions opened so far, which is the id of the last one.
     */
    public function connections(): int
    {
        return $this->connections;
    }
}
