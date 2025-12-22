<?php

declare(strict_types=1);

namespace ZtdQuery\Adapter\Pdo;

use PDO;
use PDOStatement;
use ReflectionClass;
use RuntimeException;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Connection\Exception\DatabaseException;
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

        try {
            $plan = $this->session->rewrite($query);
        } catch (DatabaseException $e) {
            throw new ZtdPdoException($e->getMessage(), 0, $e);
        }

        $stmt = $this->pdo->prepare($plan->sql(), $options);
        if ($stmt === false) {
            return false;
        }

        return new ZtdPdoStatement($stmt, $this->session, $plan);
    }

    /**
     * {@inheritDoc}
     *
     * @throws ZtdPdoException When ZTD-specific exception occurs (wraps DatabaseException).
     */
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
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

        try {
            return $this->session->execStatement($statement);
        } catch (DatabaseException $e) {
            throw new ZtdPdoException($e->getMessage(), 0, $e);
        }
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
        return $this->pdo->beginTransaction();
    }

    /**
     * {@inheritDoc}
     */
    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    /**
     * {@inheritDoc}
     */
    public function rollBack(): bool
    {
        return $this->pdo->rollBack();
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
