<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Write;

use MySqlMemory\Command\Command;
use MySqlMemory\Error\DataError;
use MySqlMemory\Error\QueryError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Iterator\Builder;
use MySqlMemory\Plan\Path\Transform\Filter;
use MySqlMemory\Plan\Planner;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use MySqlMemory\Storage\Store;
use MySqlMemory\Storage\Writer;
use Override;
use SqlSemantics\Platform\MySql\Statement\Dml\MultipleDelete;
use SqlSemantics\Platform\MySql\Statement\Dml\Update;
use SqlSemantics\Platform\MySql\Statement\Relation\TableList;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;

/**
 * Executes UPDATE and DELETE over several tables: the rows of the join that meet WHERE decide the rows of each target table that change.
 *
 * Each row of a target table changes at most once, however many joined rows it takes part in.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/update.html, https://dev.mysql.com/doc/refman/8.4/en/delete.html.
 *
 * @visibility MySqlMemory
 */
final class MultipleChangeCommand implements Command
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
     * Updates or deletes the rows.
     */
    #[Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        assert($statement instanceof Update || $statement instanceof MultipleDelete);
        $planner = new Planner($statement, $operation->facts, $session->settings(), $connection, $session->instance->dictionary);
        $context->strict = $context->modes->strict() && !($statement instanceof Update && $statement->ignore);
        $scope = new Scope();
        $relation = count($statement->tables) === 1 ? $statement->tables[0] : new TableList($statement->tables);
        $path = $planner->relations->plan($relation, $scope);
        if ($statement->where !== null) {
            $path = new Filter($path, $planner->compiler->compile($statement->where, $scope));
        }
        $builder = new Builder();
        $iterator = $builder->build($path);
        $frame = new Frame($context);
        $iterator->init($frame);
        $matches = [];
        while (($row = $iterator->read()) !== null) {
            $numbers = [];
            foreach ($scope->scans as $id => $scan) {
                $numbers[$id] = $builder->scans[spl_object_id($scan)]->current ?? null;
            }
            $matches[] = [$row, $numbers];
        }
        if ($statement instanceof MultipleDelete) {
            return $this->delete($statement, $scope, $matches, $session, $context);
        }

        return $this->update($statement, $planner, $scope, $matches, $session, $context);
    }

    /**
     * Deletes the rows of the target tables that joined rows took part in.
     *
     * @param list<array{list<int|float|string|null>, array<int, int|null>}> $matches
     */
    public function delete(MultipleDelete $statement, Scope $scope, array $matches, Session $session, Context $context): Reply
    {
        $deleted = 0;
        foreach ($statement->targets as $target) {
            $id = $this->occurrence($scope, $target->name->value);
            if ($id === null) {
                throw QueryError::UnknownTable->error($target->name->value, 'MULTI DELETE');
            }
            $table = $scope->scans[$id]->table;
            $session->transaction->touch($table);
            $numbers = array_unique(array_filter(array_map(static fn (array $match): ?int => $match[1][$id] ?? null, $matches), static fn (?int $number): bool => $number !== null));
            foreach ($numbers as $number) {
                if (isset($table->data->rows[$number])) {
                    $table->data->delete($number);
                    $deleted++;
                }
            }
        }

        return new Completion($deleted, 0, $context->diagnostics->count());
    }

    /**
     * Finds the occurrence a target names: by alias, or by table name when it has none.
     */
    public function occurrence(Scope $scope, string $name): ?int
    {
        foreach ($scope->nodes as $node) {
            $id = spl_object_id($node);
            if ($node instanceof TableReference && isset($scope->scans[$id]) && ($node->alias->value ?? $node->name->name->value) === $name) {
                return $id;
            }
        }

        return null;
    }

    /**
     * Applies the assignments to the rows the joined rows took part in, each row once.
     *
     * @param list<array{list<int|float|string|null>, array<int, int|null>}> $matches
     */
    public function update(Update $statement, Planner $planner, Scope $scope, array $matches, Session $session, Context $context): Reply
    {
        $assignments = [];
        foreach ($statement->assignments as $assignment) {
            $resolution = $planner->compiler->facts->scalar($assignment->column)->resolution;
            if (!$resolution instanceof ResolvedColumn || !isset($scope->scans[spl_object_id($resolution->relation)])) {
                throw QueryError::BadField->error($assignment->column->name->value, 'field list');
            }
            $id = spl_object_id($resolution->relation);
            $position = $planner->compiler->names->position($scope, $resolution);
            $assignments[] = [$id, $position, $planner->compiler->compile($assignment->value, $scope)];
        }
        $frame = new Frame($context);
        $done = [];
        $changed = 0;
        $direct = $this->direct($scope);
        foreach ($matches as $index => [$row, $numbers]) {
            $frame->row = $row;
            $values = [];
            foreach ($assignments as [$id, $position, $value]) {
                $values[] = [$id, $position, $value->evaluate($frame), $value];
            }
            $changed += $this->apply($values, $numbers, $scope, $done, $session, $context, $index + 1, $statement->ignore, $direct);
        }

        return new Completion($changed, 0, $context->diagnostics->count(), sprintf('Rows matched: %d  Changed: %d  Warnings: %d', count($done), $changed, $context->diagnostics->count()));
    }

    /**
     * Writes the assigned values of one joined row to the rows of their tables; answers how many rows changed.
     *
     * NULL for a NOT NULL column is refused under a strict mode and stored as the implicit
     * default of the column with a warning otherwise.
     *
     * @param list<array{int, int, int|float|string|null, \MySqlMemory\Evaluation\Evaluable}> $values
     * @param array<int, int|null> $numbers
     * @param array<string, true> $done
     * @param array{int, string}|null $direct The occurrence updated while the join is read, and the name it is written under
     */
    public function apply(array $values, array $numbers, Scope $scope, array &$done, Session $session, Context $context, int $line, bool $ignore, ?array $direct = null): int
    {
        $rows = [];
        $assigned = [];
        foreach ($values as [$id, $position, $value, $expression]) {
            $number = $numbers[$id] ?? null;
            if ($number === null || isset($done[$id . ':' . $number])) {
                continue;
            }
            $table = $scope->scans[$id]->table;
            $rows[$id] ??= [$table, $number, $table->data->rows[$number] ?? null];
            if ($rows[$id][2] === null) {
                continue;
            }
            $column = $table->definition->columns[$position];
            $stored = (new Store($context, $line, $direct !== null && $direct[0] === $id ? $direct[1] : ''))->value($value, $expression->domain(), $column);
            if ($stored === null && !$column->nullable()) {
                if ($context->strict) {
                    throw DataError::BadNull->error($column->name);
                }
                $context->warning(DataError::BadNull, $column->name);
                $stored = (new Writer($table, $context))->implicit($column);
            }
            $rows[$id][2][$position] = $stored;
            $assigned[$id][$position] = true;
        }
        $changed = 0;
        foreach ($rows as $id => [$table, $number, $row]) {
            $done[$id . ':' . $number] = true;
            if ($row === null || $row === $table->data->rows[$number]) {
                continue;
            }
            $session->transaction->touch($table);
            $writer = new Writer($table, $context);
            $row = $writer->refresh($row, $assigned[$id] ?? []);
            $conflict = $writer->conflict($row, $number);
            if ($conflict !== null) {
                if ($ignore) {
                    $context->warning(DataError::DuplicateEntry, ...$writer->entry($row, $conflict[1]));
                    continue;
                }
                throw $writer->duplicate($row, $conflict[1]);
            }
            $table->data->update($number, $row);
            $changed++;
        }

        return $changed;
    }

    /**
     * Answers the occurrence the server updates while it reads the join, and the name it writes it under; null when there is none.
     *
     * It is the first table of the join when every relation of the join is a table and no other
     * occurrence reads that table; the server writes the other tables after the join is read, and
     * names no table when it reports a value refused for one of them.
     *
     * @return array{int, string}|null
     */
    public function direct(Scope $scope): ?array
    {
        $first = null;
        $tables = [];
        foreach ($scope->nodes as $node) {
            $id = spl_object_id($node);
            if (!isset($scope->offsets[$id])) {
                continue;
            }
            if (!$node instanceof TableReference || !isset($scope->scans[$id])) {
                return null;
            }
            $first ??= [$id, $node->alias->value ?? $node->name->name->value];
            $tables[] = $scope->scans[$id]->table;
        }
        if ($first === null || count(array_filter($tables, static fn ($table): bool => $table === $scope->scans[$first[0]]->table)) > 1) {
            return null;
        }

        return $first;
    }

    /**
     * Tells whether an UPDATE writes through a join, which this command executes.
     */
    public static function joined(Update $update): bool
    {
        return count($update->tables) !== 1 || !$update->tables[0] instanceof TableReference;
    }
}
