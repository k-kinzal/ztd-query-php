<?php

declare(strict_types=1);

namespace ZtdQuery\Adapter\Pdo;

use ArrayIterator;
use Iterator;
use Override;
use PDO;
use PDOStatement as NativePdoStatement;
use ReflectionObject;
use ReturnTypeWillChange;
use stdClass;
use ZtdQuery\Connection\Exception\DatabaseException;
use ZtdQuery\Connection\StatementInterface;
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
 *
 * @phpstan-import-type Row from StatementInterface
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

    private ?PdoPreparedExecution $preparedExecution;

    private int $defaultFetchMode;

    /** @var array<int|string, array{value: mixed, type: int}> */
    private array $boundValues = [];

    /** @var array<int|string, array{value: mixed, type: int, maxLength: int, driverOptions: mixed}> */
    private array $boundParams = [];

    /** @var array{mode: int, args: array<mixed>}|null */
    private ?array $fetchMode = null;

    /**
     * Binds the instance to what it will work from.
     *
     * @param NativePdoStatement $statement
     * @param Session $session
     * @param ?RewritePlan $plan
     * @param ?PdoPreparedExecution $preparedExecution
     * @param int $defaultFetchMode
     */
    public function __construct(
        NativePdoStatement $statement,
        Session $session,
        ?RewritePlan $plan,
        ?PdoPreparedExecution $preparedExecution = null,
        int $defaultFetchMode = PDO::FETCH_BOTH,
    ) {
        $this->statement = $statement;
        $this->session = $session;
        $this->plan = $plan;
        $this->preparedExecution = $preparedExecution;
        $this->defaultFetchMode = $defaultFetchMode;
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function bindValue(int|string $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        $this->boundValues[$param] = ['value' => $value, 'type' => $type];

        return $this->statement->bindValue($param, $value, $type);
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function bindParam(
        int|string $param,
        mixed &$var,
        int $type = PDO::PARAM_STR,
        int $maxLength = 0,
        mixed $driverOptions = null
    ): bool {
        $this->boundParams[$param] = [
            'value' => &$var,
            'type' => $type,
            'maxLength' => $maxLength,
            'driverOptions' => $driverOptions,
        ];

        return $this->statement->bindParam($param, $var, $type, $maxLength, $driverOptions);
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
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
    #[Override]
    public function execute(?array $params = null): bool
    {
        $this->result = null;

        if ($this->preparedExecution !== null) {
            $prepared = $this->preparedExecution->prepare($params);
            $this->statement = $prepared['statement'];
            $this->plan = $prepared['plan'];
            $params = $prepared['params'];
            $this->rebindParameters();
            if ($this->fetchMode !== null) {
                $this->statement->setFetchMode($this->fetchMode['mode'], ...$this->fetchMode['args']);
            }
        }

        if ($this->plan === null) {
            return $this->executeStatement($params);
        }

        if (!$this->session->shouldExecute($this->plan)) {
            return false;
        }

        if ($this->session->needsPostProcessing($this->plan)) {
            return $this->executeAndPostProcess($this->plan, $params);
        }

        return $this->executeStatement($params);
    }

    /**
     * @param array<int|string, mixed>|null $params
     *
     * @throws ZtdPdoException
     */
    private function executeAndPostProcess(RewritePlan $plan, ?array $params): bool
    {
        if (!$this->executeStatement($params)) {
            return false;
        }

        try {
            $this->result = $this->session->processExecutedStatement(
                $plan,
                new PdoStatement($this->statement)
            );
        } catch (DatabaseException $e) {
            throw new ZtdPdoException($e->getMessage(), 0, $e);
        }

        return $this->result->isSuccess();
    }

    /** @param array<int|string, mixed>|null $params */
    private function executeStatement(?array $params): bool
    {
        if ($this->preparedExecution === null) {
            return $this->statement->execute($params);
        }

        return $this->preparedExecution->parameterBinder()->execute($this->statement, $params);
    }

    private function rebindParameters(): void
    {
        foreach ($this->boundValues as $parameter => $binding) {
            $this->statement->bindValue($parameter, $binding['value'], $binding['type']);
        }
        foreach (array_keys($this->boundParams) as $parameter) {
            $binding = &$this->boundParams[$parameter];
            $this->statement->bindParam(
                $parameter,
                $binding['value'],
                $binding['type'],
                $binding['maxLength'],
                $binding['driverOptions'],
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        if ($this->result !== null && !$this->result->isPassthrough()) {
            if (!$this->result->hasResultSet()) {
                return false;
            }

            $row = $this->result->fetch();
            if ($row === false) {
                return false;
            }

            return $this->formatBufferedRow($row, $mode);
        }

        /** @see NativePdoStatement */
        return $this->statement->fetch($mode, $cursorOrientation, $cursorOffset);
    }

    /**
     * {@inheritDoc}
     *
     * @return array<int, mixed>
     */
    #[Override]
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        if ($this->result !== null && !$this->result->isPassthrough()) {
            if (!$this->result->hasResultSet()) {
                return [];
            }

            $rows = $this->result->fetchAll();
            if ($this->resolveFetchMode($mode) === PDO::FETCH_COLUMN) {
                $column = is_int($args[0] ?? null) ? $args[0] : 0;

                return array_map(
                    static fn (array $row): mixed => array_values($row)[$column] ?? false,
                    $rows,
                );
            }

            return array_map(fn (array $row): mixed => $this->formatBufferedRow($row, $mode), $rows);
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
    #[Override]
    public function fetchColumn(int $column = 0): mixed
    {
        if ($this->result !== null && !$this->result->isPassthrough()) {
            if (!$this->result->hasResultSet()) {
                return false;
            }

            $row = $this->result->fetch();

            return $row === false ? false : (array_values($row)[$column] ?? false);
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
    #[Override]
    public function fetchObject(?string $class = 'stdClass', array $constructorArgs = []): object|false
    {
        /** @var class-string<T> $resolvedClass */
        $resolvedClass = $class ?? 'stdClass';

        if ($this->result !== null && !$this->result->isPassthrough()) {
            if (!$this->result->hasResultSet()) {
                return false;
            }

            $row = $this->result->fetch();
            if ($row === false) {
                return false;
            }
            $object = new $resolvedClass(...$constructorArgs);
            if ($object instanceof stdClass) {
                foreach ($row as $property => $value) {
                    $object->{$property} = $value;
                }

                return $object;
            }
            $reflection = new ReflectionObject($object);
            foreach ($row as $property => $value) {
                if ($reflection->hasProperty($property)) {
                    $reflection->getProperty($property)->setValue($object, $value);
                }
            }

            return $object;
        }

        /** @see NativePdoStatement */
        return $this->statement->fetchObject($resolvedClass, $constructorArgs);
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
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
    #[Override]
    public function closeCursor(): bool
    {
        return $this->statement->closeCursor();
    }

    /**
     * {@inheritDoc}
     */
    #[ReturnTypeWillChange]
    #[Override]
    public function setFetchMode(int $mode, mixed ...$args): bool
    {
        $this->fetchMode = ['mode' => $mode, 'args' => $args];

        return $this->statement->setFetchMode($mode, ...$args);
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function errorCode(): string
    {
        return $this->statement->errorCode() ?? '';
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
        return $this->statement->errorInfo();
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function getAttribute(int $name): mixed
    {
        return $this->statement->getAttribute($name);
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function setAttribute(int $attribute, mixed $value): bool
    {
        return $this->statement->setAttribute($attribute, $value);
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function columnCount(): int
    {
        return $this->statement->columnCount();
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function getColumnMeta(int $column): array|false
    {
        return $this->statement->getColumnMeta($column);
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function nextRowset(): bool
    {
        return $this->statement->nextRowset();
    }

    /**
     * {@inheritDoc}
     */
    #[ReturnTypeWillChange]
    #[Override]
    public function debugDumpParams(): bool|null
    {
        $this->statement->debugDumpParams();

        return true;
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function getIterator(): Iterator
    {
        if ($this->result !== null && !$this->result->isPassthrough() && $this->result->hasResultSet()) {
            /** @var Iterator<mixed, array<int|string, mixed>> $iterator */
            $iterator = new ArrayIterator($this->fetchAll());

            return $iterator;
        }

        /** @var Iterator<mixed, array<int|string, mixed>> $iterator */
        $iterator = $this->statement->getIterator();

        return $iterator;
    }

    /**
     * @param Row $row
     */
    private function formatBufferedRow(array $row, int $mode): mixed
    {
        return match ($this->resolveFetchMode($mode)) {
            PDO::FETCH_ASSOC, PDO::FETCH_NAMED => $row,
            PDO::FETCH_NUM => array_values($row),
            PDO::FETCH_OBJ => (object) $row,
            PDO::FETCH_COLUMN => array_values($row)[0] ?? false,
            default => $this->both($row),
        };
    }

    private function resolveFetchMode(int $mode): int
    {
        if ($mode !== PDO::FETCH_DEFAULT) {
            return $mode;
        }

        return $this->fetchMode['mode'] ?? $this->defaultFetchMode;
    }

    /**
     * @param Row $row
     * @return array<int|string, mixed>
     */
    private function both(array $row): array
    {
        $both = [];
        $index = 0;
        foreach ($row as $column => $value) {
            $both[$column] = $value;
            $both[$index] = $value;
            $index++;
        }

        return $both;
    }
}
