<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Write;

use MySqlMemory\Command\Command;
use MySqlMemory\Dictionary\StoredTable;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Iterator\Builder;
use MySqlMemory\Plan\Planner;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use MySqlMemory\Typing\Domain;
use Override;
use SqlSemantics\Platform\MySql\Statement\Dml\DefaultRequest;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertInto;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertQuery;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertSet;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Name\TableWildcard;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;

/**
 * Executes INSERT and REPLACE, with VALUES, SET or a query, IGNORE, and ON DUPLICATE KEY UPDATE.
 *
 * Each row takes the values named for its columns and the defaults of the others. A row that
 * conflicts with a unique key is refused (ER_DUP_ENTRY); with IGNORE it is skipped with a
 * warning, with REPLACE the rows it conflicts with are deleted first, and with ON DUPLICATE KEY
 * UPDATE the row it conflicts with is updated instead. The affected-row count is that of the
 * server: 1 per inserted row, 2 per row replaced or updated by ON DUPLICATE KEY UPDATE.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/insert.html.
 *
 * @visibility MySqlMemory
 */
final class InsertCommand implements Command
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
     * Inserts the rows.
     */
    #[Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        assert($statement instanceof InsertRows || $statement instanceof InsertSet || $statement instanceof InsertQuery);
        $into = $statement->into;
        $table = $this->table($operation, $into, $session);
        $planner = new Planner($statement, $operation->facts, $session->settings(), $connection, $session->instance->dictionary);
        $context->strict = $context->modes->strict() && !$into->ignore;
        $session->transaction->touch($table);
        [$positions, $sources] = $this->sources($statement, $planner, $table, $context);
        $rows = new Rows($table, $context, $planner, $into, $statement->onDuplicate, $session, $statement instanceof InsertQuery ? null : $statement->alias);
        $queried = $statement instanceof InsertQuery && $statement->values() === null;
        $rows->sources = $queried ? $planner->carried[1] ?? null : null;
        foreach ($sources as $index => $values) {
            if ($rows->sources !== null) {
                $rows->source = array_map(static fn (array|\MySqlMemory\Evaluation\Evaluable|DefaultRequest $value): int|float|string|null => is_array($value) ? $value[0] : null, array_slice($values, count($positions)));
                $values = array_slice($values, 0, count($positions));
            }
            $rows->write($values === [] ? [] : $positions, $values, $index + 1, count($sources) === 1 && !$queried, $queried);
        }
        return new Completion($rows->affected, $rows->identity->finish($session->variables), $context->diagnostics->count(), count($sources) > 1 || $statement instanceof InsertQuery ? sprintf('Records: %d  Duplicates: %d  Warnings: %d', count($sources), $rows->duplicates, $context->diagnostics->count()) : '');
    }

    /**
     * Finds the table an insert writes.
     *
     * @throws \MySqlMemory\Error\SqlError When the table does not exist
     */
    public function table(Operation $operation, InsertInto $into, Session $session): StoredTable
    {
        $resolution = $operation->facts->relation($into->table)->table;
        $schema = $into->table->name->schema->value ?? $session->variables->database;
        if ($resolution instanceof DeclaredTable) {
            $schema = $resolution->table->name->schema->value ?? $schema;
        }
        $table = $session->instance->dictionary->table($schema, $into->table->name->name->value);
        if ($table === null) {
            throw QueryError::NoSuchTable->error($schema, $into->table->name->name->value);
        }
        if ($into->table->partitions !== [] && $table->definition->partitioning === null) {
            throw SchemaError::PartitionClauseOnNonpartitioned->error();
        }

        return $table;
    }

    /**
     * Answers the positions of the columns written and, for each row, the value of each: an evaluable, DEFAULT, or a computed value with its domain.
     *
     * The values are compiled in a scope that holds the table written, without its rows, where
     * DEFAULT(column) finds the default of a column. An empty row without a column list names no column: each takes its default as a column no
     * row names does, and a column without one is warned about once (verified on a live 8.4
     * server).
     *
     * @return array{list<int>, list<list<Evaluable|DefaultRequest|array{int|float|string|null, Domain}>>}
     */
    public function sources(InsertRows|InsertSet|InsertQuery $statement, Planner $planner, StoredTable $table, Context $context): array
    {
        $definition = $table->definition;
        $facts = $planner->compiler->facts;
        $position = static function (ColumnUse|TableWildcard $use) use ($facts, $definition): int {
            if ($use instanceof TableWildcard) {
                throw StatementError::NotSupportedYet->error('a table wildcard in the column list of INSERT');
            }
            $resolution = $facts->scalar($use)->resolution;
            $declaration = $resolution instanceof ResolvedColumn ? $resolution->slot->declaration() : null;
            foreach ($definition->columns as $index => $column) {
                if ($column->declaration === $declaration || ($declaration === null && strcasecmp($column->name, $use->name->value) === 0)) {
                    return $index;
                }
            }
            throw QueryError::BadField->error($use->name->value, 'field list');
        };
        $scope = new Scope();
        $scope->tables[spl_object_id($statement->into->table)] = $definition;
        if ($statement instanceof InsertSet) {
            $positions = array_map(static fn ($assignment): int => $position($assignment->column), $statement->assignments);
            $values = array_map(fn ($assignment) => $assignment->value instanceof DefaultRequest ? $assignment->value : $planner->compiler->compile($assignment->value, $scope), $statement->assignments);

            return [$positions, [$values]];
        }
        $columns = $statement->into->columns;
        $columns = $columns !== null && $columns->columns === [] ? null : $columns;
        $positions = $columns === null ? array_values(array_filter(array_keys($definition->columns), static fn (int $index): bool => !$definition->columns[$index]->invisible)) : array_map($position, $columns->columns);
        $values = $statement instanceof InsertQuery ? $statement->values() : null;
        if ($statement instanceof InsertQuery && $values === null) {
            return [$positions, $this->queried($statement, $planner, $context, count($positions))];
        }
        $rows = [];
        foreach ($values === null ? $statement->rows : $values->rows as $row) {
            $values = array_map(fn ($value) => $value instanceof DefaultRequest ? $value : $planner->compiler->compile($value, $scope), $row->values);
            $rows[] = $values === [] && $columns === null ? [] : $values;
        }

        return [$positions, $rows];
    }

    /**
     * Computes the rows of an INSERT ... SELECT before any is written.
     *
     * @return list<list<array{int|float|string|null, Domain}>>
     */
    public function queried(InsertQuery $statement, Planner $planner, Context $context, int $width): array
    {
        $source = $statement->source;
        while ($source instanceof \SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery || $source instanceof \SqlSemantics\Platform\MySql\Statement\Query\QueryStatement) {
            $source = $source->query;
        }
        $planner->carrying = $statement->onDuplicate !== [] && $source instanceof \SqlSemantics\Platform\MySql\Statement\Query\Select ? $source : null;
        $plan = $planner->query($statement->source, null);
        if (count($plan->domains) !== $width) {
            throw QueryError::WrongValueCountOnRow->error(1);
        }
        (new \MySqlMemory\Session\Problem\Sampling())->optimized($statement->source, $planner->compiler->facts, $planner->settings, $planner->dictionary);
        $iterator = (new Builder())->build($plan->root);
        $iterator->init(new Frame($context));
        $rows = [];
        while (($row = $iterator->read()) !== null) {
            $values = [];
            foreach ($plan->domains as $index => $domain) {
                $values[] = [$row[$index], $domain];
            }
            if ($planner->carried !== null) {
                $position = $planner->carried[0];
                foreach ($planner->carried[1]->columns as $domains) {
                    foreach ($domains as $domain) {
                        $values[] = [$row[$position++], $domain];
                    }
                }
            }
            $rows[] = $values;
        }

        return $rows;
    }
}
