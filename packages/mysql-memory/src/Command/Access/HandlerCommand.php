<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Access;

use MySqlMemory\Command\Command;
use MySqlMemory\Command\Output;
use MySqlMemory\Dictionary\Key;
use MySqlMemory\Dictionary\KeyKind;
use MySqlMemory\Dictionary\StoredTable;
use MySqlMemory\Error\QueryError;
use MySqlMemory\Error\SchemaError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Plan\Path\Source\SingleRow;
use MySqlMemory\Plan\Path\Transform\Limit;
use MySqlMemory\Plan\Planner;
use MySqlMemory\Result\ColumnFlag;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\Reply;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Problem\Errors;
use MySqlMemory\Session\Session;
use MySqlMemory\Storage\ClusterOrder;
use Override;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerClose;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerIndexRead;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerIndexSeek;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerOpen;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerScan;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\KeyComparison;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Statement\Operation;

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
        $handler = $this->handler($session, $statement->handler->name->value);
        $table = $session->instance->dictionary->table($handler->schema, $handler->table);
        assert($table !== null);
        $key = $statement instanceof HandlerScan ? null : $this->key($table, $statement->index->value, $handler);
        if ($statement instanceof HandlerIndexSeek && $key !== null && count($statement->values) > count($key->columns)) {
            throw SchemaError::TooManyKeyParts->error(count($key->columns));
        }
        $select = $session->analyze($this->select($handler, $statement->where === null ? null : $this->condition($operation->toString(), $statement->limit !== null)));
        $query = $select->statement;
        foreach ($select->facts->diagnostics as $diagnostic) {
            throw (new Errors())->error($diagnostic, $session, 'field list', $query);
        }
        assert($query instanceof Select && $query->from !== null);
        $planner = new Planner($query, $select->facts, $session->settings(), $connection, $session->instance->dictionary);
        $plan = $planner->query($query, null);
        $condition = $this->filter($planner, $query, $table);
        $rows = (new ClusterOrder())->rows($table);
        $cursor = new HandlerCursor();
        $numbers = $cursor->ordered($rows, $key, $table);
        $values = $statement instanceof HandlerIndexSeek ? $this->values($statement, $operation, $session, $context, $connection) : [];
        $limit = $planner->blocks->limit(new SingleRow(), $statement->limit, null);
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
     * @param string|null $condition The WHERE condition of the read, or null when it has none
     */
    public function select(Handler $handler, ?string $condition): string
    {
        $from = '`' . str_replace('`', '``', $handler->schema) . '`.`' . str_replace('`', '``', $handler->table) . '` AS `' . str_replace('`', '``', $handler->name) . '`';

        return 'SELECT * FROM ' . $from . ($condition === null ? '' : ' WHERE ' . $condition);
    }

    /**
     * Answers the WHERE condition of the SELECT of a read, compiled against the columns of the table, or null when it has none.
     */
    public function filter(Planner $planner, Select $query, StoredTable $table): ?Evaluable
    {
        if ($query->where === null) {
            return null;
        }
        assert($query->from !== null);
        $definition = $table->definition;
        $scope = new Scope();
        $scope->place($query->from, array_map(static fn ($column) => $column->domain, $definition->columns), array_map(static fn ($column): string => $column->name, $definition->columns), $definition);

        return $planner->compiler->compile($query->where, $scope);
    }

    /**
     * Answers the values a key seek compares the index with.
     *
     * @return list<int|float|string|null>
     */
    public function values(HandlerIndexSeek $statement, Operation $operation, Session $session, Context $context, Connection $connection): array
    {
        $planner = new Planner($statement, $operation->facts, $session->settings(), $connection, $session->instance->dictionary);
        $values = [];
        foreach ($statement->values as $value) {
            $values[] = $planner->compiler->compile($value, new Scope())->evaluate(new Frame($context));
        }

        return $values;
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
     * Answers the WHERE condition of the SQL of a read: the text between WHERE and the LIMIT of the read.
     */
    public function condition(string $sql, bool $limited): string
    {
        $condition = substr($sql, (int) strpos($sql, ' WHERE ') + 7);
        $end = strrpos($condition, ' LIMIT ');

        return $limited && $end !== false ? substr($condition, 0, $end) : $condition;
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

        return new ResultSet($output->columns($plan, $charset), $sent, $context->diagnostics->count());
    }
}
