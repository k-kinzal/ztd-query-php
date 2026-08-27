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
 * - mysql  -> MySqlSessionFactory  (k-kinzal/ztd-query-mysql)
 * - pgsql  -> PgSqlSessionFactory  (k-kinzal/ztd-query-postgres)
 * - sqlite -> SqliteSessionFactory (k-kinzal/ztd-query-sqlite)
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
     */
    public function disableZtd(): void
    {
        $this->session->disable();
    }

    /**
     * Check whether ZTD mode is enabled.
     *
     * @return bool Whether writes are being shadowed rather than carried out
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
     */
    #[Override]
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if (!$this->session->isEnabled()) {
            return $this->pdo->prepare($query, $options);
        }

        $this->copy->guardRaw($query);

        $execution = new PdoPreparedExecution($this->pdo, $this->session, $query, $options);
        $prepared = $execution->prepare(null);

        $defaultFetchMode = $this->pdo->getAttribute(PDO::ATTR_DEFAULT_FETCH_MODE);

        return new ZtdPdoStatement(
            $prepared['statement'],
            $this->session,
            $prepared['plan'],
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
     */
    #[Override]
    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }

    /**
     * {@inheritDoc}
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
     */
    #[Override]
    public function errorInfo(): array
    {
        /** @var array{0: string|null, 1: int|null, 2: string|null} */
        return $this->pdo->errorInfo();
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function getAttribute(int $attribute): mixed
    {
        return $this->pdo->getAttribute($attribute);
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function setAttribute(int $attribute, mixed $value): bool
    {
        return $this->pdo->setAttribute($attribute, $value);
    }

    /**
     * {@inheritDoc}
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
     */
    public function pgsqlCopyToArray(
        mixed $tableName,
        mixed $separator = "\t",
        mixed $nullAs = '\\N',
        mixed $fields = null,
    ): array|false {
        return $this->copy->toArray($this, $tableName, $separator, $nullAs, $fields);
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
     */
    public function pgsqlCopyFromArray(
        mixed $tableName,
        array|Traversable $rows,
        mixed $separator = "\t",
        mixed $nullAs = '\\N',
        mixed $fields = null,
    ): bool {
        return $this->copy->fromArray($this, $tableName, $rows, $separator, $nullAs, $fields);
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
     */
    public function copyFromArray(
        string $tableName,
        array|Traversable $rows,
        string $separator = "\t",
        string $nullAs = '\\N',
        ?string $fields = null,
    ): bool {
        return $this->copy->fromArray($this, $tableName, $rows, $separator, $nullAs, $fields);
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
     */
    public function pgsqlCopyToFile(
        mixed $tableName,
        mixed $filename,
        mixed $separator = "\t",
        mixed $nullAs = '\\N',
        mixed $fields = null,
    ): bool {
        return $this->copy->toFile($this, $tableName, $filename, $separator, $nullAs, $fields);
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
     */
    public function pgsqlCopyFromFile(
        mixed $tableName,
        mixed $filename,
        mixed $separator = "\t",
        mixed $nullAs = '\\N',
        mixed $fields = null,
    ): bool {
        return $this->copy->fromFile($this, $tableName, $filename, $separator, $nullAs, $fields);
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
     */
    #[Override]
    public static function getAvailableDrivers(): array
    {
        /** @var array<int, string> */
        return PDO::getAvailableDrivers();
    }
}
