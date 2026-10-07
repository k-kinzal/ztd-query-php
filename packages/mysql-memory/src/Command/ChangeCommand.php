<?php

declare(strict_types=1);

namespace MySqlMemory\Command;

use MySqlMemory\Dictionary\StoredTable;
use MySqlMemory\Error\ErrorCode;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Plan\Planner;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use MySqlMemory\Storage\ClusterOrder;
use MySqlMemory\Storage\Store;
use MySqlMemory\Storage\Writer;
use MySqlMemory\Value\Order;
use Override;
use SqlSemantics\Platform\MySql\Statement\Dml\Delete;
use SqlSemantics\Platform\MySql\Statement\Dml\Update;
use SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;

/**
 * Executes single-table UPDATE and DELETE.
 *
 * The rows of the table that meet the WHERE condition are taken in clustered index order, or in
 * the ORDER BY order, up to the LIMIT. An UPDATE evaluates its assignments left to right, each
 * seeing the values the ones before it assigned; it counts the rows it changed, not those it
 * matched.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/update.html.
 *
 * @visibility MySqlMemory
 */
final class ChangeCommand implements Command
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
        assert($statement instanceof Update || $statement instanceof Delete);
        $relation = $statement instanceof Delete ? $statement->table : ($statement->tables[0] ?? null);
        if ($statement instanceof Update && (count($statement->tables) !== 1 || !$relation instanceof TableReference)) {
            throw ErrorCode::NotSupportedYet->error('multiple-table UPDATE');
        }
        assert($relation instanceof TableReference || $relation instanceof WriteTarget);
        $table = $this->table($operation, $relation, $session);
        $planner = new Planner($statement, $operation->facts, $session->settings(), $connection, $session->instance->dictionary);
        $context->strict = $context->modes->strict() && !($statement instanceof Update && $statement->ignore);
        $scope = new Scope();
        $definition = $table->definition;
        $scope->place($relation, array_map(static fn ($column) => $column->domain, $definition->columns), array_map(static fn ($column): string => $column->name, $definition->columns), $definition);
        $matched = $this->matched($statement, $planner, $scope, $table, $context);
        $session->transaction->touch($table);
        if ($statement instanceof Delete) {
            foreach ($matched as $number) {
                $table->data->delete($number);
            }
            $session->variables->rowCount = count($matched);

            return new Completion(count($matched), 0, $context->diagnostics->count());
        }
        $changed = $this->update($statement, $planner, $scope, $table, $context, $matched);
        $session->variables->rowCount = $changed;

        return new Completion($changed, 0, $context->diagnostics->count(), sprintf('Rows matched: %d  Changed: %d  Warnings: %d', count($matched), $changed, $context->diagnostics->count()));
    }

    /**
     * Finds the table a statement changes.
     *
     * @throws \MySqlMemory\Error\SqlError When the table does not exist
     */
    public function table(Operation $operation, TableReference|WriteTarget $relation, Session $session): StoredTable
    {
        $resolution = $operation->facts->relation($relation)->table;
        $schema = $relation->name->schema?->value ?? $session->variables->database;
        if ($resolution instanceof DeclaredTable) {
            $schema = $resolution->table->name->schema?->value ?? $schema;
        }
        $table = $session->instance->dictionary->table($schema, $relation->name->name->value);
        if ($table === null) {
            throw ErrorCode::NoSuchTable->error($schema, $relation->name->name->value);
        }
        if ($relation->partitions !== []) {
            throw ErrorCode::PartitionClauseOnNonpartitioned->error();
        }

        return $table;
    }

    /**
     * Answers the numbers of the rows the statement changes, in the order it changes them.
     *
     * @return list<int>
     */
    public function matched(Update|Delete $statement, Planner $planner, Scope $scope, StoredTable $table, Context $context): array
    {
        $where = $statement->where === null ? null : $planner->compiler->compile($statement->where, $scope);
        $keys = array_map(static fn ($item): array => [$planner->compiler->compile($item->expression, $scope), $item->direction?->value === 'DESC'], $statement->orderBy);
        $frame = new Frame($context);
        $matched = [];
        foreach ((new ClusterOrder())->rows($table) as $number => $row) {
            $frame->row = $row;
            if ($where !== null && Convert::toBool($where->evaluate($frame), $where->domain(), $context) !== true) {
                continue;
            }
            $values = array_map(static fn (array $key) => $key[0]->evaluate($frame), $keys);
            $matched[] = [$number, $values];
        }
        if ($keys !== []) {
            usort($matched, static function (array $left, array $right) use ($keys): int {
                foreach ($keys as $index => [$key, $descending]) {
                    $order = Order::compare($left[1][$index], $right[1][$index], $key->domain());
                    if ($order !== 0) {
                        return $descending ? -$order : $order;
                    }
                }

                return 0;
            });
        }
        $numbers = array_map(static fn (array $match): int => $match[0], $matched);
        $limit = $planner->blocks->limit(new \MySqlMemory\Plan\Path\SingleRow(), $statement->limit, null);

        return $limit instanceof \MySqlMemory\Plan\Path\Limit ? array_slice($numbers, 0, $limit->count) : $numbers;
    }

    /**
     * Applies the assignments of an UPDATE to the matched rows and answers how many changed.
     *
     * @param list<int> $matched
     */
    public function update(Update $statement, Planner $planner, Scope $scope, StoredTable $table, Context $context, array $matched): int
    {
        $assignments = new Assignments($planner, $table);
        $compiled = [];
        foreach ($statement->assignments as $assignment) {
            $compiled[] = [$assignments->position($assignment->column), $planner->compiler->compile($assignment->value, $scope)];
        }
        $writer = new Writer($table, $context);
        $definition = $table->definition;
        $frame = new Frame($context);
        $changed = 0;
        foreach ($matched as $index => $number) {
            $old = $table->data->rows[$number];
            $row = $old;
            $store = new Store($context, $index + 1);
            $assigned = [];
            foreach ($compiled as [$position, $value]) {
                $frame->row = $row;
                $stored = $store->value($value->evaluate($frame), $value->domain(), $definition->columns[$position]);
                if ($stored === null && !$definition->columns[$position]->nullable()) {
                    if ($context->strict) {
                        throw ErrorCode::BadNull->error($definition->columns[$position]->name);
                    }
                    $context->warning(ErrorCode::BadNull, $definition->columns[$position]->name);
                    $stored = $writer->implicit($definition->columns[$position]);
                }
                $row[$position] = $stored;
                $assigned[$position] = true;
            }
            if (!$this->differs($old, $row, $table)) {
                continue;
            }
            $conflict = $writer->conflict($row, $number);
            if ($conflict !== null) {
                if ($statement->ignore) {
                    $context->warning(ErrorCode::DuplicateEntry, ...$writer->entry($row, $conflict[1]));
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
     * Tells whether a row differs from its old values.
     *
     * @param list<int|float|string|null> $old
     * @param list<int|float|string|null> $new
     */
    public function differs(array $old, array $new, StoredTable $table): bool
    {
        foreach ($new as $position => $value) {
            $domain = $table->definition->columns[$position]->domain;
            if (($value === null) !== ($old[$position] === null) || ($value !== null && Order::key($value, $domain) !== Order::key($old[$position], $domain)) || ($value !== null && (string) $value !== (string) $old[$position])) {
                return true;
            }
        }

        return false;
    }
}
