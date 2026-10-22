<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Access;

use MySqlMemory\Command\Command;
use MySqlMemory\Command\Output;
use MySqlMemory\Dictionary\Key;
use MySqlMemory\Dictionary\KeyKind;
use MySqlMemory\Dictionary\StoredTable;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Plan\Path\Source\SingleRow;
use MySqlMemory\Plan\Path\Transform\Limit;
use MySqlMemory\Plan\Planner;
use MySqlMemory\Result\ColumnFlag;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\Reply;
use MySqlMemory\Result\ResultColumn;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Problem\Errors;
use MySqlMemory\Session\Session;
use MySqlMemory\Storage\ClusterOrder;
use MySqlMemory\Storage\TimestampZones;
use Override;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerClose;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerIndexRead;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerIndexSeek;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerOpen;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerScan;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\KeyComparison;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\Star;
use SqlSemantics\Platform\MySql\Statement\QueryRoot;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Scalar;

/**
 * Executes HANDLER: opens a table for reading row by row, reads it in its natural order or the order of an index, and closes it.
 *
 * OPEN names the handler by the alias or the table name, which no other open handler may have.
 * A read follows the natural order of the table or the order of an index from where the last
 * read of that order left the cursor; a read of another order starts it afresh. A key seek with
 * = reads the rows equal to the values, >= and > read forward from the first row past the
 * values, and <= and < read backward from the last row before them. The rows a read skips for
 * its WHERE condition move the cursor too, and a read that runs out of rows leaves the cursor
 * past the end. A name the WHERE condition does not find is reported in the field list. A
 * handler whose table is dropped is closed. Every rule was verified on a live 8.4 server.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/handler.html.
 *
 * @visibility MySqlMemory
 */
final class HandlerCommand implements Command
{
    /**
     * Answers true.
     */
    #[Override]
    public function clearsDiagnostics(): bool
    {
        return true;
    }

    /**
     * Opens, reads or closes a handler.
     */
    #[Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        if ($statement instanceof HandlerOpen) {
            return $this->open($statement, $session);
        }
        if ($statement instanceof HandlerClose) {
            return $this->close($statement, $session);
        }
        assert($statement instanceof HandlerScan || $statement instanceof HandlerIndexRead || $statement instanceof HandlerIndexSeek);

        return $this->read($statement, $operation, $session, $context, $connection);
    }

    /**
     * Closes a handler.
     *
     * @throws \MySqlMemory\Error\SqlError When no handler of the name is open
     */
    public function close(HandlerClose $statement, Session $session): Completion
    {
        $this->handler($session, $statement->handler->value);
        unset($session->handlers[mb_strtolower($statement->handler->value)]);

        return new Completion();
    }

    /**
     * Reads rows through a handler, from where its cursor stands in the order of the read, and moves the cursor.
     *
     * @throws \MySqlMemory\Error\SqlError When the handler is not open, the index does not exist, a seek has more values than the index has parts, or the condition or LIMIT fails
     */
    public function read(HandlerScan|HandlerIndexRead|HandlerIndexSeek $statement, Operation $operation, Session $session, Context $context, Connection $connection): ResultSet
    {
        (new HandlerPreparation())->check($operation, $session);
        $handler = $this->handler($session, $statement->handler->name->value);
        $table = $session->instance->dictionary->table($handler->schema, $handler->table);
        (new \MySqlMemory\Session\Access\OpenedTables())->open($handler->schema, $handler->table, $session);
        assert($table !== null);
        $query = $this->select($handler, null);
        $select = new Operation($operation->context, new QueryRoot($query));
        foreach ($select->facts->diagnostics as $diagnostic) {
            throw (new Errors())->error($diagnostic, $session, 'field list', $statement);
        }
        assert($query->from !== null);
        $planner = new Planner($query, $select->facts, $session->settings(), $connection, $session->instance->dictionary);
        $plan = $planner->query($query, null);
        $reading = new Planner($statement, $operation->facts, $session->settings(), $connection, $session->instance->dictionary);
        $condition = $this->filter($reading, $statement, $table);
        $key = $statement instanceof HandlerScan ? null : $this->key($table, $statement->index->value, $handler);
        if ($statement instanceof HandlerIndexSeek && $key !== null && count($statement->values) > count($key->columns)) {
            throw SchemaError::TooManyKeyParts->error(count($key->columns));
        }
        $rows = (new TimestampZones())->rows($table, (new ClusterOrder())->rows($table), $context);
        $cursor = new HandlerCursor();
        $numbers = $cursor->ordered($rows, $key, $table);
        $values = $statement instanceof HandlerIndexSeek ? $this->values($statement, $operation, $session, $context, $connection) : [];
        $limit = $reading->blocks->limit(new SingleRow(), $statement->limit, null);
        $count = $limit instanceof Limit ? $limit->count : 1;
        $order = $key === null ? null : strtolower($key->name);
        [$start, $step] = $cursor->start($statement, $numbers, $rows, $key, $table, $values, $handler->placed && $handler->order === $order ? $handler->position : null);
        $equal = $statement instanceof HandlerIndexSeek && $statement->comparison === KeyComparison::Equal ? $key : null;
        [$found, $position] = $cursor->walk($rows, $numbers, $start, $step, $count, $limit instanceof Limit ? $limit->offset : 0, $equal, $table, $values, $condition, $context);
        if ($count !== 0) {
            $this->place($handler, $order, $position ?? ($step > 0 ? count($numbers) : -1));
        }

        return $this->result($plan, $found, $table, $session, $context);
    }

    /**
     * Answers the SELECT a read evaluates its condition and its columns with: every column of the table under the name of the handler.
     *
     * The original predicate is retained as a semantic value; no SQL is rendered or parsed.
     */
    public function select(Handler $handler, ?Scalar $condition): Select
    {
        $from = new TableReference(new QualifiedName(new Name($handler->table), new Name($handler->schema)), new Name($handler->name));

        return new Select([], [new Star()], $from, $condition);
    }

    /**
     * Compiles the original handler predicate against its resolved table columns.
     */
    public function filter(Planner $planner, HandlerScan|HandlerIndexRead|HandlerIndexSeek $statement, StoredTable $table): ?Evaluable
    {
        if ($statement->where === null) {
            return null;
        }
        return $planner->compiler->compile($statement->where, (new HandlerKeys())->scope($statement->handler, $table));
    }

    /**
     * Answers the values a key seek compares the index with.
     *
     * @return list<int|float|string|null>
     */
    public function values(HandlerIndexSeek $statement, Operation $operation, Session $session, Context $context, Connection $connection): array
    {
        $planner = new Planner($statement, $operation->facts, $session->settings(), $connection, $session->instance->dictionary);
        $handler = $this->handler($session, $statement->handler->name->value);
        $table = $session->instance->dictionary->table($handler->schema, $handler->table);
        assert($table !== null);
        $keys = new HandlerKeys();
        $scope = $keys->scope($statement->handler, $table);
        $values = [];
        foreach ($statement->values as $position => $value) {
            if ($value instanceof \SqlSemantics\Platform\MySql\Statement\Dml\DefaultRequest) {
                $values[] = $this->default($statement, $position, $session, $context);
                continue;
            }
            $compiled = $planner->compiler->compile($value, $scope);
            $keys->check($value, $session->settings()->legacy());
            $values[] = $compiled->evaluate(new Frame($context));
        }

        return $values;
    }

    /**
     * Reads the default of the column at an index-key position.
     *
     * @throws \MySqlMemory\Error\SqlError When the index column has no default
     */
    public function default(HandlerIndexSeek $statement, int $position, Session $session, Context $context): int|float|string|null
    {
        $handler = $this->handler($session, $statement->handler->name->value);
        $table = $session->instance->dictionary->table($handler->schema, $handler->table);
        assert($table !== null);
        $key = $this->key($table, $statement->index->value, $handler);
        $column = $table->definition->columns[$key->columns[$position]];
        if (!$column->default->declared) {
            $context->warning(\MySqlMemory\Error\Family\DataError::NoDefaultForField, $column->name);
            if ($session->settings()->release() === \SqlSemantics\Contract\GrammarRelease::MySql5651) {
                return (new \MySqlMemory\Storage\Writer($table, $context))->implicit($column);
            }
            throw \MySqlMemory\Error\Family\StatementError::WrongArguments->error('HANDLER ... READ');
        }
        if ($column->default->expression !== null) {
            return $column->default->expression->evaluate(new Frame($context));
        }

        return $column->default->value;
    }

    /**
     * Places the cursor of a handler in an order.
     *
     * @param string|null $order Null for the natural order, else the lowercase index name
     */
    public function place(Handler $handler, ?string $order, int $position): void
    {
        $handler->order = $order;
        $handler->placed = true;
        $handler->position = $position;
    }

    /**
     * Opens a handler on a table.
     *
     * @throws \MySqlMemory\Error\SqlError When the table does not exist or the handler name is taken
     */
    public function open(HandlerOpen $statement, Session $session): Completion
    {
        $schema = $statement->table->schema->value ?? $session->variables->database;
        if ($schema === '') {
            throw QueryError::NoDatabase->error();
        }
        if ($session->instance->dictionary->schema($schema) === null) {
            throw QueryError::BadDatabase->error($schema);
        }
        if (isset($session->instance->dictionary->schema($schema)?->views[$statement->table->name->value])) {
            throw SchemaError::WrongObject->error($schema, $statement->table->name->value, 'BASE TABLE');
        }
        if ($session->instance->dictionary->table($schema, $statement->table->name->value) === null) {
            throw QueryError::NoSuchTable->error($schema, $statement->table->name->value);
        }
        $name = $statement->alias->value ?? $statement->table->name->value;
        if (isset($session->handlers[mb_strtolower($name)])) {
            throw QueryError::NonUniqueTable->error($name);
        }
        $session->handlers[mb_strtolower($name)] = new Handler($schema, $statement->table->name->value, $name);
        (new \MySqlMemory\Session\Access\OpenedTables())->open($schema, $statement->table->name->value, $session);

        return new Completion();
    }

    /**
     * Answers the open handler of a name, closing it when its table was dropped.
     *
     * @throws \MySqlMemory\Error\SqlError When no handler of the name is open
     */
    public function handler(Session $session, string $name): Handler
    {
        $handler = $session->handlers[mb_strtolower($name)] ?? null;
        if ($handler !== null && $session->instance->dictionary->table($handler->schema, $handler->table) === null) {
            unset($session->handlers[mb_strtolower($name)]);
            $handler = null;
        }
        if ($handler === null) {
            throw QueryError::UnknownTable->error($name, 'HANDLER');
        }

        return $handler;
    }

    /**
     * Answers the index of the table a read names; PRIMARY names the primary key.
     *
     * @throws \MySqlMemory\Error\SqlError When the table has no such index
     */
    public function key(StoredTable $table, string $name, Handler $handler): Key
    {
        foreach ($table->definition->keys as $key) {
            if (strcasecmp($key->kind === KeyKind::Primary ? 'PRIMARY' : $key->name, $name) === 0) {
                return $key;
            }
        }

        throw SchemaError::KeyMissing->error($name, $handler->name);
    }

    /**
     * Answers the rows read as SELECT * answers them: the visible columns, each value in its text.
     *
     * @param list<list<int|float|string|null>> $rows
     */
    public function result(\MySqlMemory\Plan\QueryPlan $plan, array $rows, StoredTable $table, Session $session, Context $context): ResultSet
    {
        $output = new Output();
        $results = $session->variables->read('character_set_results');
        $charset = is_string($results) ? Charset::named($results) : null;
        $visible = array_keys(array_filter($table->definition->columns, static fn ($column): bool => !$column->invisible));
        $sent = [];
        foreach ($rows as $row) {
            $values = [];
            foreach ($visible as $index => $position) {
                $domain = $plan->domains[$index];
                $values[] = $output->sent($output->text($row[$position], $domain, (($plan->origins[$index]->flags ?? 0) & ColumnFlag::ZeroFill->value) !== 0), $domain, $charset);
            }
            $sent[] = $values;
        }

        $columns = $output->columns($plan, $charset, $session->settings()->release(), $session->variables->resultCollation());
        $keys = array_merge(...array_map(static fn (Key $key): array => $key->columns, $table->definition->keys));
        $legacy = $session->settings()->legacy();
        $columns = array_map(static fn (ResultColumn $column, int $position): ResultColumn => new ResultColumn($column->name, $column->type, $column->length, $column->decimals, $column->flags | (in_array($position, $keys, true) ? ColumnFlag::PartKey->value : 0), $column->charset, $column->originalName, $column->table, $legacy ? $column->originalTable : $column->table, $legacy ? $column->schema : '*any*'), $columns, $visible);

        return new ResultSet($columns, $sent, $context->diagnostics->raised());
    }
}
