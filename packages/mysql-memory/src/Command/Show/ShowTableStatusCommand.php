<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Show;

use MySqlMemory\Command\Command;
use MySqlMemory\Command\Show\Server\ServerCatalog;
use MySqlMemory\Dictionary\StoredTable;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\ColumnFlag;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use MySqlMemory\Value\Temporal;
use Override;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowTableStatus;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowWhere;
use SqlSemantics\Statement\Operation;

/**
 * Executes SHOW TABLE STATUS: one row for each base table of a database, in name order.
 *
 * The figures are those InnoDB reports for a table of one page: 16384 bytes of data and as
 * many for each secondary key and for the FTS_DOC_ID_INDEX of a table with a full-text key,
 * the rows it holds and their average length. They are read once and kept, as the data
 * dictionary keeps them for information_schema_stats_expiry seconds. The creation and update
 * times retain the DDL clock and the latest committed change, respectively. Update times use
 * the wall clock; cache expiry uses the session clock. Cached instants are displayed in the
 * current session time zone. An expiry of zero bypasses the cache without updating it.
 * LIKE matches the table names with regard to case. A WHERE condition that depends on the rows
 * makes the server read them from a derived table, whose column metadata differs (verified on a
 * live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-table-status.html.
 *
 * @visibility MySqlMemory
 */
final class ShowTableStatusCommand implements Command
{
    /**
     * The bytes of one InnoDB page.
     */
    public const PAGE = 16384;

    /**
     * Answers true.
     */
    #[Override]
    public function clearsDiagnostics(): bool
    {
        return true;
    }

    /**
     * Lists the tables.
     */
    #[Override]
    public function execute(\SqlSemantics\Statement\AnalyzedStatement $operation, Session $session, Context $context, Connection $connection): Reply
    {
        assert($operation instanceof Operation);
        $statement = $operation->statement;
        assert($statement instanceof ShowTableStatus);
        $schema = (new Inspection())->database($statement->database, $session);
        $tables = array_filter($schema->tables, static fn (StoredTable $table): bool => !$table->definition->temporary);
        ksort($tables, SORT_STRING);
        $rows = array_map(fn (StoredTable $table): array => $this->row($table, $context), array_values($tables));
        $listing = new Listing($session->settings()->legacy() ? $this->legacyHeadings() : $this->headings(false));
        $result = $listing->result($rows, $operation, $session, $context, $connection, $statement->filter, 0, 'utf8mb3_bin');
        $derived = !$session->settings()->legacy() && $statement->filter instanceof ShowWhere && ($listing->constantEmpty || $this->derived($statement->filter->condition));

        return $derived ? new \MySqlMemory\Result\ResultSet((new Listing($this->headings(true)))->sent([], $context)->columns, $result->rows, $result->warnings) : $result;
    }

    /**
     * Tells whether a filter reads stored dictionary fields instead of computed
     * status fields. These predicates expose the derived TABLES metadata in MySQL
     * 8.x and 9.1. A redundant NOT NULL test of a nonnullable field does not.
     */
    public function derived(\SqlSemantics\Statement\Node $node): bool
    {
        if ($node instanceof \SqlSemantics\Platform\MySql\Statement\Expression\NullTest && $node->negated && $node->operand instanceof \SqlSemantics\Platform\MySql\Statement\Name\ColumnUse && in_array(strtolower($node->operand->name->value), ['name', 'create_time'], true)) {
            return false;
        }
        if ($node instanceof \SqlSemantics\Platform\MySql\Statement\Name\ColumnUse) {
            return in_array(strtolower($node->name->value), ['name', 'row_format', 'create_time'], true);
        }
        $children = [];
        $properties = get_object_vars($node);
        array_walk_recursive($properties, static function ($value) use (&$children): void {
            if ($value instanceof \SqlSemantics\Statement\Node) {
                $children[] = $value;
            }
        });
        foreach ($children as $child) {
            if ($this->derived($child)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Answers the row of a table.
     *
     * @return list<int|string|null>
     */
    public function row(StoredTable $table, Context $context): array
    {
        $definition = $table->definition;
        $innodb = strcasecmp($definition->engine, 'InnoDB') === 0 || strcasecmp($definition->engine, 'innobase') === 0;
        $now = (int) floor($context->started);
        $expiry = in_array($context->modes->release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true) ? 0 : (int) $context->variables->read('information_schema_stats_expiry');
        if ($expiry === 0) {
            $statistics = $this->statistics($table, $innodb);
        } else {
            if ($table->statisticsRead === null || !isset($table->statistics['table']) || $now - $table->statisticsRead >= $expiry) {
                $table->statistics['table'] = $this->statistics($table, $innodb);
                $table->statisticsRead = $now;
            }
            $statistics = $table->statistics['table'];
        }
        $updated = $statistics[7] ?? null;
        if ($innodb && $context->modes->release === GrammarRelease::MySql5651) {
            $updated = null;
        }

        return [
            $definition->name,
            ServerCatalog::shared()->engine($definition->engine) ?? $definition->engine,
            10,
            $innodb ? ucfirst(strtolower($definition->rowFormat->value)) : 'Fixed',
            ...array_slice($statistics, 0, 7),
            Temporal::dateTime(...[...$context->local((float) $table->created), 0]),
            is_int($updated) ? Temporal::dateTime(...[...$context->local((float) $updated), 0]) : null,
            null,
            $definition->collation,
            null,
            (new ShowCreateTableCommand($context->modes->release))->rowFormat($definition, 'row_format='),
            (new ShowCreateTableCommand())->comment($definition),
        ];
    }

    /**
     * Answers the statistics of a table as the engine reports them now: its rows, their average length, the lengths of its data and keys, the free space, the next AUTO_INCREMENT value and the instant of its last committed change.
     *
     * The next AUTO_INCREMENT value is reported for a table with an AUTO_INCREMENT column, as for
     * a table the server has opened; one it has not opened since it was created reports none.
     *
     * @return list<int|string|null>
     */
    public function statistics(StoredTable $table, bool $innodb): array
    {
        $definition = $table->definition;
        $count = count($table->data->rows);
        $secondary = 0;
        $primary = (new Keys())->primary($definition);
        foreach ($definition->keys as $key) {
            $secondary += $key === $primary ? 0 : 1;
        }
        $secondary += array_filter($definition->keys, static fn ($key): bool => $key->kind === \MySqlMemory\Dictionary\KeyKind::FullText) === [] ? 0 : 1;
        $counter = $table->data->autoIncrement;

        return [
            $count,
            $innodb && $count > 0 ? intdiv(self::PAGE, $count) : 0,
            $innodb ? self::PAGE : 0,
            0,
            $innodb ? self::PAGE * $secondary : 0,
            0,
            $definition->autoIncrementColumn() === null ? null : $counter,
            $table->updated,
        ];
    }

    /**
     * Answers the columns of the rows, as read from INFORMATION_SCHEMA.TABLES or from a derived table of it.
     *
     * @return list<Heading>
     */
    public function headings(bool $derived): array
    {
        $table = 'TABLES';
        $schema = $derived ? 'information_schema' : '';
        $base = $derived ? 'tbl' : 'tables';
        $number = ColumnFlag::Numeric->value | ColumnFlag::Unsigned->value | ($derived ? ColumnFlag::Binary->value : 0);
        $headings = [
            Heading::text('Name', Field::VarString, 64, ColumnFlag::NotNull->value | ColumnFlag::Binary->value | ColumnFlag::NoDefaultValue->value | ($derived ? 16384 : 0), 0, 'Name', $table, $base, $schema),
            Heading::text('Engine', Field::VarString, 64, 0, $derived ? 31 : 0, 'Engine', $table),
            new Heading('Version', $derived ? Field::LongLong : Field::Long, 3, ColumnFlag::Numeric->value | ($derived ? ColumnFlag::Binary->value : 0), 0, false, 'Version', $table),
            Heading::text('Row_format', Field::String, 10, ColumnFlag::Binary->value | ColumnFlag::Enum->value, 0, 'Row_format', $table, $base, $schema),
        ];
        foreach (['Rows', 'Avg_row_length', 'Data_length', 'Max_data_length', 'Index_length', 'Data_free', 'Auto_increment'] as $name) {
            $headings[] = new Heading($name, Field::LongLong, 21, $number, 0, false, $name, $table);
        }
        $created = ColumnFlag::NotNull->value | ColumnFlag::Binary->value | ColumnFlag::NoDefaultValue->value;
        array_push(
            $headings,
            $derived ? Heading::text('Create_time', Field::Timestamp, 19, $created, 0, 'Create_time', $table, $base, $schema) : new Heading('Create_time', Field::Timestamp, 19, $created, 0, false, 'Create_time', $table, $base),
            $derived ? Heading::text('Update_time', Field::DateTime, 19, 0, 0, 'Update_time', $table) : new Heading('Update_time', Field::DateTime, 19, ColumnFlag::Binary->value, 0, false, 'Update_time', $table),
            $derived ? Heading::text('Check_time', Field::DateTime, 19, 0, 0, 'Check_time', $table) : new Heading('Check_time', Field::DateTime, 19, ColumnFlag::Binary->value, 0, false, 'Check_time', $table),
            Heading::text('Collation', Field::VarString, 64, ColumnFlag::NoDefaultValue->value | ($derived ? 16384 | ColumnFlag::UniqueKey->value : 0), 0, 'Collation', $table, $derived ? 'col' : 'collations', $schema),
            new Heading('Checksum', Field::LongLong, 21, ColumnFlag::Numeric->value | ($derived ? ColumnFlag::Binary->value : 0), 0, false, 'Checksum', $table),
            Heading::text('Create_options', Field::VarString, 256, 0, $derived ? 31 : 0, 'Create_options', $table),
            $derived ? Heading::text('Comment', Field::VarString, 2048, 0, 31, 'Comment', $table) : Heading::text('Comment', Field::Blob, 6144, ColumnFlag::Blob->value, 0, 'Comment', $table),
        );

        return $headings;
    }

    /**
     * Answers the INFORMATION_SCHEMA.TABLES fields reported by MySQL 5.6 and 5.7,
     * including with LIKE or WHERE. Verified against both native releases.
     *
     * @return list<Heading>
     */
    public function legacyHeadings(): array
    {
        $headings = [];
        $text = ['Name' => 64, 'Engine' => 64, 'Row_format' => 10, 'Collation' => 32, 'Create_options' => 255, 'Comment' => 2048];
        foreach (['Name', 'Engine', 'Version', 'Row_format', 'Rows', 'Avg_row_length', 'Data_length', 'Max_data_length', 'Index_length', 'Data_free', 'Auto_increment', 'Create_time', 'Update_time', 'Check_time', 'Collation', 'Checksum', 'Create_options', 'Comment'] as $name) {
            $original = in_array($name, ['Name', 'Rows', 'Collation', 'Comment'], true) ? 'TABLE_' . strtoupper($name) : strtoupper($name);
            if (isset($text[$name])) {
                $headings[] = Heading::text($name, Field::VarString, $text[$name], in_array($name, ['Name', 'Comment'], true) ? ColumnFlag::NotNull->value : 0, 0, $original, 'TABLES', 'TABLES', 'information_schema');
            } else {
                $temporal = in_array($name, ['Create_time', 'Update_time', 'Check_time'], true);
                $headings[] = new Heading($name, $temporal ? Field::DateTime : Field::LongLong, $temporal ? 19 : 21, $temporal ? ColumnFlag::Binary->value : ColumnFlag::Numeric->value | ColumnFlag::Unsigned->value, 0, false, $original, 'TABLES', 'TABLES', 'information_schema');
            }
        }

        return $headings;
    }
}
