<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Definition;

use MySqlMemory\Dictionary\ColumnDefinition;
use MySqlMemory\Dictionary\Schema;
use MySqlMemory\Dictionary\StoredTable;
use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Iterator\Builder;
use MySqlMemory\Plan\ColumnOrigin;
use MySqlMemory\Plan\Planner;
use MySqlMemory\Plan\QueryPlan;
use MySqlMemory\Result\Completion;
use MySqlMemory\Session\Session;
use MySqlMemory\Storage\Heap;
use MySqlMemory\Storage\Store;
use MySqlMemory\Storage\Writer;
use MySqlMemory\Typing\Domain;
use SqlSemantics\Platform\MySql\Statement\Dml\DuplicateHandling;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnName;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition as ColumnElement;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Operation;

/**
 * Executes CREATE TABLE ... SELECT: creates a table with the columns the statement declares and the columns of its query, and fills it with the rows of the query.
 *
 * A column the statement declares keeps its definition, and a column of the query that it
 * declares takes its values; the other columns of the query follow, in their order. A column of
 * the query that reads a column of a table copies its definition without its keys. Another
 * column takes the type of its expression: an integer keeps the display width of the
 * expression and is a BIGINT from 11 display characters on, a string of more than 512 characters a TEXT type, NULL a VARBINARY(0), and a column
 * that cannot be NULL is NOT NULL with the implicit default of its type, but for a date and a
 * datetime. Rows are stored as INSERT ... SELECT stores them; a row that repeats a unique key
 * fails the statement, is skipped with IGNORE and replaces the row with REPLACE, which counts as
 * one row affected. The table is
 * created only when every row is stored. Every rule was verified on a live 8.4 server.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table-select.html.
 *
 * @visibility MySqlMemory
 */
final class TableQueries
{
    /**
     * @param Session $session The session creating the table
     * @param Context $context The statement
     * @param Connection $connection The connection
     */
    public function __construct(public readonly Session $session, public readonly Context $context, public readonly Connection $connection)
    {
    }

    /**
     * Creates and fills the table.
     *
     * @throws SqlError When a column, a key or a row is refused
     */
    public function create(CreateTable $create, Operation $operation, Schema $schema): Completion
    {
        assert($create->query !== null);
        $planner = new Planner($create, $operation->facts, $this->session->settings(), $this->connection, $this->session->instance->dictionary);
        $plan = $planner->query($create->query->query, null);
        $seen = [];
        foreach ($plan->names as $name) {
            if (isset($seen[mb_strtolower($name)])) {
                throw SchemaError::DuplicateFieldName->error($name);
            }
            $seen[mb_strtolower($name)] = true;
        }
        $declared = [];
        $columns = [];
        $keys = [];
        foreach ($create->elements as $element) {
            if ($element instanceof ColumnElement) {
                $declared[mb_strtolower($element->name->column->value)] = true;
                $columns[] = [$element, null];
            } else {
                $keys[] = [$element, null];
            }
        }
        foreach ($this->elements($plan, $declared) as $element) {
            $columns[] = [$element, null];
        }
        $layout = new TableLayout(new QualifiedName($create->name->name, new Name($schema->name)), $columns, $keys, $create->options, $create->temporaryWords);
        $layout->partitioning = $create->partitioning;
        $layout->implicitCollations = $this->implicitCollations($plan, $declared);
        $layout->reportedLengths = $this->reportedLengths($plan, $declared, $operation->facts->query($create->query->query));
        $layout->inferredDefaults = $this->inferredDefaults($plan, $declared);
        $definition = (new TableRebuild($this->session, $this->context, $this->connection))->definition($layout, $this->session->instance->dictionary->declarations());
        $table = new StoredTable($definition, new Heap(), created: CreateTableCommand::created($this->context), clock: (int) floor($this->context->variables->clock()));
        [$records, $affected, $duplicates] = $this->fill($table, $plan, $create->query->duplicate);
        $table->updated = $affected > 0 ? (int) floor($this->context->variables->clock()) : $table->updated;
        $this->session->instance->dictionary->store($table);
        $warnings = $this->context->diagnostics->raised();

        return new Completion($affected, 0, $warnings, 'Records: ' . $records . '  Duplicates: ' . $duplicates . '  Warnings: ' . $warnings);
    }

    /**
     * Retains computed DOUBLE widths that have no CREATE TABLE spelling.
     *
     * @param array<string, true> $declared The names explicitly declared by CREATE TABLE
     * @return array<string, int>
     */
    public function reportedLengths(QueryPlan $plan, array $declared, \SqlSemantics\Statement\Fact\QueryFact $query): array
    {
        $lengths = [];
        $fields = $query->fields();
        \SqlSemantics\Diagnostic\Check::invariant($fields !== null, 'A prepared creation query has a complete field list.');
        $columns = new \SqlSemantics\Platform\MySql\Rules\TableDefinition\Creation\QueryColumns($this->session->settings()->release(), $this->context->modes->strict());
        foreach ($plan->domains as $position => $domain) {
            $name = mb_strtolower($plan->names[$position]);
            if (!isset($declared[$name]) && $domain->field === Field::Double && $domain->decimals === Domain::NOT_FIXED) {
                $copied = $this->copied($plan->origins[$position] ?? null, $name) !== null;
                $lengths[$name] = $columns->width($domain->resolved(), $fields->at($position)->expression, $copied);
            }
        }

        return $lengths;
    }

    /**
     * Retains defaults implied by computed fields. In 5.6, zero temporal defaults
     * are inferred even when NO_ZERO_DATE would reject an explicit DEFAULT clause.
     *
     * @param array<string, true> $declared The explicitly declared column names
     * @return array<string, string>
     */
    public function inferredDefaults(QueryPlan $plan, array $declared): array
    {
        $defaults = [];
        if ($this->session->settings()->release() !== \SqlSemantics\Contract\GrammarRelease::MySql5651 && $this->context->modes->strict()) {
            return $defaults;
        }
        $columns = new \SqlSemantics\Platform\MySql\Rules\TableDefinition\Creation\QueryColumns($this->session->settings()->release(), $this->context->modes->strict());
        foreach ($plan->domains as $position => $domain) {
            $name = $plan->names[$position];
            if (!in_array($domain->kind, [Kind::Date, Kind::DateTime], true) || $domain->nullable || isset($declared[mb_strtolower($name)]) || $this->copied($plan->origins[$position] ?? null, $name) !== null) {
                continue;
            }
            $default = $columns->default($domain->resolved());
            if ($default !== null) {
                $defaults[mb_strtolower($name)] = $default;
            }
        }

        return $defaults;
    }

    /**
     * Records computed columns whose default character-set collation was not declared.
     * Direct column copies retain that property from their source column.
     *
     * @param array<string, true> $declared The lowercase names explicitly declared by CREATE TABLE
     * @return array<string, true>
     */
    public function implicitCollations(QueryPlan $plan, array $declared): array
    {
        $implicit = [];
        foreach ($plan->names as $position => $name) {
            if (isset($declared[mb_strtolower($name)])) {
                continue;
            }
            $origin = $plan->origins[$position] ?? null;
            $table = $origin === null ? null : $this->session->instance->dictionary->table($origin->schema, $origin->originalTable);
            $index = $table?->definition->position($origin->column ?? '');
            if ($index === null || ($table?->definition->columns[$index]->implicitCollation ?? false)) {
                $implicit[mb_strtolower($name)] = true;
            }
        }

        return $implicit;
    }

    /**
     * Answers the column definitions of the columns of a query that the statement does not declare.
     *
     * @param array<string, true> $declared The lowercase names of the columns the statement declares
     * @return list<ColumnElement>
     *
     */
    public function elements(QueryPlan $plan, array $declared): array
    {
        $elements = [];
        $columns = new \SqlSemantics\Platform\MySql\Rules\TableDefinition\Creation\QueryColumns($this->session->settings()->release(), $this->context->modes->strict());
        foreach ($plan->domains as $position => $domain) {
            $name = $plan->names[$position] ?? '';
            if (isset($declared[mb_strtolower($name)])) {
                continue;
            }
            $elements[] = $this->copied($plan->origins[$position] ?? null, $name)
                ?? $columns->column(new Name($name), $domain->resolved(), $domain->nullable);
        }

        return $elements;
    }

    /**
     * Answers the definition of a column of a table a query column reads, under the name of the query column; null when it reads none.
     */
    public function copied(?ColumnOrigin $origin, string $name): ?ColumnElement
    {
        if ($origin === null || $origin->column === '' || $origin->originalTable === '') {
            return null;
        }
        $table = $this->session->instance->dictionary->table($origin->schema, $origin->originalTable);
        if ($table === null || $table->definition->statement === null) {
            return null;
        }
        $layout = TableLayout::of($table->definition);
        $index = $layout->column($origin->column);

        return $index === null ? null : new ColumnElement(new ColumnName(new Name($name)), $layout->columns[$index][0]->specification);
    }

    /**
     * Stores the rows of the query into the table and answers the rows read, the rows affected and the duplicates skipped.
     *
     * @return array{int, int, int}
     *
     * @throws SqlError When a row is refused
     */
    public function fill(StoredTable $table, QueryPlan $plan, ?DuplicateHandling $duplicate): array
    {
        $context = $this->context;
        $strict = $context->strict;
        $context->strict = $context->modes->strict() && $duplicate !== DuplicateHandling::Ignore;
        try {
            $definition = $table->definition;
            $writer = new Writer($table, $context);
            $sources = array_flip(array_map(mb_strtolower(...), $plan->names));
            $iterator = (new Builder())->build($plan->root);
            $frame = new Frame($context);
            $iterator->init($frame);
            [$records, $affected, $duplicates] = [0, 0, 0];
            while (($read = $iterator->read()) !== null) {
                $records++;
                $store = new Store($context, $records, $definition->name);
                $row = [];
                foreach ($definition->columns as $column) {
                    $source = $sources[mb_strtolower($column->name)] ?? null;
                    $row[] = $source === null ? $this->defaulted($column, $writer, $store, $frame) : $this->stored($read[$source] ?? null, $plan->domains[$source], $column, $store, $writer);
                }
                [$row] = $writer->autoIncrement($row, $context->modes->has('NO_AUTO_VALUE_ON_ZERO'));
                if (!(new Partition\QueryRows($definition, $context))->accept($row, $duplicate === DuplicateHandling::Ignore)) {
                    $duplicates++;
                    continue;
                }
                $conflict = $writer->conflict($row);
                if ($conflict !== null && $duplicate === DuplicateHandling::Ignore) {
                    $context->diagnostics->warning(DataError::DuplicateEntry, DataError::DuplicateEntry->message(...$writer->entry($row, $conflict[1])));
                    $duplicates++;

                    continue;
                }
                if ($conflict !== null && $duplicate !== DuplicateHandling::Replace) {
                    throw $writer->duplicate($row, $conflict[1]);
                }
                for (; $conflict !== null; $conflict = $writer->conflict($row)) {
                    $table->data->delete($conflict[0]);
                    $duplicates++;
                }
                $table->data->insert($row);
                $affected++;
            }

            return [$records, $affected, $duplicates];
        } finally {
            $context->strict = $strict;
        }
    }

    /**
     * Answers the value a column the query does not fill takes: its default, else NULL or the implicit default.
     *
     * @throws SqlError When the column has no default under a strict mode
     */
    public function defaulted(ColumnDefinition $column, Writer $writer, Store $store, Frame $frame): int|float|string|null
    {
        [$has, $value] = $writer->default($column, $frame, $store);
        if ($has || $column->nullable() || $column->autoIncrement) {
            return $value;
        }
        $store->adjust(DataError::NoDefaultForField, $column->name);

        return $writer->implicit($column);
    }

    /**
     * Answers the value a column takes from a value of the query, NULL for a NOT NULL column refused under a strict mode.
     *
     * @throws SqlError When the value is refused
     */
    public function stored(int|float|string|null $value, Domain $from, ColumnDefinition $column, Store $store, Writer $writer): int|float|string|null
    {
        $stored = $value === null ? null : $store->value($value, $from, $column);
        if ($stored !== null || $column->nullable() || $column->autoIncrement) {
            return $stored;
        }
        $store->adjust(DataError::BadNull, $column->name);

        return $writer->implicit($column);
    }
}
