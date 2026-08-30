<?php

declare(strict_types=1);

namespace ZtdQuery\Adapter\Pdo;

use ArrayIterator;
use Iterator;
use Override;
use PDO;
use PDOStatement as NativePdoStatement;
use ReflectionException;
use ReflectionObject;
use ReturnTypeWillChange;
use stdClass;
use ZtdQuery\Adapter\Pdo\Driver\BufferedRow;
use ZtdQuery\Adapter\Pdo\Driver\PdoBinding;
use ZtdQuery\Adapter\Pdo\Driver\PdoFetchMode;
use ZtdQuery\Adapter\Pdo\Driver\PdoPreparedExecution;
use ZtdQuery\Adapter\Pdo\Driver\PdoStatement;
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
 * @phpstan-import-type RowValue from StatementInterface
 * @phpstan-import-type BindableValue from PdoBinding
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

    /**
     * Shapes a buffered row for the fetch mode it is read in.
     */
    private BufferedRow $bufferedRow;

    /**
     * Values bound by bindValue(), keyed by the placeholder they were bound to.
     *
     * @var array<int|string, PdoBinding>
     */
    private array $boundValues = [];

    /**
     * Variables bound by bindParam(), keyed by the placeholder they were bound to.
     *
     * @var array<int|string, PdoBinding>
     */
    private array $boundParams = [];

    /**
     * The fetch mode a caller set, or null where none was set.
     */
    private ?PdoFetchMode $fetchMode = null;

    /**
     * Binds the instance to what it will work from.
     *
     * @param NativePdoStatement $statement Statement the driver prepared
     * @param Session $session Session that says what ZTD does with it
     * @param RewritePlan|null $plan What ZTD will carry out instead of it, or null where ZTD is not shadowing
     * @param PdoPreparedExecution|null $preparedExecution What prepares the statement again for each set of parameters, or null where it is prepared once
     * @param int $defaultFetchMode The connection's own fetch mode, used where the caller names none
     * @param BufferedRow $bufferedRow Shapes a buffered row for the mode it is read in
     */
    public function __construct(
        NativePdoStatement $statement,
        Session $session,
        ?RewritePlan $plan,
        ?PdoPreparedExecution $preparedExecution = null,
        int $defaultFetchMode = PDO::FETCH_BOTH,
        BufferedRow $bufferedRow = new BufferedRow(),
    ) {
        $this->statement = $statement;
        $this->session = $session;
        $this->plan = $plan;
        $this->preparedExecution = $preparedExecution;
        $this->defaultFetchMode = $defaultFetchMode;
        $this->bufferedRow = $bufferedRow;
    }

    /**
     * {@inheritDoc}
     *
     * The binding is remembered as well as made, because ZTD prepares the
     * statement again on each execute() and a statement prepared again has
     * nothing bound to it.
     *
     * @return bool Whether the value was bound
     *
     * @throws ZtdPdoException When PDO cannot bind a value of that type
     */
    #[Override]
    public function bindValue(int|string $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        $this->boundValues[$param] = new PdoBinding(PdoBinding::bindable($value, $param), $type);

        return $this->statement->bindValue($param, $value, $type);
    }

    /**
     * {@inheritDoc}
     *
     * The variable is remembered by reference, so that what the caller changes
     * between executions is what the next execution sends.
     *
     * @return bool Whether the variable was bound
     *
     * @throws ZtdPdoException When PDO cannot bind a value of that type
     */
    #[Override]
    public function bindParam(
        int|string $param,
        mixed &$var,
        int $type = PDO::PARAM_STR,
        int $maxLength = 0,
        mixed $driverOptions = null
    ): bool {
        $binding = new PdoBinding(
            PdoBinding::bindable($var, $param),
            $type,
            $maxLength,
            PdoBinding::bindable($driverOptions, $param),
        );
        $binding->value = &$var;
        $this->boundParams[$param] = $binding;

        return $this->statement->bindParam($param, $var, $type, $maxLength, $driverOptions);
    }

    /**
     * {@inheritDoc}
     *
     * A column is bound on the statement the driver prepared, which is the one
     * that fills the variable when a row is read from it.
     *
     * @return bool Whether the column was bound
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
     * @param array<int|string, mixed>|null $params Parameters to run it with, or null for those already bound
     *
     * @return bool Whether the statement ran
     *
     * @throws ZtdPdoException When ZTD cannot carry the statement out
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
                $this->statement->setFetchMode($this->fetchMode->mode, ...$this->fetchMode->arguments);
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
     * Runs the statement and lets ZTD read what came back off it.
     *
     * A simulated write runs as a SELECT that answers the rows it would have
     * touched; reading those rows is what applies the write to the shadow.
     *
     * @param RewritePlan $plan What ZTD is carrying out instead of the statement
     * @param array<int|string, mixed>|null $params Parameters to run it with, or null for those already bound
     *
     * @return bool Whether the statement ran and ZTD could read its result
     *
     * @throws ZtdPdoException When ZTD cannot read what the statement answered
     */
    public function executeAndPostProcess(RewritePlan $plan, ?array $params): bool
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

    /**
     * Runs the prepared statement, binding the parameters it was given.
     *
     * @param array<int|string, mixed>|null $params Parameters to run it with, or null for those already bound
     *
     * @return bool Whether the statement ran
     */
    public function executeStatement(?array $params): bool
    {
        if ($this->preparedExecution === null) {
            return $this->statement->execute($params);
        }

        return $this->preparedExecution->parameterBinder()->execute($this->statement, $params);
    }

    /**
     * Binds everything a caller bound to whatever statement is prepared now.
     *
     * ZTD prepares the statement again on each execute(), and the new one has
     * nothing bound to it; this is what puts the bindings back.
     */
    public function rebindParameters(): void
    {
        foreach ($this->boundValues as $parameter => $binding) {
            $this->statement->bindValue($parameter, $binding->value, $binding->type);
        }
        foreach ($this->boundParams as $parameter => $binding) {
            $this->statement->bindParam(
                $parameter,
                $binding->value,
                $binding->type,
                $binding->maxLength,
                $binding->driverOptions,
            );
        }
    }

    /**
     * {@inheritDoc}
     *
     * A row ZTD buffered is shaped here for the mode it is read in; a row the
     * driver holds is read off the driver, which shapes it itself.
     *
     * @return mixed The next row as that mode reads it, or false where there is none
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

            return $this->bufferedRow->inMode($row, $this->resolveFetchMode($mode));
        }

        /** @see NativePdoStatement */
        return $this->statement->fetch($mode, $cursorOrientation, $cursorOffset);
    }

    /**
     * {@inheritDoc}
     *
     * @param mixed ...$args The rest of what the fetch mode reads
     *
     * @return array<int, mixed> Every remaining row, as that mode reads them
     */
    #[Override]
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        if ($this->result !== null && !$this->result->isPassthrough()) {
            if (!$this->result->hasResultSet()) {
                return [];
            }

            $rows = $this->result->fetchAll();
            $resolvedMode = $this->resolveFetchMode($mode);
            if ($resolvedMode === PDO::FETCH_COLUMN) {
                $column = is_int($args[0] ?? null) ? $args[0] : 0;

                return array_map(
                    static fn (array $row): bool|float|int|string => array_values($row)[$column] ?? false,
                    $rows,
                );
            }

            $shaped = [];
            foreach ($rows as $row) {
                $shaped[] = $this->bufferedRow->inMode($row, $resolvedMode);
            }

            return $shaped;
        }

        /** @see NativePdoStatement */
        $forwardArgs = [];
        foreach ($args as $arg) {
            if (is_int($arg) || is_string($arg) || is_callable($arg)) {
                $forwardArgs[] = $arg;
            }
        }
        return array_values($this->statement->fetchAll($mode, ...$forwardArgs));
    }

    /**
     * {@inheritDoc}
     *
     * @return mixed The column's value in the next row, or false where there is none
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
     * A row ZTD buffered never reached the driver, so nothing hydrated an
     * object from it; the object is built here and its properties written from
     * the row, which is what the driver would have done.
     *
     * @template T of object
     * @param class-string<T>|null $class Class to build, or null for stdClass
     * @param array<mixed> $constructorArgs Arguments to build it with
     *
     * @return T|false The object, or false where there is no row
     *
     * @throws ReflectionException When the class will not let a property be written
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
     *
     * @return int Rows the statement answered or affected
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
     *
     * @return bool Whether the cursor was closed
     */
    #[Override]
    public function closeCursor(): bool
    {
        return $this->statement->closeCursor();
    }

    /**
     * {@inheritDoc}
     *
     * The mode is remembered as well as set, because ZTD prepares the
     * statement again on each execute() and a statement prepared again is back
     * on the connection's own mode.
     *
     * @param mixed ...$args The rest of what that mode reads
     *
     * @return bool Whether the mode was set
     */
    #[ReturnTypeWillChange]
    #[Override]
    public function setFetchMode(int $mode, mixed ...$args): bool
    {
        $this->fetchMode = new PdoFetchMode($mode, array_values($args));

        return $this->statement->setFetchMode($mode, ...$args);
    }

    /**
     * {@inheritDoc}
     *
     * @return string The driver's code for what went wrong last, or an empty string where nothing did
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
     *
     * @return mixed What the driver has that attribute set to
     */
    #[Override]
    public function getAttribute(int $name): mixed
    {
        return $this->statement->getAttribute($name);
    }

    /**
     * {@inheritDoc}
     *
     * @return bool Whether the attribute was set
     */
    #[Override]
    public function setAttribute(int $attribute, mixed $value): bool
    {
        return $this->statement->setAttribute($attribute, $value);
    }

    /**
     * {@inheritDoc}
     *
     * @return int Columns in the result the statement answered
     */
    #[Override]
    public function columnCount(): int
    {
        return $this->statement->columnCount();
    }

    /**
     * {@inheritDoc}
     *
     * The metadata is the driver's own; a statement ZTD simulated has none,
     * because nothing the driver prepared answered its columns.
     */
    #[Override]
    public function getColumnMeta(int $column): array|false
    {
        return $this->statement->getColumnMeta($column);
    }

    /**
     * {@inheritDoc}
     *
     * @return bool Whether there was another result to move to
     */
    #[Override]
    public function nextRowset(): bool
    {
        return $this->statement->nextRowset();
    }

    /**
     * {@inheritDoc}
     *
     * @return bool|null Always true, because the dump is written rather than answered
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
     *
     * Rows ZTD buffered are walked from what it buffered; anything else is
     * walked off the driver's own cursor.
     *
     * @return Iterator<mixed, mixed> Every remaining row
     */
    #[Override]
    public function getIterator(): Iterator
    {
        if ($this->result !== null && !$this->result->isPassthrough() && $this->result->hasResultSet()) {
            return new ArrayIterator($this->fetchAll());
        }

        return $this->statement->getIterator();
    }

    /**
     * Answers the fetch mode a read will actually be made in.
     *
     * A caller who names no mode is answered in the one they last set on the
     * statement, or failing that in the connection's own.
     *
     * @param int $mode Mode the read was asked for in, which may be PDO::FETCH_DEFAULT
     *
     * @return int The mode the read is made in
     */
    public function resolveFetchMode(int $mode): int
    {
        if ($mode !== PDO::FETCH_DEFAULT) {
            return $mode;
        }

        return $this->fetchMode === null ? $this->defaultFetchMode : $this->fetchMode->mode;
    }
}
