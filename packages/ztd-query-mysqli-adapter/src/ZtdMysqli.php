<?php

declare(strict_types=1);

namespace ZtdQuery\Adapter\Mysqli;

use mysqli;
use mysqli_result;
use mysqli_stmt;
use mysqli_warning;
use ReflectionClass;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Connection\Exception\DatabaseException;
use ZtdQuery\Platform\MySql\MySqlSessionFactory;
use ZtdQuery\Session;
use ZtdQuery\Platform\SessionFactory;

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
 */
class ZtdMysqli extends mysqli
{
    /**
     * ZTD session context for this connection.
     */
    private Session $session;

    /**
     * Inner mysqli instance for delegation.
     */
    private mysqli $innerMysqli;

    /**
     * Last affected row count from ZTD operations.
     */
    private ?int $ztdAffectedRowCount = null;

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
        // Parent is initialized without connection; innerMysqli handles the real connection
        parent::__construct();
        $this->innerMysqli = new mysqli($hostname, $username, $password, $database, $port ?? 3306, $socket);

        $resolvedFactory = $factory ?? new MySqlSessionFactory();
        $connection = new MysqliConnection($this->innerMysqli);
        $this->session = $resolvedFactory->create($connection, $config ?? ZtdConfig::default());
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
        /** @var self $instance */
        $instance = (new ReflectionClass(self::class))->newInstanceWithoutConstructor();
        $instance->innerMysqli = $mysqli;

        $resolvedFactory = $factory ?? new MySqlSessionFactory();
        $connection = new MysqliConnection($instance->innerMysqli);
        $instance->session = $resolvedFactory->create($connection, $config ?? ZtdConfig::default());

        return $instance;
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
     * Get the affected row count from the last ZTD or regular operation.
     *
     * Note: Direct property access ($this->affected_rows) is not supported
     * because PHP's C extension property handler for mysqli takes precedence
     * over __get when the parent constructor was not called. Use this method instead.
     */
    public function lastAffectedRows(): int
    {
        if ($this->ztdAffectedRowCount !== null) {
            return $this->ztdAffectedRowCount;
        }

        return (int) $this->innerMysqli->affected_rows;
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
        if ($name === 'affected_rows' && $this->ztdAffectedRowCount !== null) {
            return $this->ztdAffectedRowCount;
        }

        return $this->readMysqliProperty($name);
    }

    /**
     * Delegate property isset check to the inner mysqli instance.
     */
    public function __isset(string $name): bool
    {
        return $this->readMysqliProperty($name) !== null;
    }

    /**
     * Read a known mysqli property from the inner instance.
     */
    private function readMysqliProperty(string $name): mixed
    {
        return match ($name) {
            'affected_rows' => $this->innerMysqli->affected_rows,
            'client_info' => $this->innerMysqli->client_info,
            'client_version' => $this->innerMysqli->client_version,
            'connect_errno' => $this->innerMysqli->connect_errno,
            'connect_error' => $this->innerMysqli->connect_error,
            'errno' => $this->innerMysqli->errno,
            'error' => $this->innerMysqli->error,
            'error_list' => $this->innerMysqli->error_list,
            'field_count' => $this->innerMysqli->field_count,
            'host_info' => $this->innerMysqli->host_info,
            'info' => $this->innerMysqli->info,
            'insert_id' => $this->innerMysqli->insert_id,
            'server_info' => $this->innerMysqli->server_info,
            'server_version' => $this->innerMysqli->server_version,
            'sqlstate' => $this->innerMysqli->sqlstate,
            'protocol_version' => $this->innerMysqli->protocol_version,
            'thread_id' => $this->innerMysqli->thread_id,
            'warning_count' => $this->innerMysqli->warning_count,
            default => null,
        };
    }

    /**
     * {@inheritDoc}
     *
     * @throws ZtdMysqliException When ZTD-specific exception occurs (wraps DatabaseException).
     */
    public function prepare(string $query): mysqli_stmt|false
    {
        if (!$this->session->isEnabled()) {
            return $this->innerMysqli->prepare($query);
        }

        try {
            $plan = $this->session->rewrite($query);
        } catch (DatabaseException $e) {
            throw new ZtdMysqliException($e->getMessage(), 0, $e);
        }

        $stmt = $this->innerMysqli->prepare($plan->sql());
        if ($stmt === false) {
            return false;
        }

        return new ZtdMysqliStatement($stmt, $this->session, $plan);
    }

    /**
     * {@inheritDoc}
     *
     * @throws ZtdMysqliException When ZTD-specific exception occurs (wraps DatabaseException).
     */
    public function query(string $query, int $resultMode = MYSQLI_STORE_RESULT): mysqli_result|bool
    {
        if (!$this->session->isEnabled()) {
            $this->ztdAffectedRowCount = null;
            return $this->innerMysqli->query($query, $resultMode);
        }

        $stmt = $this->prepare($query);
        if ($stmt === false) {
            return false;
        }

        if (!$stmt->execute()) {
            return false;
        }

        // Cannot use $stmt->affected_rows because mysqli_stmt's C extension
        // property handler takes precedence over __get when parent constructor was not called.
        if ($stmt instanceof ZtdMysqliStatement) {
            $this->ztdAffectedRowCount = $stmt->ztdAffectedRows();
        } else {
            $this->ztdAffectedRowCount = null;
        }

        $result = $stmt->get_result();
        if ($result === false) {
            return true;
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     *
     * @throws ZtdMysqliException When ZTD-specific exception occurs (wraps DatabaseException).
     */
    public function real_query(string $query): bool
    {
        if (!$this->session->isEnabled()) {
            return $this->innerMysqli->real_query($query);
        }

        $stmt = $this->prepare($query);
        if ($stmt === false) {
            return false;
        }

        return $stmt->execute();
    }

    /**
     * {@inheritDoc}
     */
    public function multi_query(string $query): bool
    {
        return $this->innerMysqli->multi_query($query);
    }

    /**
     * {@inheritDoc}
     */
    public function begin_transaction(int $flags = 0, ?string $name = null): bool
    {
        return $this->innerMysqli->begin_transaction($flags, $name);
    }

    /**
     * {@inheritDoc}
     */
    public function commit(int $flags = 0, ?string $name = null): bool
    {
        return $this->innerMysqli->commit($flags, $name);
    }

    /**
     * {@inheritDoc}
     */
    public function rollback(int $flags = 0, ?string $name = null): bool
    {
        return $this->innerMysqli->rollback($flags, $name);
    }

    /**
     * {@inheritDoc}
     */
    public function autocommit(bool $enable): bool
    {
        return $this->innerMysqli->autocommit($enable);
    }

    /**
     * {@inheritDoc}
     */
    #[\ReturnTypeWillChange]
    public function close()
    {
        $this->innerMysqli->close();
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function select_db(string $database): bool
    {
        return $this->innerMysqli->select_db($database);
    }

    /**
     * {@inheritDoc}
     */
    public function set_charset(string $charset): bool
    {
        return $this->innerMysqli->set_charset($charset);
    }

    /**
     * {@inheritDoc}
     */
    public function real_escape_string(string $string): string
    {
        return $this->innerMysqli->real_escape_string($string);
    }

    /**
     * {@inheritDoc}
     */
    public function escape_string(string $string): string
    {
        return $this->innerMysqli->escape_string($string);
    }

    /**
     * {@inheritDoc}
     */
    public function ping(): bool
    {
        return $this->innerMysqli->ping();
    }

    /**
     * {@inheritDoc}
     */
    public function character_set_name(): string
    {
        return $this->innerMysqli->character_set_name();
    }

    /**
     * {@inheritDoc}
     */
    public function change_user(string $username, #[\SensitiveParameter] string $password, ?string $database): bool
    {
        return $this->innerMysqli->change_user($username, $password, $database);
    }

    /**
     * {@inheritDoc}
     */
    public function connect(
        ?string $hostname = null,
        ?string $username = null,
        #[\SensitiveParameter] ?string $password = null,
        ?string $database = null,
        ?int $port = null,
        ?string $socket = null
    ): bool {
        /** @var bool */
        return $this->innerMysqli->connect($hostname, $username, $password, $database, $port, $socket);
    }

    /**
     * {@inheritDoc}
     */
    #[\ReturnTypeWillChange]
    public function debug(string $options)
    {
        $this->innerMysqli->debug($options);
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function dump_debug_info(): bool
    {
        return $this->innerMysqli->dump_debug_info();
    }

    /**
     * {@inheritDoc}
     */
    public function get_charset(): ?object
    {
        return $this->innerMysqli->get_charset();
    }

    /**
     * {@inheritDoc}
     *
     * @deprecated 8.1
     */
    public function get_client_info(): string
    {
        return $this->innerMysqli->get_client_info();
    }

    /**
     * {@inheritDoc}
     *
     * @return array<string, mixed>
     */
    public function get_connection_stats(): array
    {
        /** @var array<string, mixed> */
        return $this->innerMysqli->get_connection_stats();
    }

    /**
     * {@inheritDoc}
     */
    public function get_server_info(): string
    {
        return $this->innerMysqli->get_server_info();
    }

    /**
     * {@inheritDoc}
     */
    public function get_warnings(): mysqli_warning|false
    {
        return $this->innerMysqli->get_warnings();
    }

    /**
     * {@inheritDoc}
     *
     * @deprecated 8.1
     */
    public function init(): ?bool
    {
        $this->innerMysqli->init();

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function kill(int $process_id): bool
    {
        return $this->innerMysqli->kill($process_id);
    }

    /**
     * {@inheritDoc}
     */
    public function more_results(): bool
    {
        return $this->innerMysqli->more_results();
    }

    /**
     * {@inheritDoc}
     */
    public function next_result(): bool
    {
        return $this->innerMysqli->next_result();
    }

    /**
     * {@inheritDoc}
     */
    public function options(int $option, mixed $value): bool
    {
        return $this->innerMysqli->options($option, $value);
    }

    /**
     * {@inheritDoc}
     */
    public function real_connect(
        ?string $hostname = null,
        ?string $username = null,
        #[\SensitiveParameter] ?string $password = null,
        ?string $database = null,
        ?int $port = null,
        ?string $socket = null,
        int $flags = 0
    ): bool {
        return $this->innerMysqli->real_connect($hostname, $username, $password, $database, $port, $socket, $flags);
    }

    /**
     * {@inheritDoc}
     */
    public function reap_async_query(): mysqli_result|bool
    {
        return $this->innerMysqli->reap_async_query();
    }

    /**
     * {@inheritDoc}
     */
    public function refresh(int $flags): bool
    {
        return $this->innerMysqli->refresh($flags);
    }

    /**
     * {@inheritDoc}
     */
    public function release_savepoint(string $name): bool
    {
        return $this->innerMysqli->release_savepoint($name);
    }

    /**
     * {@inheritDoc}
     */
    public function savepoint(string $name): bool
    {
        return $this->innerMysqli->savepoint($name);
    }

    /**
     * {@inheritDoc}
     */
    #[\ReturnTypeWillChange]
    public function ssl_set(
        ?string $key,
        ?string $certificate,
        ?string $ca_certificate,
        ?string $ca_path,
        ?string $cipher_algos
    ) {
        $this->innerMysqli->ssl_set($key, $certificate, $ca_certificate, $ca_path, $cipher_algos);
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function stat(): string|false
    {
        return $this->innerMysqli->stat();
    }

    /**
     * {@inheritDoc}
     */
    public function stmt_init(): mysqli_stmt
    {
        return $this->innerMysqli->stmt_init();
    }

    /**
     * {@inheritDoc}
     */
    public function store_result(int $mode = 0): mysqli_result|false
    {
        return $this->innerMysqli->store_result($mode);
    }

    /**
     * {@inheritDoc}
     */
    public function thread_safe(): bool
    {
        return $this->innerMysqli->thread_safe();
    }

    /**
     * {@inheritDoc}
     */
    public function use_result(): mysqli_result|false
    {
        return $this->innerMysqli->use_result();
    }

    /**
     * {@inheritDoc}
     */
    public function set_opt(int $option, mixed $value): bool
    {
        return $this->innerMysqli->set_opt($option, $value);
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
    public static function poll(?array &$read, ?array &$error, array &$reject, int $seconds, int $microseconds = 0): int|false
    {
        /** @var int|false */
        return mysqli::poll($read, $error, $reject, $seconds, $microseconds);
    }

    /**
     * {@inheritDoc}
     *
     * @param array<mixed, mixed>|null $params
     * @throws ZtdMysqliException When ZTD-specific exception occurs (wraps DatabaseException).
     */
    public function execute_query(string $query, ?array $params = null): mysqli_result|bool
    {
        if (!$this->session->isEnabled()) {
            return $this->innerMysqli->execute_query($query, $params);
        }

        $stmt = $this->prepare($query);
        if ($stmt === false) {
            return false;
        }

        if ($params !== null) {
            if (!$stmt->execute($params)) {
                return false;
            }
        } else {
            if (!$stmt->execute()) {
                return false;
            }
        }

        if ($stmt instanceof ZtdMysqliStatement) {
            $this->ztdAffectedRowCount = $stmt->ztdAffectedRows();
        } else {
            $this->ztdAffectedRowCount = null;
        }

        $result = $stmt->get_result();
        if ($result === false) {
            return true;
        }

        return $result;
    }
}
