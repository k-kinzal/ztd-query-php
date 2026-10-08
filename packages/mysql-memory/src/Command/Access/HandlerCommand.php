<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Access;

use MySqlMemory\Command\Command;
use MySqlMemory\Command\Output;
use MySqlMemory\Dictionary\Key;
use MySqlMemory\Dictionary\KeyKind;
use MySqlMemory\Dictionary\StoredTable;
use MySqlMemory\Error\ErrorCode;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Plan\Path\Source\SingleRow;
use MySqlMemory\Plan\Path\Transform\Limit;
use MySqlMemory\Plan\Planner;
use MySqlMemory\Result\ColumnFlag;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\Reply;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Problems;
use MySqlMemory\Session\Session;
use MySqlMemory\Storage\ClusterOrder;
use MySqlMemory\Value\Order;
use Override;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerClose;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerIndexRead;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerIndexSeek;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerOpen;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerScan;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\IndexDirection;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\KeyComparison;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\ScanDirection;
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
            $this->handler($session, $statement->handler->value);
            unset($session->handlers[mb_strtolower($statement->handler->value)]);

            return new Completion();
        }
        assert($statement instanceof HandlerScan || $statement instanceof HandlerIndexRead || $statement instanceof HandlerIndexSeek);
        $handler = $this->handler($session, $statement->handler->name->value);
        $table = $session->instance->dictionary->table($handler->schema, $handler->table);
        assert($table !== null);
        $key = $statement instanceof HandlerScan ? null : $this->key($table, $statement->index->value, $handler);
        if ($statement instanceof HandlerIndexSeek && $key !== null && count($statement->values) > count($key->columns)) {
            throw ErrorCode::TooManyKeyParts->error(count($key->columns));
        }
        $select = $session->analyze('SELECT * FROM `' . str_replace('`', '``', $handler->schema) . '`.`' . str_replace('`', '``', $handler->table) . '` AS `' . str_replace('`', '``', $handler->name) . '`' . ($statement->where === null ? '' : ' WHERE ' . $this->condition($operation->toString(), $statement->limit !== null)));
        $query = $select->statement;
        foreach ($select->facts->diagnostics as $diagnostic) {
            throw (new Problems())->error($diagnostic, $session, 'field list', $query);
        }
        assert($query instanceof Select && $query->from !== null);
        $planner = new Planner($query, $select->facts, $session->settings(), $connection, $session->instance->dictionary);
        $plan = $planner->query($query, null);
        $definition = $table->definition;
        $scope = new Scope();
        $scope->place($query->from, array_map(static fn ($column) => $column->domain, $definition->columns), array_map(static fn ($column): string => $column->name, $definition->columns), $definition);
        $condition = $query->where === null ? null : $planner->compiler->compile($query->where, $scope);
        $rows = (new ClusterOrder())->rows($table);
        $numbers = $this->ordered($rows, $key, $table);
        $values = [];
        if ($statement instanceof HandlerIndexSeek) {
            $own = new Planner($statement, $operation->facts, $session->settings(), $connection, $session->instance->dictionary);
            foreach ($statement->values as $value) {
                $values[] = $own->compiler->compile($value, new Scope())->evaluate(new Frame($context));
            }
        }
        $limit = $planner->blocks->limit(new SingleRow(), $statement->limit, null);
        $count = $limit instanceof Limit ? $limit->count : 1;
        $offset = $limit instanceof Limit ? $limit->offset : 0;
        $order = $key === null ? null : strtolower($key->name);
        $continues = $handler->placed && $handler->order === $order;
        [$start, $step] = $this->start($statement, $numbers, $rows, $key, $table, $values, $continues ? $handler->position : null);
        $found = [];
        $matched = 0;
        $position = null;
        $frame = new Frame($context);
        $equal = $statement instanceof HandlerIndexSeek && $statement->comparison === KeyComparison::Equal ? $key : null;
        for ($index = $start; $count !== 0 && $index >= 0 && $index < count($numbers); $index += $step) {
            $row = $rows[$numbers[$index]];
            if ($equal !== null && $this->compare($row, $equal, $table, $values) !== 0) {
                $position = $index - $step;
                break;
            }
            $frame->row = $row;
            if ($condition !== null && Convert::toBool($condition->evaluate($frame), $condition->domain(), $context) !== true) {
                continue;
            }
            $matched++;
            if ($matched > $offset) {
                $found[] = $row;
                if (count($found) === $count) {
                    $position = $index;
                    break;
                }
            }
        }
        if ($count !== 0) {
            $handler->order = $order;
            $handler->placed = true;
            $handler->position = $position ?? ($step > 0 ? count($numbers) : -1);
        }

        return $this->result($plan, $found, $table, $session, $context);
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
            throw ErrorCode::NoDatabase->error();
        }
        if ($session->instance->dictionary->schema($schema) === null) {
            throw ErrorCode::BadDatabase->error($schema);
        }
        if (isset($session->instance->dictionary->schema($schema)?->views[$statement->table->name->value])) {
            throw ErrorCode::WrongObject->error($schema, $statement->table->name->value, 'BASE TABLE');
        }
        if ($session->instance->dictionary->table($schema, $statement->table->name->value) === null) {
            throw ErrorCode::NoSuchTable->error($schema, $statement->table->name->value);
        }
        $name = $statement->alias->value ?? $statement->table->name->value;
        if (isset($session->handlers[mb_strtolower($name)])) {
            throw ErrorCode::NonUniqueTable->error($name);
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
            throw ErrorCode::UnknownTable->error($name, 'HANDLER');
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

        throw ErrorCode::KeyMissing->error($name, $handler->name);
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
     * Answers the numbers of the rows in the order a read follows: the natural order, or the order of an index after it.
     *
     * @param array<int, list<int|float|string|null>> $rows The rows in their natural order
     * @return list<int>
     */
    public function ordered(array $rows, ?Key $key, StoredTable $table): array
    {
        $numbers = array_keys($rows);
        if ($key !== null) {
            usort($numbers, fn (int $left, int $right): int => $this->compare($rows[$left], $key, $table, array_map(static fn (int $column) => $rows[$right][$column], $key->columns)));
        }

        return $numbers;
    }

    /**
     * Compares the leading columns of an index in a row with values, as many columns as there are values.
     *
     * @param list<int|float|string|null> $row
     * @param list<int|float|string|null> $values
     */
    public function compare(array $row, Key $key, StoredTable $table, array $values): int
    {
        foreach ($values as $index => $value) {
            $column = $key->columns[$index];
            $order = Order::compare($row[$column], $value, $table->definition->columns[$column]->domain);
            if ($order !== 0) {
                return $order;
            }
        }

        return 0;
    }

    /**
     * Answers where a read starts in its order and the direction it moves in.
     *
     * @param list<int> $numbers
     * @param array<int, list<int|float|string|null>> $rows
     * @param list<int|float|string|null> $values
     * @param int|null $position Where the cursor stands in the order, or null when the read starts the order afresh
     * @return array{int, int}
     */
    public function start(HandlerScan|HandlerIndexRead|HandlerIndexSeek $statement, array $numbers, array $rows, ?Key $key, StoredTable $table, array $values, ?int $position): array
    {
        $last = count($numbers) - 1;
        if ($statement instanceof HandlerScan) {
            return [$statement->direction === ScanDirection::Next && $position !== null ? $position + 1 : 0, 1];
        }
        if ($statement instanceof HandlerIndexRead) {
            return match ($statement->direction) {
                IndexDirection::First => [0, 1],
                IndexDirection::Last => [$last, -1],
                IndexDirection::Next => [$position === null ? 0 : $position + 1, 1],
                IndexDirection::Previous => [$position === null ? $last : $position - 1, -1],
            };
        }
        assert($key !== null);
        $comparisons = array_map(fn (int $number): int => $this->compare($rows[$number], $key, $table, $values), $numbers);
        $forward = match ($statement->comparison) {
            KeyComparison::Equal, KeyComparison::GreaterOrEqual => static fn (int $order): bool => $order >= 0,
            KeyComparison::Greater => static fn (int $order): bool => $order > 0,
            KeyComparison::LessOrEqual => static fn (int $order): bool => $order <= 0,
            KeyComparison::Less => static fn (int $order): bool => $order < 0,
        };
        if ($statement->comparison === KeyComparison::LessOrEqual || $statement->comparison === KeyComparison::Less) {
            $index = $last;
            while ($index >= 0 && !$forward($comparisons[$index])) {
                $index--;
            }

            return [$index, -1];
        }
        $index = 0;
        while ($index <= $last && !$forward($comparisons[$index])) {
            $index++;
        }

        return [$index, 1];
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
