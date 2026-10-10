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
use SqlSemantics\Contract\GrammarRelease;
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
     * The name of each integer type, by the name of the field of the column; a LONGLONG of 11 or
     * more display characters is a BIGINT instead.
     *
     * @var array<string, string>
     */
    public const INTEGERS = ['Tiny' => 'TINYINT', 'Short' => 'SMALLINT', 'Int24' => 'MEDIUMINT', 'Long' => 'INT', 'LongLong' => 'INT'];

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
        $definition = (new TableRebuild($this->session, $this->context, $this->connection))->definition($layout, $this->session->instance->dictionary->declarations());
        $table = new StoredTable($definition, new Heap(), created: CreateTableCommand::created($this->context));
        [$records, $affected, $duplicates] = $this->fill($table, $plan, $create->query->duplicate);
        $table->updated = $affected > 0 ? time() : $table->updated;
        $this->session->instance->dictionary->store($table);
        $warnings = $this->context->diagnostics->count();

        return new Completion($affected, 0, $warnings, 'Records: ' . $records . '  Duplicates: ' . $duplicates . '  Warnings: ' . $warnings);
    }

    /**
     * Answers the column definitions of the columns of a query that the statement does not declare.
     *
     * @param array<string, true> $declared The lowercase names of the columns the statement declares
     * @return list<ColumnElement>
     *
     * @throws SqlError When a definition does not parse
     */
    public function elements(QueryPlan $plan, array $declared): array
    {
        $elements = [];
        $texts = [];
        foreach ($plan->domains as $position => $domain) {
            $name = $plan->names[$position] ?? '';
            if (isset($declared[mb_strtolower($name)])) {
                continue;
            }
            $copied = $this->copied($plan->origins[$position] ?? null, $name);
            if ($copied !== null) {
                $elements[$position] = $copied;
            } else {
                $texts[$position] = '`' . str_replace('`', '``', $name) . '` ' . $this->type($domain) . $this->nullability($domain);
            }
        }
        if ($texts !== []) {
            $parsed = $this->session->analyze('CREATE TABLE `t` (' . implode(', ', $texts) . ')')->statement;
            assert($parsed instanceof CreateTable);
            foreach (array_keys($texts) as $index => $position) {
                $element = $parsed->elements[$index];
                assert($element instanceof ColumnElement);
                $elements[$position] = $element;
            }
        }
        ksort($elements);

        return array_values($elements);
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
     * Answers the type a column of a query takes in the table, as SQL.
     *
     * A NULL column is a BINARY(0) before MySQL 8.1 and a VARBINARY(0) from 8.1, and so is the
     * column of a set operation that is NULL in every operand (verified on live 8.0 and 8.4
     * servers).
     */
    public function type(Domain $domain): string
    {
        $unsigned = $domain->unsigned ? ' UNSIGNED' : '';
        $fraction = $domain->decimals > 0 && $domain->decimals < Domain::NOT_FIXED ? '(' . $domain->decimals . ')' : '';

        return match ($domain->field) {
            Field::Tiny, Field::Short, Field::Int24, Field::Long, Field::LongLong => $this->integer($domain, self::INTEGERS[$domain->field->name]),
            Field::Decimal, Field::NewDecimal => 'DECIMAL(' . $domain->precision() . ',' . $domain->decimals . ')' . $unsigned,
            Field::Float => 'FLOAT' . $unsigned,
            Field::Double => 'DOUBLE' . $unsigned,
            Field::Null => in_array($this->session->settings()->release(), [GrammarRelease::MySql5651, GrammarRelease::MySql5744, GrammarRelease::MySql8044], true) ? 'BINARY(0)' : 'VARBINARY(0)',
            Field::Date, Field::NewDate => 'DATE',
            Field::Time => 'TIME' . $fraction,
            Field::DateTime => 'DATETIME' . $fraction,
            Field::Timestamp => 'TIMESTAMP' . $fraction,
            Field::Year => 'YEAR',
            Field::Bit => 'BIT(' . max(1, $domain->length) . ')',
            Field::Json => 'JSON',
            Field::Vector => 'VECTOR(' . max(1, intdiv($domain->length, 4)) . ')',
            Field::Geometry => 'GEOMETRY',
            Field::Enum, Field::Set, Field::VarChar, Field::VarString, Field::String, Field::TinyBlob, Field::Blob, Field::MediumBlob, Field::LongBlob => $this->character($domain),
        };
    }

    /**
     * Answers the type an integer column of a query takes in the table, as SQL: it keeps the
     * display width of the expression, and a LONGLONG is a BIGINT from 11 display characters on.
     *
     * @param string $name The name of the integer type of the field
     */
    public function integer(Domain $domain, string $name): string
    {
        $name = $domain->field === Field::LongLong && $domain->length >= 11 ? 'BIGINT' : $name;

        return $name . '(' . ($domain->display ?? $domain->length) . ')' . ($domain->unsigned ? ' UNSIGNED' : '');
    }

    /**
     * Answers the type a string, ENUM or SET column of a query takes in the table, as SQL: a
     * string of more than 512 characters, and a large object, is the TEXT or BLOB type that holds
     * it, and a type that is not binary states its character set and collation.
     */
    public function character(Domain $domain): string
    {
        $bytes = $domain->collation->bytes();
        $collation = $bytes ? '' : ' CHARACTER SET ' . $domain->collation->charset->name . ' COLLATE ' . $domain->collation->name;
        if ($domain->field === Field::Enum || $domain->field === Field::Set) {
            $members = implode(',', array_map(static fn (string $member): string => "'" . str_replace(['\\', "'"], ['\\\\', "''"], $member) . "'", $domain->members));

            return ($domain->field === Field::Enum ? 'ENUM' : 'SET') . '(' . $members . ')' . $collation;
        }
        if ($bytes && $domain->field === Field::String && $domain->length === 0) {
            return 'BINARY(0)';
        }
        $text = $domain->length * $domain->collation->charset->maxLength;
        $blob = in_array($domain->field, [Field::TinyBlob, Field::Blob, Field::MediumBlob, Field::LongBlob], true);
        if (!$blob && $domain->length <= 512) {
            return ($bytes ? 'VARBINARY' : 'VARCHAR') . '(' . $domain->length . ')' . $collation;
        }

        return $this->text($blob && $bytes ? $domain->length : $text, $bytes) . $collation;
    }

    /**
     * Answers the TEXT or BLOB type that holds a number of bytes.
     */
    public function text(int $bytes, bool $binary): string
    {
        $size = match (true) {
            $bytes < 256 => 'TINY',
            $bytes < 65536 => '',
            $bytes < 16777216 => 'MEDIUM',
            default => 'LONG',
        };

        return $size . ($binary ? 'BLOB' : 'TEXT');
    }

    /**
     * Answers NOT NULL with the implicit default of the type for a column that cannot be NULL; a date, a datetime and the large types take no default.
     */
    public function nullability(Domain $domain): string
    {
        if ($domain->nullable || $domain->field === Field::Null) {
            return '';
        }
        $default = match ($domain->kind) {
            Kind::Integer, Kind::Double, Kind::Year => "'0'",
            Kind::Decimal => "'0" . ($domain->decimals > 0 ? '.' . str_repeat('0', $domain->decimals) : '') . "'",
            Kind::Time => "'00:00:00" . ($domain->decimals > 0 && $domain->decimals < Domain::NOT_FIXED ? '.' . str_repeat('0', $domain->decimals) : '') . "'",
            Kind::String => $domain->length > 512 || in_array($domain->field, [Field::TinyBlob, Field::Blob, Field::MediumBlob, Field::LongBlob, Field::Geometry, Field::Enum, Field::Set, Field::Vector], true) ? null : "''",
            Kind::Date, Kind::DateTime, Kind::Json, Kind::Bit, Kind::Null => null,
        };

        return ' NOT NULL' . ($default === null ? '' : ' DEFAULT ' . $default);
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
            $sources = [];
            foreach ($plan->names as $index => $name) {
                $sources[mb_strtolower($name)] = $index;
            }
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
        [$has, $value] = $writer->default($column, $frame);
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
