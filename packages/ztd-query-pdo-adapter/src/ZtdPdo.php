<?php

declare(strict_types=1);

namespace ZtdQuery\Adapter\Pdo;

use Override;
use PDO;
use PDOStatement;
use ReflectionClass;
use RuntimeException;
use SensitiveParameter;
use ZtdQuery\Adapter\Pdo\Session\ConnectionExecution;
use ZtdQuery\Adapter\Pdo\Session\PreparedQuery;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Platform\SessionFactory;
use ZtdQuery\Rewrite\RewritePlan;
use ZtdQuery\Session;

/**
 * PDO proxy that enforces ZTD behavior for reads and writes.
 *
 * Uses delegation pattern: extends PDO for type compatibility,
 * but delegates all operations to an inner PDO instance when using fromPdo().
 *
 * Supports multiple database platforms via SessionFactory injection or auto-detection:
 * - MySQL (k-kinzal/ztd-query-mysql)
 * - PostgreSQL (k-kinzal/ztd-query-postgres)
 * - SQLite (k-kinzal/ztd-query-sqlite)
 *
 * @visibility public
 * @example Simulate a write without changing the physical table
 *     $native = new \PDO('sqlite::memory:');
 *     $native->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
 *     $pdo = \ZtdQuery\Adapter\Pdo\ZtdPdo::fromPdo($native);
 *     $pdo->exec("INSERT INTO users VALUES (1, 'Alice')") // => 1
 *     $pdo->query('SELECT name FROM users')->fetchColumn() // => 'Alice'
 *     $native->query('SELECT COUNT(*) FROM users')->fetchColumn() // => 0
 */
class ZtdPdo extends PDO
{
    private ConnectionExecution $execution;

    /**
     * Configure a new ZTD-enabled PDO wrapper.
     *
     * If $factory is provided, it is used directly to create the session.
     * If $factory is null, the factory is auto-detected from the PDO driver name.
     *
     * @param array<int, mixed>|null $options Driver options, as PDO::__construct() takes them
     * @param ZtdConfig|null $config How ZTD is to behave, or null for the default
     * @param SessionFactory|null $factory Platform to rewrite with, or null to read it off the driver
     *
     * @throws RuntimeException When the driver has no platform package installed
     * @visibility public
     * @example Open a ZTD connection
     *     $pdo = new \ZtdQuery\Adapter\Pdo\ZtdPdo('sqlite::memory:');
     *     $pdo->isZtdEnabled() // => true
     */
    public function __construct(string $dsn, ?string $username = null, ?string $password = null, ?array $options = null, ?ZtdConfig $config = null, ?SessionFactory $factory = null)
    {
        parent::__construct($dsn, $username, $password, $options);
        $this->execution = new ConnectionExecution(new PDO($dsn, $username, $password, $options), $config, $factory);
    }

    /**
     * Create a ZtdPdo wrapper around an existing PDO instance.
     *
     * This allows reusing an existing PDO connection instead of creating a new one.
     * The wrapped PDO instance will be used for all database operations.
     *
     * If $factory is provided, it is used directly to create the session.
     * If $factory is null, the factory is auto-detected from the PDO driver name.
     *
     * @param PDO $pdo Connection to wrap
     * @param ZtdConfig|null $config How ZTD is to behave, or null for the default
     * @param SessionFactory|null $factory Platform to rewrite with, or null to read it off the driver
     *
     * @return static The connection, with ZTD in front of it
     *
     * @throws RuntimeException When the driver has no platform package installed
     *
     * @visibility public
     * @example Wrap an existing PDO connection
     *     $pdo = \ZtdQuery\Adapter\Pdo\ZtdPdo::fromPdo(new \PDO('sqlite::memory:'));
     *     $pdo->isZtdEnabled() // => true
     *     $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) // => 'sqlite'
     */
    public static function fromPdo(PDO $pdo, ?ZtdConfig $config = null, ?SessionFactory $factory = null): static
    {
        $instance = (new ReflectionClass(static::class))->newInstanceWithoutConstructor();
        $instance->execution = new ConnectionExecution($pdo, $config, $factory);
        return $instance;
    }

    /**
     * Enable ZTD mode for this connection.
     *
     * While it is enabled, nothing this connection is asked to write reaches
     * the database; reads are answered from the shadow instead.
     * @visibility public
     * @example Resume shadowing after native access
     *     $pdo = \ZtdQuery\Adapter\Pdo\ZtdPdo::fromPdo(new \PDO('sqlite::memory:'));
     *     $pdo->disableZtd();
     *     $pdo->enableZtd();
     *     $pdo->isZtdEnabled() // => true
     */
    public function enableZtd(): void
    {
        $this->execution->session()->enable();
    }

    /**
     * Disable ZTD mode for this connection.
     *
     * Once it is disabled, statements run against the database as they were
     * written, and the shadow is not consulted.
     *
     * @example Temporarily pass queries through to the database
     *     $pdo = new \ZtdQuery\Adapter\Pdo\ZtdPdo('sqlite::memory:');
     *     $pdo->disableZtd();
     *     $pdo->isZtdEnabled() // => false
     *     $pdo->enableZtd();
     *     $pdo->isZtdEnabled() // => true
     * @visibility public
     */
    public function disableZtd(): void
    {
        $this->execution->session()->disable();
    }

    /**
     * Check whether ZTD mode is enabled.
     *
     * @return bool Whether writes are being shadowed rather than carried out
     * @visibility public
     * @example Inspect shadowing state
     *     $pdo = \ZtdQuery\Adapter\Pdo\ZtdPdo::fromPdo(new \PDO('sqlite::memory:'));
     *     $pdo->isZtdEnabled() // => true
     */
    public function isZtdEnabled(): bool
    {
        return $this->execution->session()->isEnabled();
    }

    /**
     * {@inheritDoc}
     *
     * @param array<mixed> $options Driver options, as PDO::prepare() takes them
     *
     * @return PDOStatement|false The prepared statement, or false where the driver would not prepare one
     *
     * @throws ZtdPdoException When ZTD cannot carry the statement out, or an option is one PDO cannot be given
     *
     * @example Bind values to a simulated INSERT
     *     $native = new \PDO('sqlite::memory:');
     *     $native->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
     *     $pdo = \ZtdQuery\Adapter\Pdo\ZtdPdo::fromPdo($native);
     *     $statement = $pdo->prepare('INSERT INTO users VALUES (:id, :name)');
     *     $statement->execute(['id' => 1, 'name' => 'Alice']) // => true
     *     $statement->rowCount() // => 1
     *     $native->query('SELECT COUNT(*) FROM users')->fetchColumn() // => 0
     * @visibility public
     */
    #[Override]
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return $this->execution->prepare(
            $query,
            $options,
            static fn (PDOStatement $statement, Session $session, RewritePlan $plan, PreparedQuery $prepared, int $mode): PDOStatement =>
                new ZtdPdoStatement($statement, $session, $plan, $prepared, $mode),
        );
    }

    /**
     * {@inheritDoc}
     *
     * @param mixed ...$fetchModeArgs The rest of what the fetch mode reads
     *
     * @return PDOStatement|false The executed statement, or false where it did not run
     *
     * @throws ZtdPdoException When ZTD cannot carry the statement out
     * @visibility public
     * @example Read virtual rows
     *     $native = new \PDO('sqlite::memory:');
     *     $native->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
     *     $pdo = \ZtdQuery\Adapter\Pdo\ZtdPdo::fromPdo($native);
     *     $pdo->exec("INSERT INTO users VALUES (1, 'Ada')");
     *     $pdo->query('SELECT name FROM users')->fetchColumn() // => 'Ada'
     *     $native->query('SELECT COUNT(*) FROM users')->fetchColumn() // => 0
     */
    #[Override]
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        return $this->execution->query($query, $fetchMode, $fetchModeArgs, $this->prepare(...));
    }

    /**
     * {@inheritDoc}
     *
     * A batch is carried out one statement at a time, and stops at the first
     * one that does not run; what it answers is what the last one that ran
     * affected, which is what PDO answers for a batch.
     *
     * @return int|false Rows the statement affected, or false where it did not run
     *
     * @throws ZtdPdoException When ZTD cannot carry the statement out
     * @visibility public
     * @example Count simulated mutations
     *     $native = new \PDO('sqlite::memory:');
     *     $native->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
     *     $pdo = \ZtdQuery\Adapter\Pdo\ZtdPdo::fromPdo($native);
     *     $pdo->exec("INSERT INTO users VALUES (1, 'Ada')") // => 1
     *     $pdo->exec("UPDATE users SET name = 'Grace'") // => 1
     *     $native->query('SELECT COUNT(*) FROM users')->fetchColumn() // => 0
     */
    #[Override]
    public function exec(string $statement): int|false
    {
        return $this->execution->exec($statement, $this->exec(...));
    }

    /**
     * {@inheritDoc}
     *
     * The connection is opened with PDO's constructor rather than with
     * PDO::connect(), which exists only from PHP 8.4 on while this package
     * supports 8.1. What connect() adds is a driver-specific subclass of PDO,
     * and nothing here asks the wrapped connection for anything a subclass
     * would answer differently.
     *
     * This carries no #[\Override] for the same reason: from PHP 8.3 on the
     * attribute is checked, and on 8.1 through 8.3 there is no PDO::connect()
     * for it to be checked against.
     *
     * @param array<mixed>|null $options Driver options, as PDO::connect() takes them
     *
     * @return static The new connection, with ZTD in front of it
     *
     * @throws RuntimeException When the driver has no platform package installed
     * @visibility public
     * @example Create a connection through the static factory
     *     $pdo = \ZtdQuery\Adapter\Pdo\ZtdPdo::connect('sqlite::memory:');
     *     $pdo->isZtdEnabled() // => true
     */
    public static function connect(
        string $dsn,
        ?string $username = null,
        #[SensitiveParameter] ?string $password = null,
        ?array $options = null
    ): static {
        return static::fromPdo(new PDO($dsn, $username, $password, $options));
    }

    /**
     * {@inheritDoc}
     * @visibility public
     * @example Begin a transaction for native and shadow state
     *     $pdo = \ZtdQuery\Adapter\Pdo\ZtdPdo::fromPdo(new \PDO('sqlite::memory:'));
     *     $pdo->beginTransaction() // => true
     *     $pdo->inTransaction() // => true
     *     $pdo->rollBack();
     */
    #[Override]
    public function beginTransaction(): bool
    {
        return $this->execution->beginTransaction();
    }

    /**
     * {@inheritDoc}
     * @visibility public
     * @example Keep committed virtual writes
     *     $native = new \PDO('sqlite::memory:');
     *     $native->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
     *     $pdo = \ZtdQuery\Adapter\Pdo\ZtdPdo::fromPdo($native);
     *     $pdo->beginTransaction();
     *     $pdo->exec("INSERT INTO users VALUES (1, 'Ada')");
     *     $pdo->commit() // => true
     *     $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() // => 1
     *     $native->query('SELECT COUNT(*) FROM users')->fetchColumn() // => 0
     */
    #[Override]
    public function commit(): bool
    {
        return $this->execution->commit();
    }

    /**
     * {@inheritDoc}
     * @visibility public
     * @example Undo virtual writes in a transaction
     *     $native = new \PDO('sqlite::memory:');
     *     $native->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
     *     $pdo = \ZtdQuery\Adapter\Pdo\ZtdPdo::fromPdo($native);
     *     $pdo->beginTransaction();
     *     $pdo->exec("INSERT INTO users VALUES (1, 'Ada')");
     *     $pdo->rollBack() // => true
     *     $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() // => 0
     */
    #[Override]
    public function rollBack(): bool
    {
        return $this->execution->rollBack();
    }

    /**
     * {@inheritDoc}
     * @visibility public
     * @example Observe transaction state
     *     $pdo = \ZtdQuery\Adapter\Pdo\ZtdPdo::fromPdo(new \PDO('sqlite::memory:'));
     *     $pdo->inTransaction() // => false
     *     $pdo->beginTransaction();
     *     $pdo->inTransaction() // => true
     *     $pdo->rollBack();
     */
    #[Override]
    public function inTransaction(): bool
    {
        return $this->execution->native()->inTransaction();
    }

    /**
     * {@inheritDoc}
     * @visibility public
     * @example Read a generated shadow key
     *     $native = new \PDO('sqlite::memory:');
     *     $native->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
     *     $pdo = \ZtdQuery\Adapter\Pdo\ZtdPdo::fromPdo($native);
     *     $pdo->exec("INSERT INTO users (name) VALUES ('Ada')");
     *     $pdo->lastInsertId() // => '1'
     */
    #[Override]
    public function lastInsertId(?string $name = null): string|false
    {
        return $this->execution->lastInsertId($name);
    }

    /**
     * {@inheritDoc}
     * @visibility public
     * @example Read a native SQLSTATE
     *     $pdo = \ZtdQuery\Adapter\Pdo\ZtdPdo::fromPdo(new \PDO('sqlite::memory:'));
     *     $pdo->disableZtd();
     *     $pdo->query('SELECT 1');
     *     $pdo->errorCode() // => '00000'
     */
    #[Override]
    public function errorCode(): ?string
    {
        return $this->execution->native()->errorCode();
    }

    /**
     * {@inheritDoc}
     *
     * @return array{0: string|null, 1: int|null, 2: string|null}
     * @visibility public
     * @example Read native error information
     *     $pdo = \ZtdQuery\Adapter\Pdo\ZtdPdo::fromPdo(new \PDO('sqlite::memory:'));
     *     $pdo->disableZtd();
     *     $pdo->query('SELECT 1');
     *     $pdo->errorInfo()[0] // => '00000'
     */
    #[Override]
    public function errorInfo(): array
    {
        /** @var array{0: string|null, 1: int|null, 2: string|null} */
        return $this->execution->native()->errorInfo();
    }

    /**
     * {@inheritDoc}
     * @visibility public
     * @example Read the underlying driver name
     *     $pdo = \ZtdQuery\Adapter\Pdo\ZtdPdo::fromPdo(new \PDO('sqlite::memory:'));
     *     $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) // => 'sqlite'
     */
    #[Override]
    public function getAttribute(int $attribute): mixed
    {
        return $this->execution->native()->getAttribute($attribute);
    }

    /**
     * {@inheritDoc}
     * @visibility public
     * @example Set the default fetch mode
     *     $pdo = \ZtdQuery\Adapter\Pdo\ZtdPdo::fromPdo(new \PDO('sqlite::memory:'));
     *     $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC) // => true
     *     $pdo->query('SELECT 7 AS id')->fetch() // => ['id' => 7]
     */
    #[Override]
    public function setAttribute(int $attribute, mixed $value): bool
    {
        return $this->execution->native()->setAttribute($attribute, $value);
    }

    /**
     * {@inheritDoc}
     * @visibility public
     * @example Quote a value using the native driver
     *     $pdo = \ZtdQuery\Adapter\Pdo\ZtdPdo::fromPdo(new \PDO('sqlite::memory:'));
     *     $pdo->quote("O'Reilly") // => "'O''Reilly'"
     */
    #[Override]
    public function quote(string $string, int $type = PDO::PARAM_STR): string|false
    {
        return $this->execution->native()->quote($string, $type);
    }

    /**
     * {@inheritDoc}
     *
     * @return array<int, string> Every driver name PDO itself was built with
     * @visibility public
     * @example List the available native drivers
     *     \ZtdQuery\Adapter\Pdo\ZtdPdo::getAvailableDrivers() === \PDO::getAvailableDrivers() // => true
     */
    #[Override]
    public static function getAvailableDrivers(): array
    {
        /** @var array<int, string> */
        return PDO::getAvailableDrivers();
    }
}
