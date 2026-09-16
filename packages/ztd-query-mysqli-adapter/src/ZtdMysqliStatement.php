<?php

declare(strict_types=1);

namespace ZtdQuery\Adapter\Mysqli;

use mysqli_result;
use mysqli_stmt;
use mysqli_warning;
use Override;
use ReturnTypeWillChange;
use ZtdQuery\Adapter\Mysqli\Native\MysqliStatementBindingBridge;
use ZtdQuery\Adapter\Mysqli\Native\MysqliStatementPropertyReader;
use ZtdQuery\Adapter\Mysqli\Session\StatementExecution;
use ZtdQuery\Rewrite\RewritePlan;
use ZtdQuery\Session;

/**
 * mysqli_stmt wrapper that applies ZTD rewrite/simulation on execute().
 *
 * Uses delegation pattern: extends mysqli_stmt for type compatibility,
 * but delegates all operations to an inner mysqli_stmt instance.
 *
 * All public methods are explicitly overridden to prevent parent class
 * implementation from being called.
 *
 * Properties are delegated via __get/__isset to the delegate instance.
 *
 * @visibility public
 * @example Execute a parameterized simulated insert
 *     $container = \Testcontainers\Testcontainers::run(\Container\MySql80Container::class);
 *     $native = new \mysqli(str_replace('localhost', '127.0.0.1', $container->getHost()), 'root', 'root', 'test', $container->getMappedPort(3306));
 *     $native->set_charset('utf8mb4');
 *     $native->query('CREATE TABLE contacts (id INT PRIMARY KEY, name VARCHAR(100))');
 *     $ztd = \ZtdQuery\Adapter\Mysqli\ZtdMysqli::fromMysqli($native);
 *     $statement = $ztd->prepare('INSERT INTO contacts VALUES (?, ?)');
 *     $statement->execute([7, 'Alice']) // => true
 *     $statement->ztdAffectedRows() // => 1
 *     $ztd->query('SELECT name FROM contacts')->fetch_all(MYSQLI_ASSOC) // => [['name' => 'Alice']]
 *     $native->query('SELECT name FROM contacts')->fetch_all(MYSQLI_ASSOC) // => []
 *     $native->query('DROP TABLE contacts');
 *     $container->stop();
 */
final class ZtdMysqliStatement extends MysqliStatementBindingBridge
{
    private StatementExecution $execution;

    /**
     * Wrap the prepared statement with its session and optional rewrite plan.
     */
    public function __construct(mysqli_stmt $delegate, Session $session, ?RewritePlan $plan)
    {
        parent::__construct($delegate);
        $this->execution = new StatementExecution($delegate, $session, $plan);
    }

    /**
     * Delegate property access to the delegate instance.
     *
     * Handles affected_rows and num_rows specially when ZTD result is available.
     *
     * @return mixed
     */
    public function __get(string $name): mixed
    {
        $result = $this->execution->result();
        if ($result !== null && !$result->isPassthrough() && in_array($name, ['affected_rows', 'num_rows'], true)) {
            return $result->rowCount();
        }
        return (new MysqliStatementPropertyReader())->read($this->execution->native(), $name);
    }

    /**
     * Delegate property isset check to the delegate instance.
     */
    public function __isset(string $name): bool
    {
        return in_array($name, [
            'affected_rows',
            'insert_id',
            'num_rows',
            'param_count',
            'field_count',
            'errno',
            'error',
            'error_list',
            'sqlstate',
            'id',
        ], true);
    }

    /**
     * Get affected rows for ZTD results.
     *
     * This method exists because mysqli_stmt's C extension property handler
     * takes precedence over __get, making $stmt->affected_rows inaccessible
     * when the parent constructor was not called. This provides a safe
     * alternative for ZtdMysqli to query affected rows after execution.
     *
     * @return int The number of affected rows from ZTD processing, or from the delegate.
     */
    public function ztdAffectedRows(): int
    {
        return $this->execution->affectedRows();
    }

    /**
     * Execute the statement, applying ZTD simulation as needed.
     *
     * @param array<mixed, mixed>|null $params Optional parameters to bind (PHP 8.1+).
     * @throws ZtdMysqliException When the session cannot process the native result.
     */
    #[Override]
    public function execute(?array $params = null): bool
    {
        return $this->execution->execute($params);
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function get_result(): mysqli_result|false
    {
        return $this->execution->getResult();
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function fetch(): ?bool
    {
        return $this->execution->fetch();
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
    public function free_result(): void
    {
        $this->execution->native()->free_result();
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function reset(): bool
    {
        return $this->execution->reset();
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function store_result(): bool
    {
        return $this->execution->native()->store_result();
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function data_seek(int $offset): void
    {
        $this->execution->native()->data_seek($offset);
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function result_metadata(): mysqli_result|false
    {
        return $this->execution->native()->result_metadata();
    }

    /**
     * {@inheritDoc}
     * @throws ZtdMysqliException When the native attribute cannot be read.
     */
    #[Override]
    public function attr_get(int $attribute): int
    {
        $value = $this->execution->native()->attr_get($attribute);
        if ($value === false) {
            throw new ZtdMysqliException('Unable to read the mysqli statement attribute.');
        }

        return $value;
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function attr_set(int $attribute, int $value): bool
    {
        return $this->execution->native()->attr_set($attribute, $value);
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
    public function num_rows(): int|string
    {
        $result = $this->execution->result();
        if ($result !== null && !$result->isPassthrough()) {
            return $result->rowCount();
        }

        return $this->execution->native()->num_rows();
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function prepare(string $query): bool
    {
        return $this->execution->native()->prepare($query);
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function send_long_data(int $param_num, string $data): bool
    {
        return $this->execution->native()->send_long_data($param_num, $data);
    }
}
