<?php

declare(strict_types=1);

namespace ZtdQuery\Adapter\Mysqli;

use mysqli;
use mysqli_result;
use mysqli_sql_exception;
use mysqli_stmt;
use mysqli_warning;
use Override;
use ReflectionClass;
use ReturnTypeWillChange;
use SensitiveParameter;
use ZtdQuery\Adapter\Mysqli\Session\ConnectionExecution;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Platform\SessionFactory;
use ZtdQuery\Rewrite\RewritePlan;
use ZtdQuery\Session;

/**
 * mysqli proxy that enforces ZTD behavior for reads and writes.
 *
 * Uses delegation pattern: extends mysqli for type compatibility,
 * but delegates all operations to an inner mysqli instance when using fromMysqli().
 *
 * All public methods are explicitly overridden to prevent parent class
 * implementation from being called when constructed via fromMysqli().
 *
 * Properties are delegated via __get/__isset to the inner mysqli instance.
 *
 * Supports optional SessionFactory injection. If no factory is provided,
 * MySqlSessionFactory is used by default (mysqli is MySQL-only).
 *
 * @visibility public
 * @example Simulate writes without changing the native table
 *     $container = \Testcontainers\Testcontainers::run(\Container\MySql80Container::class);
 *     $native = new \mysqli(str_replace('localhost', '127.0.0.1', $container->getHost()), 'root', 'root', 'test', $container->getMappedPort(3306));
 *     $native->set_charset('utf8mb4');
 *     $native->query('CREATE TABLE accounts (id INT PRIMARY KEY, balance INT)');
 *     $native->query('INSERT INTO accounts VALUES (1, 10)');
 *     $ztd = \ZtdQuery\Adapter\Mysqli\ZtdMysqli::fromMysqli($native);
 *     $ztd->query('INSERT INTO accounts VALUES (2, 20)');
 *     $ztd->lastAffectedRows() // => 1
 *     $ztd->query('SELECT id FROM accounts')->fetch_all(MYSQLI_ASSOC) // => [['id' => 2]]
 *     $native->query('SELECT id FROM accounts')->fetch_all(MYSQLI_ASSOC) // => [['id' => '1']]
 *     $native->query('DROP TABLE accounts');
 *     $container->stop();
 */
class ZtdMysqli extends mysqli
{
    private ConnectionExecution $execution;

    /**
     * Configure a new ZTD-enabled mysqli wrapper.
     *
     * If $factory is provided, it is used directly to create the session.
     * If $factory is null, MySqlSessionFactory is used by default.
     */
    public function __construct(
        ?string $hostname = null,
        ?string $username = null,
        ?string $password = null,
        ?string $database = null,
        ?int $port = null,
        ?string $socket = null,
        ?ZtdConfig $config = null,
        ?SessionFactory $factory = null
    ) {
        /**
         * Parent is initialized without connection; innerMysqli handles the real connection
         */
        parent::__construct();
        $this->execution = new ConnectionExecution(new mysqli($hostname, $username, $password, $database, $port ?? 3306, $socket), $config, $factory);
    }

    /**
     * Create a ZtdMysqli wrapper around an existing mysqli instance.
     *
     * This allows reusing an existing mysqli connection instead of creating a new one.
     * The wrapped mysqli instance will be used for all database operations.
     *
     * If $factory is provided, it is used directly to create the session.
     * If $factory is null, MySqlSessionFactory is used by default.
     */
    public static function fromMysqli(mysqli $mysqli, ?ZtdConfig $config = null, ?SessionFactory $factory = null): self
    {
        /**
         * @var self $instance
         */
        $instance = (new ReflectionClass(self::class))->newInstanceWithoutConstructor();
        $instance->execution = new ConnectionExecution($mysqli, $config, $factory);

        return $instance;
    }

    /**
     * Enable ZTD mode for this connection.
     */
    public function enableZtd(): void
    {
        $this->execution->session()->enable();
    }

    /**
     * Disable ZTD mode for this connection.
     */
    public function disableZtd(): void
    {
        $this->execution->session()->disable();
    }

    /**
     * Check whether ZTD mode is enabled.
     */
    public function isZtdEnabled(): bool
    {
        return $this->execution->session()->isEnabled();
    }

    /**
     * Get the affected row count from the last ZTD or regular operation.
     *
     * Note: Direct property access ($this->affected_rows) is not supported
     * because PHP's C extension property handler for mysqli takes precedence
     * over __get when the parent constructor was not called. Use this method instead.
     */
    public function lastAffectedRows(): int
    {
        return $this->execution->affectedRows();
    }

    /**
     * Delegate property access to the inner mysqli instance.
     *
     * Handles affected_rows specially when ZTD has tracked affected rows.
     *
     * @return mixed
     */
    public function __get(string $name): mixed
    {
        $affectedRows = $this->execution->simulatedAffectedRows();
        if ($name === 'affected_rows' && $affectedRows !== null) {
            return $affectedRows;
        }

        return (new Native\MysqliPropertyReader())->read($this->execution->native(), $name);
    }

    /**
     * Delegate property isset check to the inner mysqli instance.
     */
    public function __isset(string $name): bool
    {
        return (new Native\MysqliPropertyReader())->read($this->execution->native(), $name) !== null;
    }

    /**
     * {@inheritDoc}
     *
     * @throws ZtdMysqliException When ZTD-specific exception occurs (wraps DatabaseException).
     * @throws mysqli_sql_exception When native execution fails.
     */
    #[Override]
    public function prepare(string $query): mysqli_stmt|false
    {
        return $this->execution->prepare($query, static fn (mysqli_stmt $statement, Session $session, RewritePlan $plan): ZtdMysqliStatement => new ZtdMysqliStatement($statement, $session, $plan));
    }

    /**
     * {@inheritDoc}
     *
     * @throws ZtdMysqliException When ZTD-specific exception occurs (wraps DatabaseException).
     * @throws mysqli_sql_exception When native execution fails.
     */
    #[Override]
    public function query(string $query, int $resultMode = MYSQLI_STORE_RESULT): mysqli_result|bool
    {
        return $this->execution->query($query, $resultMode, fn (string $sql): mysqli_result|bool => $this->execute_query($sql));
    }

    /**
     * {@inheritDoc}
     *
     * @throws ZtdMysqliException When ZTD-specific exception occurs (wraps DatabaseException).
     * @throws mysqli_sql_exception When native execution fails.
     */
    #[Override]
    public function real_query(string $query): bool
    {
        return $this->execution->realQuery($query, fn (string $sql): mysqli_stmt|false => $this->prepare($sql));
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function multi_query(string $query): bool
    {
        return $this->execution->native()->multi_query($query);
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function begin_transaction(int $flags = 0, ?string $name = null): bool
    {
        return $this->execution->beginTransaction($flags, $name);
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function commit(int $flags = 0, ?string $name = null): bool
    {
        return $this->execution->commit($flags, $name);
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function rollback(int $flags = 0, ?string $name = null): bool
    {
        return $this->execution->rollBack($flags, $name);
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function autocommit(bool $enable): bool
    {
        return $this->execution->autocommit($enable);
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    #[ReturnTypeWillChange]
    public function close()
    {
        $this->execution->native()->close();
        return true;
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function select_db(string $database): bool
    {
        return $this->execution->native()->select_db($database);
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function set_charset(string $charset): bool
    {
        return $this->execution->native()->set_charset($charset);
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function real_escape_string(string $string): string
    {
        return $this->execution->native()->real_escape_string($string);
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function escape_string(string $string): string
    {
        return $this->execution->native()->escape_string($string);
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function ping(): bool
    {
        return $this->execution->native()->ping();
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function character_set_name(): string
    {
        return $this->execution->native()->character_set_name();
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function change_user(string $username, #[SensitiveParameter] string $password, ?string $database): bool
    {
        return $this->execution->native()->change_user($username, $password, $database);
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function connect(
        ?string $hostname = null,
        ?string $username = null,
        #[SensitiveParameter] ?string $password = null,
        ?string $database = null,
        ?int $port = null,
        ?string $socket = null
    ): bool {
        /**
         * @var bool
         */
        return $this->execution->native()->connect($hostname, $username, $password, $database, $port, $socket);
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    #[ReturnTypeWillChange]
    public function debug(string $options)
    {
        $this->execution->native()->debug($options);
        return true;
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function dump_debug_info(): bool
    {
        return $this->execution->native()->dump_debug_info();
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function get_charset(): ?object
    {
        return $this->execution->native()->get_charset();
    }

    /**
     * {@inheritDoc}
     *
     * @deprecated 8.1
     */
    #[Override]
    public function get_client_info(): string
    {
        return $this->execution->native()->get_client_info();
    }

    /**
     * {@inheritDoc}
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function get_connection_stats(): array
    {

        return mysqli_get_connection_stats($this->execution->native());
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function get_server_info(): string
    {
        return $this->execution->native()->get_server_info();
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function get_warnings(): mysqli_warning|false
    {
        return $this->execution->native()->get_warnings();
    }

    /**
     * {@inheritDoc}
     *
     * @deprecated 8.1
     */
    #[Override]
    public function init(): ?bool
    {
        $this->execution->native()->init();

        return true;
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function kill(int $process_id): bool
    {
        return $this->execution->native()->kill($process_id);
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function more_results(): bool
    {
        return $this->execution->native()->more_results();
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function next_result(): bool
    {
        return $this->execution->native()->next_result();
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function options(int $option, mixed $value): bool
    {
        return $this->execution->native()->options($option, $value);
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function real_connect(
        ?string $hostname = null,
        ?string $username = null,
        #[SensitiveParameter] ?string $password = null,
        ?string $database = null,
        ?int $port = null,
        ?string $socket = null,
        int $flags = 0
    ): bool {
        return $this->execution->native()->real_connect($hostname, $username, $password, $database, $port, $socket, $flags);
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function reap_async_query(): mysqli_result|bool
    {
        return $this->execution->native()->reap_async_query();
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function refresh(int $flags): bool
    {
        return $this->execution->native()->refresh($flags);
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function release_savepoint(string $name): bool
    {
        return $this->execution->releaseSavepoint($name);
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function savepoint(string $name): bool
    {
        return $this->execution->savepoint($name);
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    #[ReturnTypeWillChange]
    public function ssl_set(
        ?string $key,
        ?string $certificate,
        ?string $ca_certificate,
        ?string $ca_path,
        ?string $cipher_algos
    ) {
        $this->execution->native()->ssl_set($key, $certificate, $ca_certificate, $ca_path, $cipher_algos);
        return true;
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function stat(): string|false
    {
        return $this->execution->native()->stat();
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function stmt_init(): mysqli_stmt
    {
        return $this->execution->native()->stmt_init();
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function store_result(int $mode = 0): mysqli_result|false
    {
        unset($mode);
        return $this->execution->native()->store_result();
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function thread_safe(): bool
    {
        return $this->execution->native()->thread_safe();
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function use_result(): mysqli_result|false
    {
        return $this->execution->native()->use_result();
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function set_opt(int $option, mixed $value): bool
    {
        return $this->execution->native()->set_opt($option, $value);
    }

    /**
     * {@inheritDoc}
     *
     * @param array<mixed, mixed>|null $read
     * @param array<mixed, mixed>|null $error
     * @param array<mixed, mixed> $reject
     * @param-out mixed $read
     * @param-out mixed $error
     * @param-out mixed $reject
     */
    #[Override]
    public static function poll(?array &$read, ?array &$error, array &$reject, int $seconds, int $microseconds = 0): int|false
    {
        /**
         * @var int|false
         */
        return mysqli::poll($read, $error, $reject, $seconds, $microseconds);
    }

    /**
     * {@inheritDoc}
     *
     * @param array<mixed, mixed>|null $params
     * @throws ZtdMysqliException When ZTD-specific exception occurs (wraps DatabaseException).
     * @throws mysqli_sql_exception When native execution fails.
     */
    public function execute_query(string $query, ?array $params = null): mysqli_result|bool
    {
        return $this->execution->executeQuery(
            $query,
            $params,
            fn (string $sql): mysqli_stmt|false => $this->prepare($sql),
            static fn (mysqli_stmt $statement): ?int => $statement instanceof ZtdMysqliStatement ? $statement->ztdAffectedRows() : null,
        );
    }
}
