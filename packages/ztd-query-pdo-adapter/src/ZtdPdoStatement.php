<?php

declare(strict_types=1);

namespace ZtdQuery\Adapter\Pdo;

use ArrayIterator;
use Iterator;
use Override;
use PDO;
use PDOStatement as NativePdoStatement;
use ReflectionException;
use ReturnTypeWillChange;
use stdClass;
use ZtdQuery\Adapter\Pdo\Session\BufferedRow;
use ZtdQuery\Adapter\Pdo\Session\PreparedQuery;
use ZtdQuery\Adapter\Pdo\Session\StatementExecution;
use ZtdQuery\Rewrite\RewritePlan;
use ZtdQuery\Session;

/**
 * PDOStatement wrapper that applies ZTD rewrite/simulation on execute().
 *
 * Uses delegation pattern: extends PDOStatement for type compatibility,
 * but delegates all operations to an inner PDOStatement instance.
 *
 * @visibility public
 * @example Fetch a row through the PDO statement interface
 *     $native = new \PDO('sqlite::memory:');
 *     $native->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
 *     $pdo = \ZtdQuery\Adapter\Pdo\ZtdPdo::fromPdo($native);
 *     $pdo->exec("INSERT INTO users VALUES (1, 'Alice')");
 *     $statement = $pdo->query('SELECT id, name FROM users');
 *     $statement->fetch(\PDO::FETCH_ASSOC) // => ['id' => 1, 'name' => 'Alice']
 *     $statement->fetch() // => false
 */
final class ZtdPdoStatement extends NativePdoStatement
{
    private StatementExecution $execution;

    private BufferedRow $bufferedRow;

    private ?int $fetchMode = null;

    /**
     * Wrap a driver statement with the session state that governs its execution.
     *
     * @visibility ZtdQuery\Adapter\Pdo
     */
    public function __construct(
        NativePdoStatement $statement,
        Session $session,
        ?RewritePlan $plan,
        ?PreparedQuery $preparedExecution = null,
        private readonly int $defaultFetchMode = PDO::FETCH_BOTH,
    ) {
        $this->execution = new StatementExecution($statement, $session, $plan, $preparedExecution);
        $this->bufferedRow = new BufferedRow();
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
     * @visibility public
     * @example Retain typed values when the query is prepared again
     *     $pdo = \ZtdQuery\Adapter\Pdo\ZtdPdo::fromPdo(new \PDO('sqlite::memory:'));
     *     $statement = $pdo->prepare('SELECT :id AS id');
     *     $statement->bindValue(':id', 7, \PDO::PARAM_INT) // => true
     *     $statement->execute() // => true
     *     $statement->fetchColumn() // => 7
     */
    #[Override]
    public function bindValue(int|string $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        $this->execution->bindings()->parameter($param, static fn (NativePdoStatement $statement): bool => $statement->bindValue($param, $value, $type));
        return $this->execution->native()->bindValue($param, $value, $type);
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
     * @visibility public
     * @example Read the current variable at each execution
     *     $pdo = \ZtdQuery\Adapter\Pdo\ZtdPdo::fromPdo(new \PDO('sqlite::memory:'));
     *     $statement = $pdo->prepare('SELECT ? AS id');
     *     $params = (object) ['id' => 7];
     *     $statement->bindParam(1, $params->id, \PDO::PARAM_INT);
     *     $statement->execute();
     *     $statement->fetchColumn() // => 7
     *     $params->id = 9;
     *     $statement->execute();
     *     $statement->fetchColumn() // => 9
     */
    #[Override]
    public function bindParam(int|string $param, mixed &$var, int $type = PDO::PARAM_STR, int $maxLength = 0, mixed $driverOptions = null): bool
    {
        return $this->execution->bindParameter($param, static function (NativePdoStatement $statement) use ($param, &$var, $type, $maxLength, $driverOptions): bool {
            return $statement->bindParam($param, $var, $type, $maxLength, $driverOptions);
        });
    }

    /**
     * {@inheritDoc}
     *
     * A column is bound on the statement the driver prepared, which is the one
     * that fills the variable when a row is read from it.
     *
     * @return bool Whether the column was bound
     * @visibility public
     * @example Bind a column on an executed statement
     *     $pdo = \ZtdQuery\Adapter\Pdo\ZtdPdo::fromPdo(new \PDO('sqlite::memory:'));
     *     $statement = $pdo->query('SELECT 7 AS id');
     *     $row = (object) ['id' => null];
     *     $statement->bindColumn('id', $row->id, \PDO::PARAM_INT) // => true
     *     $statement->fetch(\PDO::FETCH_BOUND);
     *     $row->id // => 7
     */
    #[Override]
    public function bindColumn(
        int|string $column,
        mixed &$var,
        int $type = PDO::PARAM_STR,
        int $maxLength = 0,
        mixed $driverOptions = null
    ): bool {
        return $this->execution->native()->bindColumn($column, $var, $type, $maxLength, $driverOptions);
    }

    /**
     * Execute the statement, applying ZTD simulation as needed.
     *
     * @param array<int|string, mixed>|null $params Parameters to run it with, or null for those already bound
     *
     * @return bool Whether the statement ran
     *
     * @throws ZtdPdoException When ZTD cannot carry the statement out
     * @visibility public
     * @example Execute parameters against current shadow state
     *     $pdo = \ZtdQuery\Adapter\Pdo\ZtdPdo::fromPdo(new \PDO('sqlite::memory:'));
     *     $statement = $pdo->prepare('SELECT ? AS name');
     *     $statement->execute(['Ada']) // => true
     *     $statement->fetchColumn() // => 'Ada'
     */
    #[Override]
    public function execute(?array $params = null): bool
    {
        return $this->execution->execute($params);
    }







    /**
     * {@inheritDoc}
     *
     * A row ZTD buffered is shaped here for the mode it is read in; a row the
     * driver holds is read off the driver, which shapes it itself.
     *
     * @return mixed The next row as that mode reads it, or false where there is none
     * @visibility public
     * @example Fetch an associative row
     *     $pdo = \ZtdQuery\Adapter\Pdo\ZtdPdo::fromPdo(new \PDO('sqlite::memory:'));
     *     $statement = $pdo->query('SELECT 7 AS id');
     *     $statement->fetch(\PDO::FETCH_ASSOC) // => ['id' => 7]
     *     $statement->fetch() // => false
     */
    #[Override]
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        $result = $this->execution->result();
        if ($result !== null && !$result->isPassthrough()) {
            $resolvedMode = $this->bufferedRow->resolveMode($mode, $this->defaultFetchMode, $this->fetchMode);
            return $this->bufferedRow->fetch($result->hasResultSet() ? $result->fetch() : false, $resolvedMode);
        }
        return $this->execution->native()->fetch($mode, $cursorOrientation, $cursorOffset);
    }

    /**
     * {@inheritDoc}
     *
     * @param mixed ...$args The rest of what the fetch mode reads
     *
     * @return array<array-key, mixed> Every remaining row, as that mode reads them
     * @visibility public
     * @example Fetch one column from every row
     *     $pdo = \ZtdQuery\Adapter\Pdo\ZtdPdo::fromPdo(new \PDO('sqlite::memory:'));
     *     $statement = $pdo->query('SELECT 7 AS id UNION ALL SELECT 9 AS id');
     *     $statement->fetchAll(\PDO::FETCH_COLUMN) // => [7, 9]
     */
    #[Override]
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        $result = $this->execution->result();
        if ($result !== null && !$result->isPassthrough()) {
            $resolvedMode = $this->bufferedRow->resolveMode($mode, $this->defaultFetchMode, $this->fetchMode);
            return $this->bufferedRow->all($result->hasResultSet() ? $result->fetchAll() : [], $resolvedMode, $args);
        }
        return $this->execution->native()->fetchAll($mode, ...$args);
    }

    /**
     * {@inheritDoc}
     *
     * @return mixed The column's value in the next row, or false where there is none
     * @visibility public
     * @example Read a single value
     *     $pdo = \ZtdQuery\Adapter\Pdo\ZtdPdo::fromPdo(new \PDO('sqlite::memory:'));
     *     $statement = $pdo->query('SELECT 7 AS id');
     *     $statement->fetchColumn() // => 7
     *     $statement->fetchColumn() // => false
     */
    #[Override]
    public function fetchColumn(int $column = 0): mixed
    {
        $result = $this->execution->result();
        if ($result !== null && !$result->isPassthrough()) {
            return $this->bufferedRow->column($result->hasResultSet() ? $result->fetch() : false, $column);
        }
        return $this->execution->native()->fetchColumn($column);
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
     * @return ($class is null ? stdClass : T)|false The object, or false where there is no row
     *
     * @throws ReflectionException When the class will not let a property be written
     * @visibility public
     * @example Hydrate an anonymous row object
     *     $pdo = \ZtdQuery\Adapter\Pdo\ZtdPdo::fromPdo(new \PDO('sqlite::memory:'));
     *     $statement = $pdo->query('SELECT 7 AS id');
     *     $row = $statement->fetchObject();
     *     $row->id // => 7
     *     $statement->fetchObject() // => false
     */
    #[Override]
    public function fetchObject(?string $class = 'stdClass', array $constructorArgs = []): object|false
    {
        $result = $this->execution->result();
        if ($result !== null && !$result->isPassthrough()) {
            return $this->bufferedRow->object($result->hasResultSet() ? $result->fetch() : false, $class, $constructorArgs);
        }
        return $this->execution->native()->fetchObject($class ?? 'stdClass', $constructorArgs);
    }

    /**
     * {@inheritDoc}
     *
     * @return int Rows the statement answered or affected
     * @visibility public
     * @example Count affected shadow rows
     *     $native = new \PDO('sqlite::memory:');
     *     $native->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
     *     $pdo = \ZtdQuery\Adapter\Pdo\ZtdPdo::fromPdo($native);
     *     $statement = $pdo->prepare('INSERT INTO users VALUES (?, ?)');
     *     $statement->execute([1, 'Ada']);
     *     $statement->rowCount() // => 1
     */
    #[Override]
    public function rowCount(): int
    {
        if ($this->execution->result() !== null && !$this->execution->result()->isPassthrough()) {
            return $this->execution->result()->rowCount();
        }

        return $this->execution->native()->rowCount();
    }

    /**
     * {@inheritDoc}
     *
     * @return bool Whether the cursor was closed
     * @visibility public
     * @example Release a native cursor
     *     $pdo = \ZtdQuery\Adapter\Pdo\ZtdPdo::fromPdo(new \PDO('sqlite::memory:'));
     *     $statement = $pdo->query('SELECT 7 AS id');
     *     $statement->closeCursor() // => true
     */
    #[Override]
    public function closeCursor(): bool
    {
        return $this->execution->native()->closeCursor();
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
     * @visibility public
     * @example Choose the shape of subsequent rows
     *     $pdo = \ZtdQuery\Adapter\Pdo\ZtdPdo::fromPdo(new \PDO('sqlite::memory:'));
     *     $statement = $pdo->query('SELECT 7 AS id');
     *     $statement->setFetchMode(\PDO::FETCH_NUM) // => true
     *     $statement->fetch() // => [7]
     */
    #[ReturnTypeWillChange]
    #[Override]
    public function setFetchMode(int $mode, mixed ...$args): bool
    {
        $this->fetchMode = $mode;
        $this->execution->bindings()->fetch(static fn (NativePdoStatement $statement): bool => $statement->setFetchMode($mode, ...$args));
        return $this->execution->native()->setFetchMode($mode, ...$args);
    }

    /**
     * {@inheritDoc}
     *
     * @return string The driver's code for what went wrong last, or an empty string where nothing did
     * @visibility public
     * @example Read statement SQLSTATE
     *     $pdo = \ZtdQuery\Adapter\Pdo\ZtdPdo::fromPdo(new \PDO('sqlite::memory:'));
     *     $statement = $pdo->query('SELECT 7 AS id');
     *     $statement->errorCode() // => '00000'
     */
    #[Override]
    public function errorCode(): string
    {
        return $this->execution->native()->errorCode() ?? '';
    }

    /**
     * {@inheritDoc}
     *
     * @return array{0: string|null, 1: int|null, 2: string|null}
     * @visibility public
     * @example Read statement error details
     *     $pdo = \ZtdQuery\Adapter\Pdo\ZtdPdo::fromPdo(new \PDO('sqlite::memory:'));
     *     $statement = $pdo->query('SELECT 7 AS id');
     *     $statement->errorInfo()[0] // => '00000'
     */
    #[Override]
    public function errorInfo(): array
    {
        /** @var array{0: string|null, 1: int|null, 2: string|null} */
        return $this->execution->native()->errorInfo();
    }

    /**
     * {@inheritDoc}
     *
     * @return mixed What the driver has that attribute set to
     * @visibility public
     * @example Handle an unsupported SQLite statement attribute
     *     $pdo = \ZtdQuery\Adapter\Pdo\ZtdPdo::fromPdo(new \PDO('sqlite::memory:'));
     *     $statement = $pdo->query('SELECT 7 AS id');
     *     $statement->getAttribute(\PDO::ATTR_CURSOR) // throws \PDOException
     */
    #[Override]
    public function getAttribute(int $name): mixed
    {
        return $this->execution->native()->getAttribute($name);
    }

    /**
     * {@inheritDoc}
     *
     * @return bool Whether the attribute was set
     * @visibility public
     * @example Handle a driver that does not support statement attributes
     *     $pdo = \ZtdQuery\Adapter\Pdo\ZtdPdo::fromPdo(new \PDO('sqlite::memory:'));
     *     $statement = $pdo->query('SELECT 7 AS id');
     *     try { $supported = $statement->setAttribute(\PDO::ATTR_CURSOR, \PDO::CURSOR_FWDONLY); } catch (\PDOException) { $supported = false; }
     *     $supported // => false
     */
    #[Override]
    public function setAttribute(int $attribute, mixed $value): bool
    {
        return $this->execution->native()->setAttribute($attribute, $value);
    }

    /**
     * {@inheritDoc}
     *
     * @return int Columns in the result the statement answered
     * @visibility public
     * @example Inspect result width
     *     $pdo = \ZtdQuery\Adapter\Pdo\ZtdPdo::fromPdo(new \PDO('sqlite::memory:'));
     *     $statement = $pdo->query('SELECT 7 AS id');
     *     $statement->columnCount() // => 1
     */
    #[Override]
    public function columnCount(): int
    {
        return $this->execution->native()->columnCount();
    }

    /**
     * {@inheritDoc}
     *
     * The metadata is the driver's own; a statement ZTD simulated has none,
     * because nothing the driver prepared answered its columns.
     * @visibility public
     * @example Read a projected column label
     *     $pdo = \ZtdQuery\Adapter\Pdo\ZtdPdo::fromPdo(new \PDO('sqlite::memory:'));
     *     $statement = $pdo->query('SELECT 7 AS id');
     *     $statement->getColumnMeta(0)['name'] // => 'id'
     */
    #[Override]
    public function getColumnMeta(int $column): array|false
    {
        return $this->execution->native()->getColumnMeta($column);
    }

    /**
     * {@inheritDoc}
     *
     * @return bool Whether there was another result to move to
     * @visibility public
     * @example Handle SQLite without multiple rowsets
     *     $pdo = \ZtdQuery\Adapter\Pdo\ZtdPdo::fromPdo(new \PDO('sqlite::memory:'));
     *     $statement = $pdo->query('SELECT 7 AS id');
     *     $statement->nextRowset() // throws \PDOException
     */
    #[Override]
    public function nextRowset(): bool
    {
        return $this->execution->native()->nextRowset();
    }

    /**
     * {@inheritDoc}
     *
     * @return bool|null Always true, because the dump is written rather than answered
     * @visibility public
     * @example Print the native statement diagnostics
     *     $pdo = \ZtdQuery\Adapter\Pdo\ZtdPdo::fromPdo(new \PDO('sqlite::memory:'));
     *     $statement = $pdo->query('SELECT 7 AS id');
     *     $statement->debugDumpParams() // => true
     */
    #[ReturnTypeWillChange]
    #[Override]
    public function debugDumpParams(): bool|null
    {
        $this->execution->native()->debugDumpParams();

        return true;
    }

    /**
     * {@inheritDoc}
     *
     * Rows ZTD buffered are walked from what it buffered; anything else is
     * walked off the driver's own cursor.
     *
     * @return Iterator<mixed, mixed> Every remaining row
     * @visibility public
     * @example Iterate remaining rows
     *     $pdo = \ZtdQuery\Adapter\Pdo\ZtdPdo::fromPdo(new \PDO('sqlite::memory:'));
     *     $statement = $pdo->query('SELECT 7 AS id');
     *     $statement->setFetchMode(\PDO::FETCH_ASSOC);
     *     iterator_to_array($statement->getIterator()) // => [['id' => 7]]
     */
    #[Override]
    public function getIterator(): Iterator
    {
        if ($this->execution->result() !== null && !$this->execution->result()->isPassthrough() && $this->execution->result()->hasResultSet()) {
            return new ArrayIterator($this->fetchAll());
        }

        return $this->execution->native()->getIterator();
    }


}
