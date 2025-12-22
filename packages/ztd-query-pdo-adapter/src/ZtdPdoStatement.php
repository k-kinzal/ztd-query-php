<?php

declare(strict_types=1);

namespace ZtdQuery\Adapter\Pdo;

use Iterator;
use PDO;
use PDOStatement as NativePdoStatement;
use ZtdQuery\ExecuteResult;
use ZtdQuery\Rewrite\RewritePlan;
use ZtdQuery\Session;

/**
 * PDOStatement wrapper that applies ZTD rewrite/simulation on execute().
 *
 * Uses delegation pattern: extends PDOStatement for type compatibility,
 * but delegates all operations to an inner PDOStatement instance.
 *
 * Properties are minimized:
 * - $statement: The prepared Statement (rewritten SQL when ZTD enabled)
 * - $session: Session for ZTD logic
 * - $plan: RewritePlan from prepare time (null when ZTD disabled)
 * - $result: Last execution result (temporary)
 */
final class ZtdPdoStatement extends NativePdoStatement
{
    /**
     * Inner PDOStatement to delegate operations to.
     * When ZTD is enabled, this is prepared with the rewritten SQL.
     */
    private NativePdoStatement $statement;

    /**
     * ZTD session context.
     */
    private Session $session;

    /**
     * Rewrite plan from prepare time (null when ZTD disabled).
     */
    private ?RewritePlan $plan;

    /**
     * Last execution result from Session.
     */
    private ?ExecuteResult $result = null;

    public function __construct(NativePdoStatement $statement, Session $session, ?RewritePlan $plan)
    {
        $this->statement = $statement;
        $this->session = $session;
        $this->plan = $plan;
    }

    /**
     * {@inheritDoc}
     */
    public function bindValue(int|string $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        return $this->statement->bindValue($param, $value, $type);
    }

    /**
     * {@inheritDoc}
     */
    public function bindParam(
        int|string $param,
        mixed &$var,
        int $type = PDO::PARAM_STR,
        int $maxLength = 0,
        mixed $driverOptions = null
    ): bool {
        return $this->statement->bindParam($param, $var, $type, $maxLength, $driverOptions);
    }

    /**
     * {@inheritDoc}
     */
    public function bindColumn(
        int|string $column,
        mixed &$var,
        int $type = PDO::PARAM_STR,
        int $maxLength = 0,
        mixed $driverOptions = null
    ): bool {
        return $this->statement->bindColumn($column, $var, $type, $maxLength, $driverOptions);
    }

    /**
     * Execute the statement, applying ZTD simulation as needed.
     *
     * @param array<int|string, mixed>|null $params
     */
    public function execute(?array $params = null): bool
    {
        $this->result = null;

        if ($this->plan === null) {
            return $this->statement->execute($params);
        }

        if (!$this->session->shouldExecute($this->plan)) {
            return false;
        }

        if (!$this->session->needsPostProcessing($this->plan)) {
            return $this->statement->execute($params);
        }

        if (!$this->statement->execute($params)) {
            return false;
        }

        $this->result = $this->session->processExecutedStatement(
            $this->plan,
            new PdoStatement($this->statement)
        );

        return $this->result->isSuccess();
    }

    /**
     * {@inheritDoc}
     */
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        if ($this->result !== null && !$this->result->isPassthrough()) {
            if (!$this->result->hasResultSet()) {
                return false;
            }
        }

        /** @see NativePdoStatement */
        return $this->statement->fetch($mode, $cursorOrientation, $cursorOffset);
    }

    /**
     * {@inheritDoc}
     *
     * @return array<int, mixed>
     */
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        if ($this->result !== null && !$this->result->isPassthrough()) {
            if (!$this->result->hasResultSet()) {
                return [];
            }
        }

        /** @see NativePdoStatement */
        $forwardArgs = [];
        foreach ($args as $arg) {
            if (is_int($arg) || is_string($arg) || is_callable($arg)) {
                $forwardArgs[] = $arg;
            }
        }
        /** @var array<int, mixed> $rows */
        $rows = $this->statement->fetchAll($mode, ...$forwardArgs);

        return $rows;
    }

    /**
     * {@inheritDoc}
     */
    public function fetchColumn(int $column = 0): mixed
    {
        if ($this->result !== null && !$this->result->isPassthrough()) {
            if (!$this->result->hasResultSet()) {
                return false;
            }
        }

        /** @see NativePdoStatement */
        return $this->statement->fetchColumn($column);
    }

    /**
     * {@inheritDoc}
     *
     * @template T of object
     * @param class-string<T>|null $class
     * @param array<mixed> $constructorArgs
     * @return T|false
     */
    public function fetchObject(?string $class = 'stdClass', array $constructorArgs = []): object|false
    {
        /** @var class-string<T> $resolvedClass */
        $resolvedClass = $class ?? 'stdClass';

        if ($this->result !== null && !$this->result->isPassthrough()) {
            if (!$this->result->hasResultSet()) {
                return false;
            }
        }

        /** @see NativePdoStatement */
        return $this->statement->fetchObject($resolvedClass, $constructorArgs);
    }

    /**
     * {@inheritDoc}
     */
    public function rowCount(): int
    {
        if ($this->result !== null && !$this->result->isPassthrough()) {
            return $this->result->rowCount();
        }

        return $this->statement->rowCount();
    }

    /**
     * {@inheritDoc}
     */
    public function closeCursor(): bool
    {
        return $this->statement->closeCursor();
    }

    /**
     * {@inheritDoc}
     */
    #[\ReturnTypeWillChange]
    public function setFetchMode(int $mode, mixed ...$args): bool
    {
        return $this->statement->setFetchMode($mode, ...$args);
    }

    /**
     * {@inheritDoc}
     */
    public function errorCode(): string
    {
        return $this->statement->errorCode() ?? '';
    }

    /**
     * {@inheritDoc}
     *
     * @return array{0: string|null, 1: int|null, 2: string|null}
     */
    public function errorInfo(): array
    {
        /** @var array{0: string|null, 1: int|null, 2: string|null} */
        return $this->statement->errorInfo();
    }

    /**
     * {@inheritDoc}
     */
    public function getAttribute(int $name): mixed
    {
        return $this->statement->getAttribute($name);
    }

    /**
     * {@inheritDoc}
     */
    public function setAttribute(int $attribute, mixed $value): bool
    {
        return $this->statement->setAttribute($attribute, $value);
    }

    /**
     * {@inheritDoc}
     */
    public function columnCount(): int
    {
        return $this->statement->columnCount();
    }

    /**
     * {@inheritDoc}
     */
    public function getColumnMeta(int $column): array|false
    {
        return $this->statement->getColumnMeta($column);
    }

    /**
     * {@inheritDoc}
     */
    public function nextRowset(): bool
    {
        return $this->statement->nextRowset();
    }

    /**
     * {@inheritDoc}
     */
    #[\ReturnTypeWillChange]
    public function debugDumpParams(): bool|null
    {
        $this->statement->debugDumpParams();

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function getIterator(): Iterator
    {
        /** @var Iterator<mixed, array<int|string, mixed>> $iterator */
        $iterator = $this->statement->getIterator();

        return $iterator;
    }
}
