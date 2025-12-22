<?php

declare(strict_types=1);

namespace ZtdQuery\Adapter\Pdo;

use Iterator;
use ZtdQuery\ZteSession;
use PDO;
use PDOStatement;

/**
 * PDOStatement wrapper that applies ZTD rewrite/simulation on execute().
 *
 * Uses delegation pattern: extends PDOStatement for type compatibility,
 * but delegates all operations to an inner PDOStatement instance.
 */
final class ZtdStatement extends PDOStatement
{
    /**
     * Inner PDOStatement to delegate operations to.
     */
    private PDOStatement $delegate;

    /**
     * ZTD session context.
     */
    private ZteSession $session;

    /**
     * Owning ZtdPdo connection.
     */
    private ZtdPdo $pdo;

    /**
     * Delegate statement used for rewritten SQL execution.
     */
    private ?PDOStatement $rewrittenDelegate = null;

    /**
     * Buffered rows for simulated write results.
     *
     * @var array<int, array<string, mixed>>|null
     */
    private ?array $bufferedRows = null;

    /**
     * Current index into buffered rows.
     */
    private int $bufferIndex = 0;

    /**
     * Row count override for simulated writes.
     */
    private ?int $rowCountOverride = null;

    /**
     * Captured bound values for replay on rewritten statements.
     *
     * @var array<int, array{param: int|string, value: mixed, type: int}>
     */
    private array $boundValues = [];

    /**
     * Captured bound parameters for replay on rewritten statements.
     *
     * @var array<int, array{param: int|string, var: mixed, type: int, length: int, driverOptions: mixed}>
     */
    private array $boundParams = [];

    /**
     * Original query string for this statement.
     */
    private string $query;

    public function __construct(
        PDOStatement $delegate,
        ZteSession $session,
        ZtdPdo $pdo,
        string $query
    ) {
        // Do not call parent constructor
        $this->delegate = $delegate;
        $this->session = $session;
        $this->pdo = $pdo;
        $this->query = $query;
    }

    /**
     * {@inheritDoc}
     */
    public function bindValue(int|string $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        $this->boundValues[] = ['param' => $param, 'value' => $value, 'type' => $type];

        return $this->delegate->bindValue($param, $value, $type);
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
        $this->boundParams[] = [
            'param' => $param,
            'var' => &$var,
            'type' => $type,
            'length' => $maxLength,
            'driverOptions' => $driverOptions,
        ];

        return $this->delegate->bindParam($param, $var, $type, $maxLength, $driverOptions);
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
        return $this->delegate->bindColumn($column, $var, $type, $maxLength, $driverOptions);
    }

    /**
     * Execute the statement, rewriting and simulating as needed.
     *
     * @param array<int|string, mixed>|null $params
     */
    public function execute(?array $params = null): bool
    {
        $this->resetBuffer();

        if (!$this->session->isEnabled()) {
            return $this->delegate->execute($params);
        }

        $result = $this->session->executeStatement(
            $this->query,
            $params,
            fn (string $sql, ?array $params) => $this->prepareAndExecute($sql, $params)
        );

        if ($result['passthrough']) {
            return $this->delegate->execute($params);
        }

        if (!$result['success']) {
            return false;
        }

        $this->rewrittenDelegate = $result['rewrittenStatement'];
        $this->bufferedRows = $result['rows'] ?: null;
        $this->rowCountOverride = count($result['rows']) > 0 ? count($result['rows']) : null;

        return true;
    }

    /**
     * Prepare and execute a rewritten SQL statement.
     *
     * @param array<int|string, mixed>|null $params
     */
    private function prepareAndExecute(string $sql, ?array $params): PDOStatement|false
    {
        $statement = $this->pdo->getInnerPdo()->prepare($sql);
        if ($statement === false) {
            return false;
        }

        if ($params !== null) {
            $executed = $statement->execute($params);
        } else {
            $this->applyBindings($statement);
            $executed = $statement->execute();
        }

        if (!$executed) {
            return false;
        }

        return $statement;
    }

    /**
     * {@inheritDoc}
     */
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        if ($this->bufferedRows !== null) {
            if ($this->bufferIndex >= count($this->bufferedRows)) {
                return false;
            }
            $row = $this->bufferedRows[$this->bufferIndex];
            $this->bufferIndex++;

            return $row;
        }

        if ($this->rewrittenDelegate !== null) {
            return $this->rewrittenDelegate->fetch($mode, $cursorOrientation, $cursorOffset);
        }

        return $this->delegate->fetch($mode, $cursorOrientation, $cursorOffset);
    }

    /**
     * {@inheritDoc}
     *
     * @return array<int, mixed>
     */
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        if ($this->bufferedRows !== null) {
            $rows = array_slice($this->bufferedRows, $this->bufferIndex);
            $this->bufferIndex = count($this->bufferedRows);

            return $rows;
        }

        if ($this->rewrittenDelegate !== null) {
            $forwardArgs = [];
            foreach ($args as $arg) {
                if (is_int($arg) || is_string($arg) || is_callable($arg)) {
                    $forwardArgs[] = $arg;
                }
            }
            /** @var array<int, mixed> $rows */
            $rows = $this->rewrittenDelegate->fetchAll($mode, ...$forwardArgs);
            return $rows;
        }

        $forwardArgs = [];
        foreach ($args as $arg) {
            if (is_int($arg) || is_string($arg) || is_callable($arg)) {
                $forwardArgs[] = $arg;
            }
        }
        /** @var array<int, mixed> $rows */
        $rows = $this->delegate->fetchAll($mode, ...$forwardArgs);
        return $rows;
    }

    /**
     * {@inheritDoc}
     */
    public function fetchColumn(int $column = 0): mixed
    {
        if ($this->rewrittenDelegate !== null) {
            return $this->rewrittenDelegate->fetchColumn($column);
        }

        return $this->delegate->fetchColumn($column);
    }

    /**
     * {@inheritDoc}
     *
     * @template T of object
     * @param class-string<T>|null $class
     * @param array<int, mixed> $constructorArgs
     * @return T|false
     */
    public function fetchObject(?string $class = 'stdClass', array $constructorArgs = []): object|false
    {
        /** @var class-string<T> $resolvedClass */
        $resolvedClass = $class ?? 'stdClass';

        if ($this->rewrittenDelegate !== null) {
            return $this->rewrittenDelegate->fetchObject($resolvedClass, $constructorArgs);
        }

        return $this->delegate->fetchObject($resolvedClass, $constructorArgs);
    }

    /**
     * {@inheritDoc}
     */
    public function rowCount(): int
    {
        if ($this->rowCountOverride !== null) {
            return $this->rowCountOverride;
        }

        if ($this->rewrittenDelegate !== null) {
            return $this->rewrittenDelegate->rowCount();
        }

        return $this->delegate->rowCount();
    }

    /**
     * {@inheritDoc}
     */
    public function closeCursor(): bool
    {
        if ($this->rewrittenDelegate !== null) {
            return $this->rewrittenDelegate->closeCursor();
        }

        return $this->delegate->closeCursor();
    }

    /**
     * {@inheritDoc}
     */
    #[\ReturnTypeWillChange]
    public function setFetchMode(int $mode, mixed ...$args): bool
    {
        return $this->delegate->setFetchMode($mode, ...$args);
    }

    /**
     * {@inheritDoc}
     */
    public function errorCode(): ?string
    {
        if ($this->rewrittenDelegate !== null) {
            return $this->rewrittenDelegate->errorCode();
        }

        return $this->delegate->errorCode();
    }

    /**
     * {@inheritDoc}
     *
     * @return array{0: string|null, 1: int|null, 2: string|null}
     */
    public function errorInfo(): array
    {
        if ($this->rewrittenDelegate !== null) {
            /** @var array{0: string|null, 1: int|null, 2: string|null} */
            return $this->rewrittenDelegate->errorInfo();
        }

        /** @var array{0: string|null, 1: int|null, 2: string|null} */
        return $this->delegate->errorInfo();
    }

    /**
     * {@inheritDoc}
     */
    public function getAttribute(int $name): mixed
    {
        return $this->delegate->getAttribute($name);
    }

    /**
     * {@inheritDoc}
     */
    public function setAttribute(int $attribute, mixed $value): bool
    {
        return $this->delegate->setAttribute($attribute, $value);
    }

    /**
     * {@inheritDoc}
     */
    public function columnCount(): int
    {
        if ($this->rewrittenDelegate !== null) {
            return $this->rewrittenDelegate->columnCount();
        }

        return $this->delegate->columnCount();
    }

    /**
     * {@inheritDoc}
     */
    public function getColumnMeta(int $column): array|false
    {
        if ($this->rewrittenDelegate !== null) {
            return $this->rewrittenDelegate->getColumnMeta($column);
        }

        return $this->delegate->getColumnMeta($column);
    }

    /**
     * {@inheritDoc}
     */
    public function nextRowset(): bool
    {
        if ($this->rewrittenDelegate !== null) {
            return $this->rewrittenDelegate->nextRowset();
        }

        return $this->delegate->nextRowset();
    }

    /**
     * {@inheritDoc}
     */
    #[\ReturnTypeWillChange]
    public function debugDumpParams(): bool|null
    {
        $this->delegate->debugDumpParams();

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function getIterator(): Iterator
    {
        if ($this->rewrittenDelegate !== null) {
            return $this->rewrittenDelegate->getIterator();
        }

        return $this->delegate->getIterator();
    }

    private function resetBuffer(): void
    {
        $this->rewrittenDelegate = null;
        $this->bufferedRows = null;
        $this->bufferIndex = 0;
        $this->rowCountOverride = null;
    }

    private function applyBindings(PDOStatement $statement): void
    {
        foreach ($this->boundValues as $binding) {
            $statement->bindValue($binding['param'], $binding['value'], $binding['type']);
        }

        foreach ($this->boundParams as $binding) {
            $statement->bindParam(
                $binding['param'],
                $binding['var'],
                $binding['type'],
                $binding['length'],
                $binding['driverOptions']
            );
        }
    }
}
