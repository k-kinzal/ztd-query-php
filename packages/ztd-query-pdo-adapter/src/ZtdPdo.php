<?php

declare(strict_types=1);

namespace ZtdQuery\Adapter\Pdo;

use PDO;
use PDOStatement;
use ReflectionClass;
use RuntimeException;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Connection\Exception\DatabaseException;
use ZtdQuery\Platform\CopySupport;
use ZtdQuery\Platform\CopyTarget;
use ZtdQuery\Session;
use ZtdQuery\Platform\SessionFactory;

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
     * Driver name to SessionFactory class mapping for auto-detection.
     *
     * @var array<string, array{class: string, package: string}>
     */
    private const DRIVER_MAP = [
        'mysql' => [
            'class' => 'ZtdQuery\\Platform\\MySql\\MySqlSessionFactory',
            'package' => 'k-kinzal/ztd-query-mysql',
        ],
        'pgsql' => [
            'class' => 'ZtdQuery\\Platform\\Postgres\\PgSqlSessionFactory',
            'package' => 'k-kinzal/ztd-query-postgres',
        ],
        'sqlite' => [
            'class' => 'ZtdQuery\\Platform\\Sqlite\\SqliteSessionFactory',
            'package' => 'k-kinzal/ztd-query-sqlite',
        ],
    ];

    /**
     * ZTD session context for this connection.
     */
    private Session $session;

    /**
     * Inner PDO instance for delegation.
     */
    private PDO $pdo;

    /**
     * Configure a new ZTD-enabled PDO wrapper.
     *
     * If $factory is provided, it is used directly to create the session.
     * If $factory is null, the factory is auto-detected from the PDO driver name.
     *
     * @param array<int, mixed>|null $options
     */
    public function __construct(string $dsn, ?string $username = null, ?string $password = null, ?array $options = null, ?ZtdConfig $config = null, ?SessionFactory $factory = null)
    {
        parent::__construct($dsn, $username, $password, $options);
        $this->pdo = new PDO($dsn, $username, $password, $options);

        $resolvedFactory = $factory ?? self::detectFactory($this->pdo);
        $connection = new PdoConnection($this->pdo);
        $this->session = $resolvedFactory->create($connection, $config ?? ZtdConfig::default());
    }

    /**
     * Create a ZtdPdo wrapper around an existing PDO instance.
     *
     * This allows reusing an existing PDO connection instead of creating a new one.
     * The wrapped PDO instance will be used for all database operations.
     *
     * If $factory is provided, it is used directly to create the session.
     * If $factory is null, the factory is auto-detected from the PDO driver name.
     */
    public static function fromPdo(PDO $pdo, ?ZtdConfig $config = null, ?SessionFactory $factory = null): static
    {
        /** @var static $instance */
        $instance = (new ReflectionClass(static::class))->newInstanceWithoutConstructor();
        $instance->pdo = $pdo;

        $resolvedFactory = $factory ?? self::detectFactory($instance->pdo);
        $connection = new PdoConnection($instance->pdo);
        $instance->session = $resolvedFactory->create($connection, $config ?? ZtdConfig::default());

        return $instance;
    }

    /**
     * Detect the appropriate SessionFactory based on the PDO driver name.
     *
     * @throws RuntimeException If the driver is unsupported or the required platform package is not installed.
     */
    private static function detectFactory(PDO $pdo): SessionFactory
    {
        /** @var string $driver */
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if (!isset(self::DRIVER_MAP[$driver])) {
            throw new RuntimeException(sprintf(
                'Unsupported PDO driver: "%s". Supported drivers: %s.',
                $driver,
                implode(', ', array_keys(self::DRIVER_MAP))
            ));
        }

        $mapping = self::DRIVER_MAP[$driver];
        /** @var class-string<SessionFactory> $className */
        $className = $mapping['class'];
        $packageName = $mapping['package'];

        if (!class_exists($className)) {
            throw new RuntimeException(sprintf(
                'Platform package for PDO driver "%s" is not installed. Install it with: composer require %s',
                $driver,
                $packageName
            ));
        }

        return new $className();
    }

    /**
     * Enable ZTD mode for this connection.
     */
    public function enableZtd(): void
    {
        $this->session->enable();
    }

    /**
     * Disable ZTD mode for this connection.
     */
    public function disableZtd(): void
    {
        $this->session->disable();
    }

    /**
     * Check whether ZTD mode is enabled.
     */
    public function isZtdEnabled(): bool
    {
        return $this->session->isEnabled();
    }

    /**
     * {@inheritDoc}
     *
     * @param array<mixed> $options
     * @throws ZtdPdoException When ZTD-specific exception occurs (wraps DatabaseException).
     */
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if (!$this->session->isEnabled()) {
            return $this->pdo->prepare($query, $options);
        }

        $this->guardRawPostgreSqlCopy($query);

        try {
            $execution = new PdoPreparedExecution($this->pdo, $this->session, $query, $options);
            $prepared = $execution->prepare(null);
        } catch (DatabaseException $e) {
            throw new ZtdPdoException($e->getMessage(), 0, $e);
        }

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
     * @throws ZtdPdoException When ZTD-specific exception occurs (wraps DatabaseException).
     */
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
     * @throws ZtdPdoException When ZTD-specific exception occurs (wraps DatabaseException).
     */
    public function exec(string $statement): int|false
    {
        if (!$this->session->isEnabled()) {
            return $this->pdo->exec($statement);
        }

        $statements = $this->session->splitStatements($statement);
        if (count($statements) > 1) {
            return $this->execMultiple($statements);
        }

        $this->guardRawPostgreSqlCopy($statement);

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
     * @param non-empty-list<string> $statements
     */
    private function execMultiple(array $statements): int|false
    {
        $affectedRows = $this->exec($statements[0]);
        if ($affectedRows === false) {
            return false;
        }

        foreach (array_slice($statements, 1) as $statement) {
            $result = $this->exec($statement);
            if ($result === false) {
                return false;
            }
            $affectedRows = $result;
        }

        return $affectedRows;
    }

    /**
     * {@inheritDoc}
     *
     * @param array<mixed>|null $options
     */
    public static function connect(
        string $dsn,
        ?string $username = null,
        #[\SensitiveParameter] ?string $password = null,
        ?array $options = null
    ): static {
        return static::fromPdo(PDO::connect($dsn, $username, $password, $options));
    }

    /**
     * {@inheritDoc}
     */
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
    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }

    /**
     * {@inheritDoc}
     */
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
    public function errorCode(): ?string
    {
        return $this->pdo->errorCode();
    }

    /**
     * {@inheritDoc}
     *
     * @return array{0: string|null, 1: int|null, 2: string|null}
     */
    public function errorInfo(): array
    {
        /** @var array{0: string|null, 1: int|null, 2: string|null} */
        return $this->pdo->errorInfo();
    }

    /**
     * {@inheritDoc}
     */
    public function getAttribute(int $attribute): mixed
    {
        return $this->pdo->getAttribute($attribute);
    }

    /**
     * {@inheritDoc}
     */
    public function setAttribute(int $attribute, mixed $value): bool
    {
        return $this->pdo->setAttribute($attribute, $value);
    }

    /**
     * {@inheritDoc}
     */
    public function quote(string $string, int $type = PDO::PARAM_STR): string|false
    {
        return $this->pdo->quote($string, $type);
    }

    /** @return array<int, string>|false */
    public function pgsqlCopyToArray(
        mixed $tableName,
        mixed $separator = "\t",
        mixed $nullAs = '\\N',
        mixed $fields = null,
    ): array|false {
        return $this->copyToArrayThroughZtd(
            $this->copyStringArgument($tableName, 'tableName'),
            $this->copyStringArgument($separator, 'separator'),
            $this->copyStringArgument($nullAs, 'nullAs'),
            $this->copyOptionalStringArgument($fields, 'fields'),
        );
    }

    /** @return array<int, string>|false */
    public function copyToArray(
        string $tableName,
        string $separator = "\t",
        string $nullAs = '\\N',
        ?string $fields = null,
    ): array|false {
        return $this->copyToArrayThroughZtd($tableName, $separator, $nullAs, $fields);
    }

    /** @param array<mixed>|\Traversable<mixed> $rows */
    public function pgsqlCopyFromArray(
        mixed $tableName,
        array|\Traversable $rows,
        mixed $separator = "\t",
        mixed $nullAs = '\\N',
        mixed $fields = null,
    ): bool {
        return $this->copyFromArrayThroughZtd(
            $this->copyStringArgument($tableName, 'tableName'),
            $rows,
            $this->copyStringArgument($separator, 'separator'),
            $this->copyStringArgument($nullAs, 'nullAs'),
            $this->copyOptionalStringArgument($fields, 'fields'),
        );
    }

    /** @param array<mixed>|\Traversable<mixed> $rows */
    public function copyFromArray(
        string $tableName,
        array|\Traversable $rows,
        string $separator = "\t",
        string $nullAs = '\\N',
        ?string $fields = null,
    ): bool {
        return $this->copyFromArrayThroughZtd($tableName, $rows, $separator, $nullAs, $fields);
    }

    public function pgsqlCopyToFile(
        mixed $tableName,
        mixed $filename,
        mixed $separator = "\t",
        mixed $nullAs = '\\N',
        mixed $fields = null,
    ): bool {
        return $this->copyToFileThroughZtd(
            $this->copyStringArgument($tableName, 'tableName'),
            $this->copyStringArgument($filename, 'filename'),
            $this->copyStringArgument($separator, 'separator'),
            $this->copyStringArgument($nullAs, 'nullAs'),
            $this->copyOptionalStringArgument($fields, 'fields'),
        );
    }

    public function copyToFile(
        string $tableName,
        string $filename,
        string $separator = "\t",
        string $nullAs = '\\N',
        ?string $fields = null,
    ): bool {
        return $this->copyToFileThroughZtd($tableName, $filename, $separator, $nullAs, $fields);
    }

    public function pgsqlCopyFromFile(
        mixed $tableName,
        mixed $filename,
        mixed $separator = "\t",
        mixed $nullAs = '\\N',
        mixed $fields = null,
    ): bool {
        return $this->copyFromFileThroughZtd(
            $this->copyStringArgument($tableName, 'tableName'),
            $this->copyStringArgument($filename, 'filename'),
            $this->copyStringArgument($separator, 'separator'),
            $this->copyStringArgument($nullAs, 'nullAs'),
            $this->copyOptionalStringArgument($fields, 'fields'),
        );
    }

    public function copyFromFile(
        string $tableName,
        string $filename,
        string $separator = "\t",
        string $nullAs = '\\N',
        ?string $fields = null,
    ): bool {
        return $this->copyFromFileThroughZtd($tableName, $filename, $separator, $nullAs, $fields);
    }

    /** @return array<int, string>|false */
    private function copyToArrayThroughZtd(
        string $tableName,
        string $separator,
        string $nullAs,
        ?string $fields,
    ): array|false {
        [$copy, $target] = $this->postgreSqlCopyTarget($tableName, $fields);
        $statement = $this->query($copy->selectSql($target));
        if ($statement === false) {
            return false;
        }

        $rows = [];
        while (($values = $statement->fetch(PDO::FETCH_NUM)) !== false) {
            if (!is_array($values)) {
                throw new ZtdPdoException('PostgreSQL COPY query returned an invalid row.');
            }
            $rows[] = $copy->encodeRow(array_values($values), $separator, $nullAs);
        }

        return $rows;
    }

    /** @param array<mixed>|\Traversable<mixed> $rows */
    private function copyFromArrayThroughZtd(
        string $tableName,
        array|\Traversable $rows,
        string $separator,
        string $nullAs,
        ?string $fields,
    ): bool {
        [$copy, $target] = $this->postgreSqlCopyTarget($tableName, $fields);
        $decodedRows = [];
        foreach ($rows as $row) {
            if (!is_string($row)) {
                throw new \TypeError(sprintf('PostgreSQL COPY rows must be strings, %s given.', get_debug_type($row)));
            }
            $decodedRow = $copy->decodeRow($row, $separator, $nullAs);
            if (count($decodedRow) !== count($target->columns)) {
                throw new \ValueError(sprintf(
                    'PostgreSQL COPY row has %d fields, but %d fields are required.',
                    count($decodedRow),
                    count($target->columns),
                ));
            }
            $decodedRows[] = $decodedRow;
        }
        if ($decodedRows === []) {
            return true;
        }

        $parameters = [];
        foreach ($decodedRows as $parameterValues) {
            foreach ($parameterValues as $value) {
                $parameters[] = $value;
            }
        }

        $statement = $this->prepare($copy->insertSql(
            $target,
            count($decodedRows),
            !$this->session->isEnabled(),
        ));

        return $statement !== false && $statement->execute($parameters);
    }

    private function copyToFileThroughZtd(
        string $tableName,
        string $filename,
        string $separator,
        string $nullAs,
        ?string $fields,
    ): bool {
        $rows = $this->copyToArrayThroughZtd($tableName, $separator, $nullAs, $fields);
        if ($rows === false) {
            return false;
        }

        return file_put_contents($filename, implode('', $rows)) !== false;
    }

    private function copyFromFileThroughZtd(
        string $tableName,
        string $filename,
        string $separator,
        string $nullAs,
        ?string $fields,
    ): bool {
        $rows = file($filename);
        if ($rows === false) {
            return false;
        }

        return $this->copyFromArrayThroughZtd($tableName, $rows, $separator, $nullAs, $fields);
    }

    /**
     * @return array{CopySupport, CopyTarget}
     */
    private function postgreSqlCopyTarget(string $tableName, ?string $fields): array
    {
        $copy = $this->session->copySupport();
        if ($copy === null) {
            throw new ZtdPdoException('PostgreSQL COPY methods require the PDO PostgreSQL driver.');
        }

        $target = $this->session->copyTarget($tableName, $fields);
        if ($target === null) {
            throw new ZtdPdoException(sprintf(
                'PostgreSQL COPY cannot resolve the schema for table "%s".',
                $tableName,
            ));
        }

        return [$copy, $target];
    }

    private function guardRawPostgreSqlCopy(string $sql): void
    {
        if ($this->session->copySupport()?->isCopyStatement($sql) === true) {
            throw new ZtdPdoException(
                'ZTD Write Protection: Raw PostgreSQL COPY cannot preserve shadow isolation; '
                . 'use the pgsqlCopyToArray(), pgsqlCopyFromArray(), pgsqlCopyToFile(), or pgsqlCopyFromFile() methods.',
            );
        }
    }

    private function copyStringArgument(mixed $value, string $name): string
    {
        if (!is_string($value)) {
            throw new \TypeError(sprintf('PostgreSQL COPY argument $%s must be a string, %s given.', $name, get_debug_type($value)));
        }

        return $value;
    }

    private function copyOptionalStringArgument(mixed $value, string $name): ?string
    {
        if ($value === null) {
            return null;
        }

        return $this->copyStringArgument($value, $name);
    }

    /**
     * {@inheritDoc}
     *
     * @return array<int, string>
     */
    public static function getAvailableDrivers(): array
    {
        /** @var array<int, string> */
        return PDO::getAvailableDrivers();
    }
}
