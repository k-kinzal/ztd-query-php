<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Access\Load;

use MySqlMemory\Command\Access\LoadDataCommand;
use MySqlMemory\Command\Write\Assignments;
use MySqlMemory\Command\Write\InsertCommand;
use MySqlMemory\Command\Write\Rows;
use MySqlMemory\Dictionary\StoredTable;
use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Plan\Planner;
use MySqlMemory\Result\Completion;
use MySqlMemory\Session\Session;
use MySqlMemory\Storage\Store;
use MySqlMemory\Storage\Writer;
use MySqlMemory\Typing\Domain;
use SqlSemantics\Platform\MySql\Statement\Dml\DuplicateHandling;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertInto;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadFormat;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadTable;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Statement\Operation;

/**
 * Writes decoded client records using the resolved LOAD target and assignments.
 * Runtime file data is converted before SET expressions and ordinary row checks.
 * LOCAL loading converts data errors to warnings and skips conflicting rows unless
 * REPLACE was requested. Triggers, partitions and identities use the shared writer.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/load-data.html.
 *
 * @visibility MySqlMemory
 */
final class ClientRows
{
    /**
     * Writes a nonempty DATA or XML client file without creating or parsing SQL.
     */
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection, string $contents): Completion
    {
        $statement = $operation->statement;
        assert($statement instanceof LoadTable);
        $replace = $statement->duplicates === DuplicateHandling::Replace;
        $into = new InsertInto($replace, null, !$replace, $statement->table);
        $table = (new InsertCommand())->table($operation, $into, $session);
        $planner = new Planner($statement, $operation->facts, $session->settings(), $connection, $session->instance->dictionary);
        $scope = new Scope();
        $scope->place($statement->table, array_map(static fn ($column) => $column->domain, $table->definition->columns), array_map(static fn ($column): string => $column->name, $table->definition->columns), $table->definition);
        $targets = $this->targets($statement, $planner, $table);
        $charset = Charset::known($statement->charset->name->value ?? (string) $session->variables->read('character_set_database'));
        $domain = Domain::string(strlen($contents), $charset->defaultCollation($session->settings()->release()));
        $context->strict = false;
        $session->transaction->touch($table);
        $rows = new Rows($table, $context, $planner, $into, [], $session);
        $separators = (new LoadDataCommand())->separatorBytes($statement);
        $fixed = $statement->format === LoadFormat::Data && ($separators['field:TERMINATED'] ?? "\t") === '' && ($separators['field:ENCLOSED'] ?? '') === '';
        $ignored = (int) ($statement->ignored->text ?? '0');
        $records = match ($statement->format) {
            LoadFormat::Data => $fixed ? (new FixedRecords())->read($contents, $separators, $this->widths($targets, $table), $ignored) : (new SeparatedRecords())->read($contents, $separators, $ignored),
            LoadFormat::Xml => $this->xml((new XmlRecords())->read($contents, $separators['xml:ROW'] ?? '<row>', $session->settings()->legacy(), $ignored), $targets, $table),
        };
        $deleted = $skipped = 0;
        foreach ($records as $index => $record) {
            [$positions, $values] = $this->values($record, $targets, $rows, $domain, $index + 1, $fixed);
            foreach ($statement->assignments as $assignment) {
                $positions[] = (new Assignments($planner, $table))->position($assignment->column);
                $values[] = $planner->compiler->compile($assignment->value, $scope);
            }
            $before = $rows->affected;
            $rows->write($positions, $values, $index + 1, false);
            $delta = $rows->affected - $before;
            $deleted += max(0, $delta - 1);
            $skipped += (int) ($delta === 0);
        }
        $rows->identity->finish($session->variables);
        return new Completion($rows->affected, $rows->identity->generated ?? 0, $context->diagnostics->raised(), sprintf('Records: %d  Deleted: %d  Skipped: %d  Warnings: %d', count($records), $deleted, $skipped, $context->diagnostics->raised()));
    }

    /**
     * @return list<int|UserVariable> Resolved column positions and retained variable identities
     */
    public function targets(LoadTable $statement, Planner $planner, StoredTable $table): array
    {
        if ($statement->columns === []) {
            return array_values(array_filter(array_keys($table->definition->columns), static fn (int $index): bool => !$table->definition->columns[$index]->invisible));
        }
        return array_map(static fn (\SqlSemantics\Platform\MySql\Statement\Name\ColumnUse|UserVariable $target): int|UserVariable => $target instanceof UserVariable ? $target : (new Assignments($planner, $table))->position($target), $statement->columns);
    }

    /**
     * Maps XML field names to resolved destinations; an absent selected field is NULL.
     * Unselected columns retain their ordinary defaults through the shared writer.
     *
     * @param list<array<string, string|null>> $records Named XML input records
     * @param list<int|UserVariable> $targets Resolved column positions and variable identities
     * @return list<list<string|null>> Values in semantic destination order
     */
    public function xml(array $records, array $targets, StoredTable $table): array
    {
        $names = array_map(static fn (int|UserVariable $target): string => $target instanceof UserVariable ? $target->name->value : $table->definition->columns[$target]->name, $targets);
        return array_map(static fn (array $record): array => array_map(static fn (string $name): ?string => $record[$name] ?? null, $names), $records);
    }

    /**
     * @param list<string|null> $record The decoded input fields
     * @param list<int|UserVariable> $targets The bound input destinations
     * @return array{list<int>, list<array{int|float|string|null, Domain}>} Converted values before SET evaluation
     */
    public function values(array $record, array $targets, Rows $rows, Domain $domain, int $number, bool $fixed): array
    {
        $positions = $values = [];
        $store = new Store($rows->context, $number, $rows->table->definition->name);
        foreach ($targets as $index => $target) {
            $raw = $record[$index] ?? null;
            if ($target instanceof UserVariable) {
                $rows->session->variables->assign($target->name->value, $raw, $domain);
                continue;
            }
            if (!array_key_exists($index, $record)) {
                if ($fixed) {
                    $column = $rows->table->definition->columns[$target];
                    $positions[] = $target;
                    $values[] = [(new Writer($rows->table, $rows->context))->implicit($column), $column->domain];
                }
                continue;
            }
            $column = $rows->table->definition->columns[$target];
            if ($column->generated !== null) {
                throw DataError::GeneratedColumnValue->error($column->name, $rows->table->definition->name);
            }
            if ($raw !== null && trim($raw) === '' && in_array($column->domain->kind, [\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind::Integer, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind::Decimal, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind::Double], true)) {
                $store->adjust(DataError::TruncatedWrongValueForField, strtolower($column->domain->kind->name), $raw, $column->name, $number);
            }
            $value = $store->value($raw, $domain, $column);
            if ($value === null && !$column->nullable() && !$column->autoIncrement) {
                $rows->context->warning(DataError::LoadNullToNotNull, $column->name, $number);
                $value = (new Writer($rows->table, $rows->context))->implicit($column);
            }
            $positions[] = $target;
            $values[] = [$value, $column->domain];
        }
        if (count($record) < count($targets)) {
            $rows->context->warning(DataError::LoadTooFewFields, $number);
        } elseif (count($record) > count($targets)) {
            $rows->context->warning(DataError::LoadTooManyFields, $number);
        }
        return [$positions, $values];
    }

    /**
     * @param list<int|UserVariable> $targets Input destinations in file order
     * @return list<int> Physical text widths used by fixed-row import
     */
    public function widths(array $targets, StoredTable $table): array
    {
        return array_map(static function (int|UserVariable $target) use ($table): int {
            if ($target instanceof UserVariable) {
                throw \MySqlMemory\Error\Family\StatementError::FixedRowsToVariable->error();
            }
            $domain = $table->definition->columns[$target]->domain;
            return match ($domain->field) {
                Field::Tiny => 4,
                Field::Short => 6,
                Field::Int24 => 9,
                Field::Long => 11,
                Field::LongLong => 20,
                Field::Decimal, Field::Float, Field::Double, Field::Null, Field::Timestamp, Field::Date, Field::Time, Field::DateTime, Field::Year, Field::NewDate, Field::VarChar, Field::Bit, Field::Vector, Field::Json, Field::NewDecimal, Field::Enum, Field::Set, Field::TinyBlob, Field::MediumBlob, Field::LongBlob, Field::Blob, Field::VarString, Field::String, Field::Geometry => $domain->length,
            };
        }, $targets);
    }
}
