<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Definition;

use MySqlMemory\Dictionary\ColumnDefinition;
use MySqlMemory\Dictionary\Fill;
use MySqlMemory\Dictionary\StoredTable;
use MySqlMemory\Dictionary\TableDefinition;
use MySqlMemory\Error\ErrorCode;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Plan\Planner;
use MySqlMemory\Session\Session;
use MySqlMemory\Storage\ClusterOrder;
use MySqlMemory\Storage\Heap;
use MySqlMemory\Storage\Store;
use MySqlMemory\Storage\Writer;
use MySqlMemory\Typing\Domain;
use SqlSemantics\Contract\SearchPath;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Operation;

/**
 * Builds the definition a table layout declares, and the rows of a table under it.
 *
 * The layout is declared as CREATE TABLE declares a table, against the other tables of the
 * server. Each row keeps the value of each column it had, stored into the column's new type as
 * an INSERT stores it: a value the type cannot hold is a warning, or an error under a strict
 * mode, at the number of the row in the order the table was read. NULL in a column that became
 * NOT NULL is ER_INVALID_USE_OF_NULL under a strict mode, else the implicit default with
 * WARN_DATA_TRUNCATED. A new column takes its default, and a new AUTO_INCREMENT column numbers
 * the rows. A row that repeats a unique key fails the statement. The rows are copied into a
 * temporary table the server names #sql-<process>_<connection>, its process 1 and the connection
 * id in hexadecimal, which an invalid JSON text names. The server copies the rows when
 * a column changes its type, other than a VARCHAR growing within the same length size or an
 * ENUM or SET gaining members at its end, and otherwise changes the table in place, which
 * affects no rows. Every rule was verified on a live 8.4 server.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-table.html,
 * https://dev.mysql.com/doc/refman/8.4/en/innodb-online-ddl-operations.html.
 *
 * @visibility MySqlMemory
 */
final class TableRebuild
{
    /**
     * @param Session $session The session changing the table
     * @param Context $context The statement
     * @param Connection $connection The connection, to compile defaults
     */
    public function __construct(public readonly Session $session, public readonly Context $context, public readonly Connection $connection)
    {
    }

    /**
     * Answers the definition a layout declares, its keys checked and duplicated keys warned of.
     *
     * @param list<Table> $declarations The tables the definition is declared against
     * @param bool $warn Whether a key that duplicates another is warned of
     *
     * @throws SqlError When the definition is invalid
     */
    public function definition(TableLayout $layout, array $declarations, bool $warn = true): TableDefinition
    {
        $create = $layout->statement();
        $schemaName = $layout->name->schema->value ?? $this->session->variables->database;
        $schema = $this->session->instance->dictionary->schema($schemaName);
        if ($schema === null) {
            throw ErrorCode::BadDatabase->error($schemaName);
        }
        $database = $this->session->variables->database;
        $operation = new Operation($this->session->semantics()->context($declarations, true, $database === '' ? null : new SearchPath($database), $this->session->resolution()), $create);
        $planner = new Planner($create, $operation->facts, $this->session->settings(), $this->connection, $this->session->instance->dictionary);
        $command = new CreateTableCommand();
        $definition = $command->primaryNotNull((new Definitions($planner, $schema->collation))->table($create, $operation->declarations()[0], $schemaName));
        foreach ($warn ? $command->duplicates($definition->keys) : [] as $duplicate) {
            $this->context->warning(ErrorCode::DuplicateIndex, $duplicate->name, $schemaName . '.' . $definition->name);
        }
        $columns = [];
        foreach ($definition->columns as $column) {
            $columns[] = isset($layout->undefaulted[mb_strtolower($column->name)])
                ? new ColumnDefinition($column->name, $column->domain, Fill::none(), $column->autoIncrement, $column->onUpdateNow, $column->generated, $column->invisible, $column->declaration, $column->comment)
                : $column;
        }

        return new TableDefinition($definition->schema, $definition->name, $columns, $definition->keys, $definition->declaration, $definition->engine, $definition->collation, $definition->temporary, $definition->comment, $definition->statement);
    }

    /**
     * Answers the rows of a table under a new definition.
     *
     * @param list<int|null> $origins The old position of each new column; null for a new column
     *
     * @throws SqlError When a value cannot be stored or a row repeats a unique key
     */
    public function rows(StoredTable $old, TableDefinition $definition, array $origins): Heap
    {
        $context = $this->context;
        $strict = $context->strict;
        $context->strict = $context->modes->strict();
        try {
            $heap = new Heap();
            $target = new StoredTable($definition, $heap);
            $writer = new Writer($target, $context);
            $automatic = $definition->autoIncrementColumn();
            if ($automatic !== null && $origins[$automatic] !== null && $origins[$automatic] === $old->definition->autoIncrementColumn()) {
                $heap->autoIncrement = $old->data->autoIncrement;
            }
            $frame = new Frame($context);
            $number = 0;
            $resequenced = [];
            foreach ((new ClusterOrder())->rows($old) as $row) {
                $number++;
                $store = new Store($context, $number, '#sql-1_' . dechex($this->session->id), true);
                $values = [];
                foreach ($definition->columns as $position => $column) {
                    $origin = $origins[$position] ?? null;
                    $values[] = $origin === null ? $this->fresh($column, $writer, $frame) : $this->kept($row[$origin], $old->definition->columns[$origin]->domain, $column, $store, $writer, $number);
                }
                [$values, $generated] = $writer->autoIncrement($values, $context->modes->has('NO_AUTO_VALUE_ON_ZERO'));
                $resequenced[$heap->insert($values)] = $generated !== null && $automatic !== null && $origins[$automatic] !== null;
            }
            $this->unique($writer, $resequenced);

            return $heap;
        } finally {
            $context->strict = $strict;
        }
    }

    /**
     * Refuses rows that repeat a unique key, as the server finds them when it builds the index: in the order of the key, the first of two equal rows.
     *
     * The keys are checked in the order the table holds them. A repeat that a column becoming
     * AUTO_INCREMENT caused by numbering a row is reported as resequencing.
     *
     * @param array<int, bool> $resequenced Whether each row, by number, was numbered by a column becoming AUTO_INCREMENT
     *
     * @throws SqlError When two rows repeat a unique key
     */
    public function unique(Writer $writer, array $resequenced): void
    {
        $table = $writer->table;
        foreach ($table->definition->keys as $key) {
            if (!$key->unique()) {
                continue;
            }
            $entries = [];
            foreach ($table->data->rows as $number => $row) {
                $entry = $writer->key($row, $key);
                if ($entry !== null) {
                    $entries[] = [$entry, $number];
                }
            }
            usort($entries, static fn (array $left, array $right): int => strcmp($left[0], $right[0]));
            foreach (array_keys($entries) as $index) {
                if ($index === 0 || $entries[$index][0] !== $entries[$index - 1][0]) {
                    continue;
                }
                $first = $entries[$index - 1][1];
                $row = $table->data->rows[$first];
                if (($resequenced[$first] ?? false) || ($resequenced[$entries[$index][1]] ?? false)) {
                    [$value, $name] = $writer->entry($row, $key);

                    throw new SqlError(ErrorCode::DuplicateEntry, "ALTER TABLE causes auto_increment resequencing, resulting in duplicate entry '" . $value . "' for key '" . $name . "'");
                }

                throw $writer->duplicate($row, $key);
            }
        }
    }

    /**
     * Answers the value a new column takes in a row: its default, NULL, or the implicit default of a NOT NULL column.
     */
    public function fresh(ColumnDefinition $column, Writer $writer, Frame $frame): int|float|string|null
    {
        [$has, $value] = $writer->default($column, $frame);
        if ($has || $column->nullable() || $column->autoIncrement) {
            return $value;
        }

        return $writer->implicit($column);
    }

    /**
     * Answers the value a column keeps in a row, stored into its new type.
     *
     * @throws SqlError When the value cannot be stored
     */
    public function kept(int|float|string|null $value, Domain $from, ColumnDefinition $column, Store $store, Writer $writer, int $number): int|float|string|null
    {
        $stored = $value === null || !self::changed($from, $column->domain) ? $value : $store->value($value, $from, $column);
        if ($stored !== null || $column->nullable() || $column->autoIncrement) {
            return $stored;
        }
        if ($this->context->strict) {
            throw ErrorCode::InvalidUseOfNull->error();
        }
        $this->context->diagnostics->warning(ErrorCode::DataTruncated, ErrorCode::DataTruncated->message($column->name, $number));

        return $writer->implicit($column);
    }

    /**
     * Tells whether a column changed how it stores its values: its type, length, scale, sign, collation or members.
     */
    public static function changed(Domain $from, Domain $to): bool
    {
        return $from->field !== $to->field || $from->length !== $to->length || $from->decimals !== $to->decimals || $from->unsigned !== $to->unsigned
            || $from->collation->name !== $to->collation->name || $from->members !== $to->members;
    }

    /**
     * Tells whether the server changes a column in place: the same storage, a VARCHAR that grows within the same length size, or an ENUM or SET that gains members at its end.
     */
    public static function inPlace(Domain $from, Domain $to): bool
    {
        if (!self::changed($from, $to)) {
            return true;
        }
        if ($from->field !== $to->field || $from->collation->name !== $to->collation->name || $from->unsigned !== $to->unsigned || $from->decimals !== $to->decimals) {
            return false;
        }
        if ($from->kind === Kind::String && ($from->field === Field::VarChar || $from->field === Field::VarString)) {
            return $to->length >= $from->length && ($from->byteLength() < 256) === ($to->byteLength() < 256);
        }
        if ($from->field === Field::Enum || $from->field === Field::Set) {
            $grown = array_slice($to->members, 0, count($from->members)) === $from->members;
            $limit = $from->field === Field::Enum ? 256 : 9;

            return $grown && (count($from->members) < $limit) === (count($to->members) < $limit);
        }

        return false;
    }
}
