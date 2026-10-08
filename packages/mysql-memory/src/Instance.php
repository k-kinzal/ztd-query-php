<?php

declare(strict_types=1);

namespace MySqlMemory;

use MySqlMemory\Account\Accounts;
use MySqlMemory\Dictionary\Dictionary;
use MySqlMemory\Dictionary\Schema;
use MySqlMemory\Registry\Registry;
use MySqlMemory\Session\Globals;
use MySqlMemory\Session\Session;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Variable\Catalog\SystemVariables;

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

    private int $connections = 0;

    /**
     * @param string $version The MySQL release emulated, as `8.4.7`
     * @param array<string, string|int> $globals Global variable values the server starts with, by name
     * @param list<string> $databases Databases created at start, besides the system ones
     * @param string|null $clientHost The host every client is seen connecting from, or null for its address
     */
    public function __construct(public readonly string $version = '8.4.7', array $globals = [], array $databases = [], public readonly ?string $clientHost = null)
    {
        $this->catalog = SystemVariables::of(GrammarRelease::tryFrom('mysql-' . $version) ?? GrammarRelease::MySql847);
        $this->globals = new Globals(array_change_key_case($globals, CASE_LOWER));
        $this->dictionary = new Dictionary();
        $this->accounts = Accounts::installed();
        $this->registry = new Registry();
        foreach (['information_schema', 'mysql', 'performance_schema', 'sys', ...$databases] as $name) {
            $this->dictionary->schemas[$name] = new Schema($name);
        }
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
        return new Session($this, ++$this->connections, $user, $host, $database);
    }

    /**
     * Answers the number of sessions opened so far, which is the id of the last one.
     */
    public function connections(): int
    {
        return $this->connections;
    }
}
