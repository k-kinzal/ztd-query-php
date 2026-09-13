<?php

declare(strict_types=1);

namespace ZtdQuery\Adapter\Pdo;

use Override;
use PDO;
use PDOStatement;
use ReflectionClass;
use RuntimeException;
use SensitiveParameter;
use Traversable;
use ZtdQuery\Adapter\Pdo\Driver\PdoConnection;
use ZtdQuery\Adapter\Pdo\Session\DriverSessionFactory;
use ZtdQuery\Adapter\Pdo\Session\PostgreSqlCopy;
use ZtdQuery\Adapter\Pdo\Session\PreparedQuery;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Connection\Exception\DatabaseException;
use ZtdQuery\Platform\SessionFactory;
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
    /**
     * ZTD session context for this connection.
     */
    private Session $session;

    /**
     * Inner PDO instance for delegation.
     */
    private PDO $pdo;

    /**
     * PostgreSQL COPY, carried out through ZTD rather than by the server.
     */
    private PostgreSqlCopy $copy;

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
        $this->pdo = new PDO($dsn, $username, $password, $options);

        $resolvedFactory = $factory ?? (new DriverSessionFactory())->forConnection($this->pdo);
        $connection = new PdoConnection($this->pdo);
        $this->session = $resolvedFactory->create($connection, $config ?? ZtdConfig::default());
        $this->copy = new PostgreSqlCopy($this->session);
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
        /** @var static $instance */
        $instance = (new ReflectionClass(static::class))->newInstanceWithoutConstructor();
        $instance->pdo = $pdo;

        $resolvedFactory = $factory ?? (new DriverSessionFactory())->forConnection($instance->pdo);
        $connection = new PdoConnection($instance->pdo);
        $instance->session = $resolvedFactory->create($connection, $config ?? ZtdConfig::default());
        $instance->copy = new PostgreSqlCopy($instance->session);

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
        $this->session->enable();
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
        $this->session->disable();
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
        return $this->session->isEnabled();
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
        if (!$this->session->isEnabled()) {
            return $this->pdo->prepare($query, $options);
        }

        $this->copy->guardRaw($query);

        try {
            $native = $this->pdo;
            $execution = new PreparedQuery($this->session, $query, static fn (string $sql): PDOStatement|false => $native->prepare($sql, $options));
            $plan = $execution->rewrite();
            $compiled = $this->session->parameterBindingCompiler()?->compile($plan->sql(), null);
            $statement = $execution->prepare($compiled['sql'] ?? $plan->sql());
        } catch (DatabaseException $exception) {
            throw new ZtdPdoException($exception->getMessage(), 0, $exception);
        }

        $defaultFetchMode = $this->pdo->getAttribute(PDO::ATTR_DEFAULT_FETCH_MODE);

        return new ZtdPdoStatement(
            $statement,
            $this->session,
            $plan,
            $execution,
            is_int($defaultFetchMode) ? $defaultFetchMode : PDO::FETCH_BOTH,
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
        if ($this->session->isEnabled()) {
            $transactionStatement = $this->session->transactionStatement($query);
            if ($transactionStatement !== null) {
                $statement = $this->pdo->query($query, $fetchMode, ...$fetchModeArgs);
                if ($statement !== false) {
                    $this->session->applyTransactionStatement($transactionStatement);
                }

                return $statement;
            }
        }

        $stmt = $this->prepare($query);
        if ($stmt === false) {
            return false;
        }

        if ($fetchMode !== null) {
            $stmt->setFetchMode($fetchMode, ...$fetchModeArgs);
        }

        if (!$stmt->execute()) {
            return false;
        }

        return $stmt;
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
        if (!$this->session->isEnabled()) {
            return $this->pdo->exec($statement);
        }

        $statements = $this->session->splitStatements($statement);
        if (count($statements) > 1) {
            $affectedRows = 0;
            foreach ($statements as $one) {
                $result = $this->exec($one);
                if ($result === false) {
                    return false;
                }
                $affectedRows = $result;
            }

            return $affectedRows;
        }

        $this->copy->guardRaw($statement);

        $transactionStatement = $this->session->transactionStatement($statement);
        if ($transactionStatement !== null) {
            $result = $this->pdo->exec($statement);
            if ($result !== false) {
                $this->session->applyTransactionStatement($transactionStatement);
            }

            return $result;
        }

        try {
            return $this->session->execStatement($statement);
        } catch (DatabaseException $e) {
            throw new ZtdPdoException($e->getMessage(), 0, $e);
        }
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
        $result = $this->pdo->beginTransaction();
        if ($result) {
            $this->session->beginTransaction();
        }

        return $result;
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
        $result = $this->pdo->commit();
        if ($result) {
            $this->session->commitTransaction();
        }

        return $result;
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
        $result = $this->pdo->rollBack();
        if ($result) {
            $this->session->rollBackTransaction();
        }

        return $result;
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
        return $this->pdo->inTransaction();
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
        if ($this->session->isEnabled() && $name === null) {
            $lastInsertId = $this->session->lastInsertId();
            if ($lastInsertId !== false) {
                return $lastInsertId;
            }
        }

        return $this->pdo->lastInsertId($name);
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
        return $this->pdo->errorCode();
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
        return $this->pdo->errorInfo();
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
        return $this->pdo->getAttribute($attribute);
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
        return $this->pdo->setAttribute($attribute, $value);
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
        return $this->pdo->quote($string, $type);
    }

    /**
     * Answers the table's rows as PostgreSQL's COPY would have written them out.
     *
     * The arguments are read as pdo_pgsql's own method read them, which is to
     * say as anything at all, and refused here where they are not strings.
     *
     * @param mixed $tableName Relation to read, as the caller named it
     * @param mixed $separator Field separator COPY writes between values
     * @param mixed $nullAs Text COPY writes where a value is null
     * @param mixed $fields Column list as the caller wrote it, or null for every column
     *
     * @return list<string>|false One encoded line per row, or false where the read did not run
     *
     * @throws ZtdPdoException When an argument is not a string, the dialect has no COPY, or the table is undescribed
     * @visibility public
     * @example Reject COPY on a connection without PostgreSQL support
     *     $pdo = \ZtdQuery\Adapter\Pdo\ZtdPdo::fromPdo(new \PDO('sqlite::memory:'));
     *     $pdo->pgsqlCopyToArray('users') // throws \ZtdQuery\Adapter\Pdo\ZtdPdoException: PostgreSQL driver
     */
    public function pgsqlCopyToArray(
        mixed $tableName,
        mixed $separator = "\t",
        mixed $nullAs = '\\N',
        mixed $fields = null,
    ): array|false {
        $strings = [];
        foreach (['tableName' => $tableName, 'separator' => $separator, 'nullAs' => $nullAs] as $name => $value) {
            if (!is_string($value)) {
                throw new ZtdPdoException(sprintf('PostgreSQL COPY argument $%s must be a string, %s given.', $name, get_debug_type($value)));
            }
            $strings[$name] = $value;
        }
        if ($fields !== null && !is_string($fields)) {
            throw new ZtdPdoException(sprintf('PostgreSQL COPY argument $fields must be a string, %s given.', get_debug_type($fields)));
        }
        return $this->copyToArray($strings['tableName'], $strings['separator'], $strings['nullAs'], $fields);
    }

    /**
     * Answers the table's rows as PostgreSQL's COPY would have written them out.
     *
     * @param string $tableName Relation to read
     * @param string $separator Field separator COPY writes between values
     * @param string $nullAs Text COPY writes where a value is null
     * @param string|null $fields Column list as the caller wrote it, or null for every column
     *
     * @return list<string>|false One encoded line per row, or false where the read did not run
     *
     * @throws ZtdPdoException When the dialect has no COPY, or nothing has described the table
     * @visibility public
     * @example Require PostgreSQL COPY support
     *     $pdo = \ZtdQuery\Adapter\Pdo\ZtdPdo::fromPdo(new \PDO('sqlite::memory:'));
     *     $pdo->copyToArray('users') // throws \ZtdQuery\Adapter\Pdo\ZtdPdoException: PostgreSQL driver
     */
    public function copyToArray(
        string $tableName,
        string $separator = "\t",
        string $nullAs = '\\N',
        ?string $fields = null,
    ): array|false {
        return $this->copy->toArray($this, $tableName, $separator, $nullAs, $fields);
    }

    /**
     * Writes encoded lines into the table as PostgreSQL's COPY would have read them in.
     *
     * @param mixed $tableName Relation to write, as the caller named it
     * @param array<mixed>|Traversable<mixed, mixed> $rows One encoded line per row
     * @param mixed $separator Field separator COPY reads between values
     * @param mixed $nullAs Text COPY reads as a null value
     * @param mixed $fields Column list as the caller wrote it, or null for every column
     *
     * @return bool Whether every row was written
     *
     * @throws ZtdPdoException When an argument is not a string, the table is undescribed, or a line does not fit it
     * @visibility public
     * @example Require PostgreSQL COPY support
     *     $pdo = \ZtdQuery\Adapter\Pdo\ZtdPdo::fromPdo(new \PDO('sqlite::memory:'));
     *     $pdo->pgsqlCopyFromArray('users', []) // throws \ZtdQuery\Adapter\Pdo\ZtdPdoException: PostgreSQL driver
     */
    public function pgsqlCopyFromArray(
        mixed $tableName,
        array|Traversable $rows,
        mixed $separator = "\t",
        mixed $nullAs = '\\N',
        mixed $fields = null,
    ): bool {
        $strings = [];
        foreach (['tableName' => $tableName, 'separator' => $separator, 'nullAs' => $nullAs] as $name => $value) {
            if (!is_string($value)) {
                throw new ZtdPdoException(sprintf('PostgreSQL COPY argument $%s must be a string, %s given.', $name, get_debug_type($value)));
            }
            $strings[$name] = $value;
        }
        if ($fields !== null && !is_string($fields)) {
            throw new ZtdPdoException(sprintf('PostgreSQL COPY argument $fields must be a string, %s given.', get_debug_type($fields)));
        }
        return $this->copyFromArray($strings['tableName'], $rows, $strings['separator'], $strings['nullAs'], $fields);
    }

    /**
     * Writes encoded lines into the table as PostgreSQL's COPY would have read them in.
     *
     * @param string $tableName Relation to write
     * @param array<mixed>|Traversable<mixed, mixed> $rows One encoded line per row
     * @param string $separator Field separator COPY reads between values
     * @param string $nullAs Text COPY reads as a null value
     * @param string|null $fields Column list as the caller wrote it, or null for every column
     *
     * @return bool Whether every row was written
     *
     * @throws ZtdPdoException When the table is undescribed, or a line does not fit it
     * @visibility public
     * @example Require PostgreSQL COPY support
     *     $pdo = \ZtdQuery\Adapter\Pdo\ZtdPdo::fromPdo(new \PDO('sqlite::memory:'));
     *     $pdo->copyFromArray('users', []) // throws \ZtdQuery\Adapter\Pdo\ZtdPdoException: PostgreSQL driver
     */
    public function copyFromArray(
        string $tableName,
        array|Traversable $rows,
        string $separator = "\t",
        string $nullAs = '\\N',
        ?string $fields = null,
    ): bool {
        $lines = [];
        foreach ($rows as $row) {
            if (!is_string($row)) {
                throw new ZtdPdoException(sprintf('PostgreSQL COPY rows must be strings, %s given.', get_debug_type($row)));
            }
            $lines[] = $row;
        }
        return $this->copy->fromArray($this, $tableName, $lines, $separator, $nullAs, $fields);
    }

    /**
     * Writes the table's rows into a file as PostgreSQL's COPY would have written them out.
     *
     * @param mixed $tableName Relation to read, as the caller named it
     * @param mixed $filename File to write the encoded lines to
     * @param mixed $separator Field separator COPY writes between values
     * @param mixed $nullAs Text COPY writes where a value is null
     * @param mixed $fields Column list as the caller wrote it, or null for every column
     *
     * @return bool Whether the file was written
     *
     * @throws ZtdPdoException When an argument is not a string, the dialect has no COPY, or the table is undescribed
     * @visibility public
     * @example Require PostgreSQL COPY support
     *     $pdo = \ZtdQuery\Adapter\Pdo\ZtdPdo::fromPdo(new \PDO('sqlite::memory:'));
     *     $pdo->pgsqlCopyToFile('users', __FILE__) // throws \ZtdQuery\Adapter\Pdo\ZtdPdoException: PostgreSQL driver
     */
    public function pgsqlCopyToFile(
        mixed $tableName,
        mixed $filename,
        mixed $separator = "\t",
        mixed $nullAs = '\\N',
        mixed $fields = null,
    ): bool {
        $strings = [];
        foreach (['tableName' => $tableName, 'filename' => $filename, 'separator' => $separator, 'nullAs' => $nullAs] as $name => $value) {
            if (!is_string($value)) {
                throw new ZtdPdoException(sprintf('PostgreSQL COPY argument $%s must be a string, %s given.', $name, get_debug_type($value)));
            }
            $strings[$name] = $value;
        }
        if ($fields !== null && !is_string($fields)) {
            throw new ZtdPdoException(sprintf('PostgreSQL COPY argument $fields must be a string, %s given.', get_debug_type($fields)));
        }
        return $this->copyToFile($strings['tableName'], $strings['filename'], $strings['separator'], $strings['nullAs'], $fields);
    }

    /**
     * Writes the table's rows into a file as PostgreSQL's COPY would have written them out.
     *
     * @param string $tableName Relation to read
     * @param string $filename File to write the encoded lines to
     * @param string $separator Field separator COPY writes between values
     * @param string $nullAs Text COPY writes where a value is null
     * @param string|null $fields Column list as the caller wrote it, or null for every column
     *
     * @return bool Whether the file was written
     *
     * @throws ZtdPdoException When the dialect has no COPY, or nothing has described the table
     * @visibility public
     * @example Require PostgreSQL COPY support
     *     $pdo = \ZtdQuery\Adapter\Pdo\ZtdPdo::fromPdo(new \PDO('sqlite::memory:'));
     *     $pdo->copyToFile('users', __FILE__) // throws \ZtdQuery\Adapter\Pdo\ZtdPdoException: PostgreSQL driver
     */
    public function copyToFile(
        string $tableName,
        string $filename,
        string $separator = "\t",
        string $nullAs = '\\N',
        ?string $fields = null,
    ): bool {
        return $this->copy->toFile($this, $tableName, $filename, $separator, $nullAs, $fields);
    }

    /**
     * Reads encoded lines out of a file and writes them into the table.
     *
     * @param mixed $tableName Relation to write, as the caller named it
     * @param mixed $filename File to read the encoded lines from
     * @param mixed $separator Field separator COPY reads between values
     * @param mixed $nullAs Text COPY reads as a null value
     * @param mixed $fields Column list as the caller wrote it, or null for every column
     *
     * @return bool Whether every row in the file was written
     *
     * @throws ZtdPdoException When an argument is not a string, the table is undescribed, or a line does not fit it
     * @visibility public
     * @example Require PostgreSQL COPY support
     *     $pdo = \ZtdQuery\Adapter\Pdo\ZtdPdo::fromPdo(new \PDO('sqlite::memory:'));
     *     $path = tempnam(sys_get_temp_dir(), 'ztd-doc');
     *     file_put_contents($path, "1\tAda\n");
     *     $pdo->pgsqlCopyFromFile('users', $path) // throws \ZtdQuery\Adapter\Pdo\ZtdPdoException: PostgreSQL driver
     *     unlink($path);
     */
    public function pgsqlCopyFromFile(
        mixed $tableName,
        mixed $filename,
        mixed $separator = "\t",
        mixed $nullAs = '\\N',
        mixed $fields = null,
    ): bool {
        $strings = [];
        foreach (['tableName' => $tableName, 'filename' => $filename, 'separator' => $separator, 'nullAs' => $nullAs] as $name => $value) {
            if (!is_string($value)) {
                throw new ZtdPdoException(sprintf('PostgreSQL COPY argument $%s must be a string, %s given.', $name, get_debug_type($value)));
            }
            $strings[$name] = $value;
        }
        if ($fields !== null && !is_string($fields)) {
            throw new ZtdPdoException(sprintf('PostgreSQL COPY argument $fields must be a string, %s given.', get_debug_type($fields)));
        }
        return $this->copyFromFile($strings['tableName'], $strings['filename'], $strings['separator'], $strings['nullAs'], $fields);
    }

    /**
     * Reads encoded lines out of a file and writes them into the table.
     *
     * @param string $tableName Relation to write
     * @param string $filename File to read the encoded lines from
     * @param string $separator Field separator COPY reads between values
     * @param string $nullAs Text COPY reads as a null value
     * @param string|null $fields Column list as the caller wrote it, or null for every column
     *
     * @return bool Whether every row in the file was written
     *
     * @throws ZtdPdoException When the table is undescribed, or a line does not fit it
     * @visibility public
     * @example Require PostgreSQL COPY support
     *     $pdo = \ZtdQuery\Adapter\Pdo\ZtdPdo::fromPdo(new \PDO('sqlite::memory:'));
     *     $path = tempnam(sys_get_temp_dir(), 'ztd-doc');
     *     file_put_contents($path, "1\tAda\n");
     *     $pdo->copyFromFile('users', $path) // throws \ZtdQuery\Adapter\Pdo\ZtdPdoException: PostgreSQL driver
     *     unlink($path);
     */
    public function copyFromFile(
        string $tableName,
        string $filename,
        string $separator = "\t",
        string $nullAs = '\\N',
        ?string $fields = null,
    ): bool {
        return $this->copy->fromFile($this, $tableName, $filename, $separator, $nullAs, $fields);
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
