<?php

declare(strict_types=1);

namespace ZtdQuery\Adapter\Pdo;

use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Shadow\ShadowStore;
use ZtdQuery\Platform\MySql\MySqlRewriter;
use ZtdQuery\Platform\MySql\MySqlSchemaReflector;
use ZtdQuery\Platform\MySql\Transformer\CteGenerator;
use ZtdQuery\Platform\MySql\Transformer\DeleteTransformer;
use ZtdQuery\Platform\MySql\Transformer\UpdateTransformer;
use ZtdQuery\QueryGuard;
use ZtdQuery\ResultSelectRunner;
use ZtdQuery\Rewrite\Projection\WriteProjection;
use ZtdQuery\Rewrite\Shadowing\CteShadowing;
use ZtdQuery\Shadow\ShadowApplier;
use ZtdQuery\Schema\SchemaRegistry;
use ZtdQuery\ZteSession;
use PDO;
use PDOStatement;
use ReflectionClass;

/**
 * PDO proxy that enforces ZTD behavior for reads and writes.
 *
 * Uses delegation pattern: extends PDO for type compatibility,
 * but delegates all operations to an inner PDO instance when using fromPdo().
 */
class ZtdPdo extends PDO
{
    /**
     * ZTD session context for this connection.
     */
    private ZteSession $session;


    /**
     * Shadow store holding simulated rows.
     */
    private ShadowStore $shadowStore;

    /**
     * Schema registry for column/PK lookup.
     */
    private SchemaRegistry $schemaRegistry;

    /**
     * Inner PDO instance for delegation.
     */
    private PDO $innerPdo;

    /**
     * ZTD configuration.
     */
    private ZtdConfig $config;

    /**
     * Configure a new ZTD-enabled PDO wrapper.
     *
     * @param array<int, mixed>|null $options
     */
    public function __construct(string $dsn, ?string $username = null, ?string $password = null, ?array $options = null, ?ZtdConfig $config = null)
    {
        // Do not call parent constructor - delegate all operations to innerPdo
        $this->innerPdo = new PDO($dsn, $username, $password, $options);
        $this->config = $config ?? ZtdConfig::default();
        $this->initialize();
    }

    /**
     * Create a ZtdPdo wrapper around an existing PDO instance.
     *
     * This allows reusing an existing PDO connection instead of creating a new one.
     * The wrapped PDO instance will be used for all database operations.
     */
    public static function fromPdo(PDO $pdo, ?ZtdConfig $config = null): self
    {
        /** @var self $instance */
        $instance = (new ReflectionClass(self::class))->newInstanceWithoutConstructor();
        $instance->innerPdo = $pdo;
        $instance->config = $config ?? ZtdConfig::default();
        $instance->initialize();

        return $instance;
    }

    /**
     * Get the underlying PDO instance.
     */
    public function getInnerPdo(): PDO
    {
        return $this->innerPdo;
    }

    /**
     * Initialize ZTD components.
     */
    private function initialize(): void
    {
        $this->shadowStore = new ShadowStore();

        // Pass PDO directly - no closure needed, no circular reference
        $reflector = new MySqlSchemaReflector($this->getInnerPdo());
        $this->schemaRegistry = new SchemaRegistry($reflector);

        $guard = new QueryGuard();
        $shadowing = new CteShadowing(new CteGenerator(), $this->schemaRegistry);
        $writeProjection = new WriteProjection(
            $this->shadowStore,
            $this->schemaRegistry,
            $shadowing,
            new UpdateTransformer(),
            new DeleteTransformer()
        );
        $rewriter = new MySqlRewriter($guard, $this->shadowStore, $shadowing, $writeProjection, null, $this->schemaRegistry);

        $this->session = new ZteSession(
            $this->shadowStore,
            $this->schemaRegistry,
            $guard,
            $rewriter,
            new ShadowApplier($this->shadowStore),
            new ResultSelectRunner(),
            $this->config
        );

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

    // === PDO methods - all explicitly overridden for delegation ===

    /**
     * {@inheritDoc}
     *
     * @param array<int, mixed> $options
     */
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $stmt = $this->innerPdo->prepare($query, $options);
        if ($stmt === false) {
            return false;
        }

        return new ZtdStatement($stmt, $this->session, $this, $query);
    }

    /**
     * {@inheritDoc}
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
     */
    public function exec(string $statement): int|false
    {
        if (!$this->session->isEnabled()) {
            return $this->innerPdo->exec($statement);
        }

        return $this->session->execStatement(
            $statement,
            fn (string $sql) => $this->innerPdo->exec($sql),
            fn (string $sql) => $this->innerPdo->query($sql)
        );
    }

    /**
     * {@inheritDoc}
     */
    public function beginTransaction(): bool
    {
        return $this->innerPdo->beginTransaction();
    }

    /**
     * {@inheritDoc}
     */
    public function commit(): bool
    {
        return $this->innerPdo->commit();
    }

    /**
     * {@inheritDoc}
     */
    public function rollBack(): bool
    {
        return $this->innerPdo->rollBack();
    }

    /**
     * {@inheritDoc}
     */
    public function inTransaction(): bool
    {
        return $this->innerPdo->inTransaction();
    }

    /**
     * {@inheritDoc}
     */
    public function lastInsertId(?string $name = null): string|false
    {
        return $this->innerPdo->lastInsertId($name);
    }

    /**
     * {@inheritDoc}
     */
    public function errorCode(): ?string
    {
        return $this->innerPdo->errorCode();
    }

    /**
     * {@inheritDoc}
     *
     * @return array{0: string|null, 1: int|null, 2: string|null}
     */
    public function errorInfo(): array
    {
        /** @var array{0: string|null, 1: int|null, 2: string|null} */
        return $this->innerPdo->errorInfo();
    }

    /**
     * {@inheritDoc}
     */
    public function getAttribute(int $attribute): mixed
    {
        return $this->innerPdo->getAttribute($attribute);
    }

    /**
     * {@inheritDoc}
     */
    public function setAttribute(int $attribute, mixed $value): bool
    {
        return $this->innerPdo->setAttribute($attribute, $value);
    }

    /**
     * {@inheritDoc}
     */
    public function quote(string $string, int $type = PDO::PARAM_STR): string|false
    {
        return $this->innerPdo->quote($string, $type);
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
