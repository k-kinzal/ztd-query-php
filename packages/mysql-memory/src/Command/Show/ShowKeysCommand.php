<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Show;

use MySqlMemory\Command\Command;
use MySqlMemory\Dictionary\Key;
use MySqlMemory\Dictionary\KeyKind;
use MySqlMemory\Dictionary\StoredTable;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\ColumnFlag;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use MySqlMemory\Value\Encoding;
use MySqlMemory\Value\Order;
use Override;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowKeys;
use SqlSemantics\Statement\Operation;

/**
 * Executes SHOW INDEX (or KEYS, INDEXES): one row for each column of each key of a table, the keys in the order the server keeps them.
 *
 * Collation is A for an ascending column, D for a descending one and NULL for a full-text key.
 * Sub_part is the length of a prefix; a spatial key indexes 32 bytes. Cardinality is the
 * estimate of the engine, read once and kept as the data dictionary keeps it for
 * information_schema_stats_expiry seconds. Null is YES for a
 * column that can hold NULL. The rows are read from INFORMATION_SCHEMA.SHOW_STATISTICS, which
 * the column metadata names (verified on a live 8.4 server); MySQL 5.6 and 5.7 read them from
 * INFORMATION_SCHEMA.STATISTICS (see legacy()).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-index.html.
 *
 * @visibility MySqlMemory
 */
final class ShowKeysCommand implements Command
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
     * Lists the key columns.
     */
    #[Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        assert($statement instanceof ShowKeys);
        $stored = (new Inspection())->table($statement->table, $statement->database, $session);
        $definition = $stored->definition;
        $rows = [];
        $release = $session->settings()->release();
        $legacy = $session->settings()->legacy();
        foreach ((new Keys())->ordered($definition) as $key) {
            foreach ($key->columns as $sequence => $position) {
                $stored->statistics['index ' . $key->name . ' ' . $sequence] ??= [$this->cardinality($stored, $key, $sequence, $release !== \SqlSemantics\Contract\GrammarRelease::MySql5651)];
                $cardinality = $stored->statistics['index ' . $key->name . ' ' . $sequence][0];
                $column = $definition->columns[$position];
                $rows[] = [
                    $definition->name,
                    $key->unique() ? 0 : 1,
                    $key->name,
                    $sequence + 1,
                    $column->name,
                    $key->kind === KeyKind::FullText ? null : (($key->descending[$sequence] ?? false) ? 'D' : 'A'),
                    $cardinality,
                    $key->kind === KeyKind::Spatial ? 32 : ($key->prefixes[$sequence] ?? null),
                    null,
                    $column->nullable() ? 'YES' : '',
                    $this->type($key->kind, $definition->engine),
                    '',
                    '',
                    ...($legacy ? [] : ['YES', null]),
                ];
            }
        }
        $headings = $legacy ? $this->legacy() : ($definition->temporary ? $this->temporary() : $this->headings());

        return (new Listing($headings))->result($rows, $operation, $session, $context, $connection, $statement->filter);
    }

    /**
     * Answers the cardinality of the first columns of a key, up to one of them, as the engine estimates it.
     *
     * InnoDB counts the distinct values of those columns, NULL equal to NULL, as it does exactly
     * for a table of few pages; MySQL 5.6 counts whole values where the key takes a prefix (verified
     * on a live 5.6.51 server).
     *
     * @param bool $prefixed Whether the values are cut to the prefixes of the key MyISAM counts the rows for the whole of a unique key of NOT NULL
     * columns and reports nothing else.
     */
    public function cardinality(StoredTable $stored, Key $key, int $sequence, bool $prefixed = true): ?int
    {
        $definition = $stored->definition;
        if (strcasecmp($definition->engine, 'MyISAM') === 0) {
            return $key->unique() && (new Keys())->notNull($key, $definition) && $sequence === count($key->columns) - 1 ? count($stored->data->rows) : null;
        }
        if (strcasecmp($definition->engine, 'InnoDB') !== 0 && strcasecmp($definition->engine, 'innobase') !== 0) {
            return null;
        }
        $distinct = [];
        foreach ($stored->data->rows as $row) {
            $text = '';
            foreach (array_slice($key->columns, 0, $sequence + 1) as $index => $position) {
                $value = $row[$position] ?? null;
                $domain = $definition->columns[$position]->domain;
                $prefix = $prefixed ? $key->prefixes[$index] ?? null : null;
                if ($value !== null && $prefix !== null && $domain->kind === Kind::String) {
                    $value = Encoding::slice((string) $value, 0, $prefix, $domain->collation->charset);
                }
                $text .= ($value === null ? "\1" : "\0" . Order::key($value, $domain)) . "\0";
            }
            $distinct[$text] = true;
        }

        return count($distinct);
    }

    /**
     * Answers the index type of a key: FULLTEXT, SPATIAL, HASH for a key of a MEMORY table, else BTREE.
     */
    public function type(KeyKind $kind, string $engine): string
    {
        return match ($kind) {
            KeyKind::FullText => 'FULLTEXT',
            KeyKind::Spatial => 'SPATIAL',
            KeyKind::Primary, KeyKind::Unique, KeyKind::Index => strcasecmp($engine, 'MEMORY') === 0 || strcasecmp($engine, 'HEAP') === 0 ? 'HASH' : 'BTREE',
        };
    }

    /**
     * Answers the columns of the rows that list the indexes of a temporary table, which the server reads apart from INFORMATION_SCHEMA (verified on a live 8.4 server).
     *
     * @return list<Heading>
     */
    public function temporary(): array
    {
        $table = 'TMP_TABLE_KEYS';
        $required = ColumnFlag::NotNull->value;
        $number = ColumnFlag::Numeric->value;

        return [
            Heading::text('Table', Field::VarString, 64, $required, 0, 'Table', $table),
            new Heading('Non_unique', Field::LongLong, 2, $required | $number, 0, false, 'Non_unique', $table),
            Heading::text('Key_name', Field::VarString, 64, $required, 0, 'Key_name', $table),
            new Heading('Seq_in_index', Field::LongLong, 3, $required | $number, 0, false, 'Seq_in_index', $table),
            Heading::text('Column_name', Field::VarString, 64, 0, 0, 'Column_name', $table),
            Heading::text('Collation', Field::VarString, 1, 0, 0, 'Collation', $table),
            new Heading('Cardinality', Field::LongLong, 22, $number, 0, false, 'Cardinality', $table),
            new Heading('Sub_part', Field::LongLong, 4, $number, 0, false, 'Sub_part', $table),
            Heading::text('Packed', Field::VarString, 10, 0, 0, 'Packed', $table),
            Heading::text('Null', Field::VarString, 3, $required, 0, 'Null', $table),
            Heading::text('Index_type', Field::VarString, 16, $required, 0, 'Index_type', $table),
            Heading::text('Comment', Field::VarString, 16, 0, 0, 'Comment', $table),
            Heading::text('Index_comment', Field::VarString, 1024, $required, 0, 'Index_comment', $table),
            Heading::text('Visible', Field::VarString, 4, 0, 0, 'Visible', $table),
            Heading::text('Expression', Field::Blob, 4294967295, ColumnFlag::Blob->value | ColumnFlag::Binary->value, 0, 'Expression', $table),
        ];
    }

    /**
     * Answers the columns of the rows in MySQL 5.6 and 5.7, which read them from INFORMATION_SCHEMA.STATISTICS and have no Visible and Expression columns (verified on live 5.6.51 and 5.7.44 servers).
     *
     * @return list<Heading>
     */
    public function legacy(): array
    {
        $table = 'STATISTICS';
        $required = ColumnFlag::NotNull->value;

        return [
            Heading::text('Table', Field::VarString, 64, $required, 0, 'Table', $table),
            new Heading('Non_unique', Field::LongLong, 1, $required, 0, false, 'Non_unique', $table),
            Heading::text('Key_name', Field::VarString, 64, $required, 0, 'Key_name', $table),
            new Heading('Seq_in_index', Field::LongLong, 2, $required, 0, false, 'Seq_in_index', $table),
            Heading::text('Column_name', Field::VarString, 64, $required, 0, 'Column_name', $table),
            Heading::text('Collation', Field::VarString, 1, 0, 0, 'Collation', $table),
            new Heading('Cardinality', Field::LongLong, 21, 0, 0, false, 'Cardinality', $table),
            new Heading('Sub_part', Field::LongLong, 3, 0, 0, false, 'Sub_part', $table),
            Heading::text('Packed', Field::VarString, 10, 0, 0, 'Packed', $table),
            Heading::text('Null', Field::VarString, 3, $required, 0, 'Null', $table),
            Heading::text('Index_type', Field::VarString, 16, $required, 0, 'Index_type', $table),
            Heading::text('Comment', Field::VarString, 16, 0, 0, 'Comment', $table),
            Heading::text('Index_comment', Field::VarString, 1024, $required, 0, 'Index_comment', $table),
        ];
    }

    /**
     * Answers the columns of the rows.
     *
     * @return list<Heading>
     */
    public function headings(): array
    {
        $table = 'SHOW_STATISTICS';

        return [
            Heading::text('Table', Field::VarString, 64, ColumnFlag::NotNull->value | ColumnFlag::Binary->value | ColumnFlag::NoDefaultValue->value, 0, 'Table', $table, 'tables'),
            new Heading('Non_unique', Field::Long, 2, ColumnFlag::NotNull->value | ColumnFlag::Numeric->value, 0, false, 'Non_unique', $table),
            Heading::text('Key_name', Field::VarString, 64, 0, 0, 'Key_name', $table),
            new Heading('Seq_in_index', Field::Long, 10, ColumnFlag::NotNull->value | ColumnFlag::Unsigned->value | ColumnFlag::NoDefaultValue->value | ColumnFlag::Numeric->value, 0, false, 'Seq_in_index', $table, 'index_column_usage'),
            Heading::text('Column_name', Field::VarString, 64, 0, 0, 'Column_name', $table),
            Heading::text('Collation', Field::VarString, 1, 0, 0, 'Collation', $table),
            new Heading('Cardinality', Field::LongLong, 21, ColumnFlag::Numeric->value, 0, false, 'Cardinality', $table),
            new Heading('Sub_part', Field::LongLong, 21, ColumnFlag::Numeric->value, 0, false, 'Sub_part', $table),
            new Heading('Packed', Field::Null, 0, ColumnFlag::Binary->value | ColumnFlag::Numeric->value),
            Heading::text('Null', Field::VarString, 3, ColumnFlag::NotNull->value, 0, 'Null', $table),
            Heading::text('Index_type', Field::VarString, 11, ColumnFlag::NotNull->value | ColumnFlag::Binary->value, 0, 'Index_type', $table),
            Heading::text('Comment', Field::VarString, 8, ColumnFlag::NotNull->value, 0, 'Comment', $table),
            Heading::text('Index_comment', Field::VarString, 2048, ColumnFlag::NotNull->value | ColumnFlag::Binary->value | ColumnFlag::NoDefaultValue->value, 0, 'Index_comment', $table, 'indexes'),
            Heading::text('Visible', Field::VarString, 3, ColumnFlag::NotNull->value, 0, 'Visible', $table),
            Heading::text('Expression', Field::Blob, 4294967295, ColumnFlag::Blob->value | ColumnFlag::Binary->value, 0, 'Expression', $table),
        ];
    }
}
