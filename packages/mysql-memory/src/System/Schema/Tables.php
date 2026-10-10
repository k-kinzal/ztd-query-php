<?php

declare(strict_types=1);

namespace MySqlMemory\System\Schema;

use MySqlMemory\Command\Show\ShowTableStatusCommand;
use MySqlMemory\Dictionary\StoredTable;
use MySqlMemory\Dictionary\View;
use MySqlMemory\System\Listed;
use MySqlMemory\System\Reading;
use MySqlMemory\System\SystemRows;
use Override;
use SqlSemantics\Platform\MySql\Statement\Table\Catalog\SystemTable;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

/**
 * The rows of INFORMATION_SCHEMA.TABLES: one for each table and view of each database.
 *
 * A base table reports the figures SHOW TABLE STATUS reports, read once and kept as the data
 * dictionary keeps them for information_schema_stats_expiry seconds. A view reports only its
 * name, its creation time and the comment VIEW. A system table reports its engine, row format,
 * collation, options and comment, and no row or byte; its creation time is the time the server
 * started (verified on live 5.7.44 and 8.4.7 servers).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/information-schema-tables-table.html.
 *
 * @visibility MySqlMemory
 */
final class Tables implements SystemRows
{
    /**
     * Answers a row for each table and view.
     */
    #[Override]
    public function rows(Reading $reading): array
    {
        $now = date('Y-m-d H:i:s', (int) floor($reading->connection->context->started));
        $started = date('Y-m-d H:i:s', (int) floor($reading->instance->started));
        $rows = [];
        foreach (Listed::schemas($reading) as $schema) {
            foreach (Listed::of($schema, $reading) as $name => $object) {
                $row = ['TABLE_CATALOG' => 'def', 'TABLE_SCHEMA' => $schema->name, 'TABLE_NAME' => $name];
                $rows[] = $row + match (true) {
                    $object instanceof StoredTable => $this->table($object, $reading),
                    $object instanceof View => $this->view($now, $reading),
                    $object instanceof SystemTable => $this->system($object, $started, $reading),
                };
            }
        }

        return $rows;
    }

    /**
     * Answers the columns of the row of a base table.
     *
     * @return array<string, int|string|null>
     */
    public function table(StoredTable $table, Reading $reading): array
    {
        [, $engine, $version, $format, $count, $average, $data, $maximum, $index, $free, $increment, $created, $updated, $checked, $collation, $checksum, $options, $comment] = (new ShowTableStatusCommand())->row($table, $reading->connection->context);
        $collation = is_string($collation) ? Collation::named($collation)?->nameIn($reading->release) ?? $collation : $collation;

        return [
            'TABLE_TYPE' => 'BASE TABLE',
            'ENGINE' => $engine,
            'VERSION' => $version,
            'ROW_FORMAT' => $format,
            'TABLE_ROWS' => $count,
            'AVG_ROW_LENGTH' => $average,
            'DATA_LENGTH' => $data,
            'MAX_DATA_LENGTH' => $maximum,
            'INDEX_LENGTH' => $index,
            'DATA_FREE' => $free,
            'AUTO_INCREMENT' => $increment,
            'CREATE_TIME' => $reading->dictionary() ? gmdate('Y-m-d H:i:s', $table->created) : $created,
            'UPDATE_TIME' => $updated,
            'CHECK_TIME' => $checked,
            'TABLE_COLLATION' => $collation,
            'CHECKSUM' => $checksum,
            'CREATE_OPTIONS' => $options,
            'TABLE_COMMENT' => $comment,
        ];
    }

    /**
     * Answers the columns of the row of a view.
     *
     * @return array<string, int|string|null>
     */
    public function view(string $now, Reading $reading): array
    {
        return ['TABLE_TYPE' => 'VIEW', 'CREATE_TIME' => $reading->dictionary() ? $now : null, 'TABLE_COMMENT' => 'VIEW'];
    }

    /**
     * Answers the columns of the row of a system table.
     *
     * @return array<string, int|string|null>
     */
    public function system(SystemTable $table, string $started, Reading $reading): array
    {
        $view = $table->engine === null;

        return [
            'TABLE_TYPE' => $table->type,
            'ENGINE' => $table->engine,
            'VERSION' => $table->version,
            'ROW_FORMAT' => $table->rowFormat,
            'TABLE_ROWS' => 0,
            'AVG_ROW_LENGTH' => 0,
            'DATA_LENGTH' => 0,
            'MAX_DATA_LENGTH' => 0,
            'INDEX_LENGTH' => 0,
            'DATA_FREE' => 0,
            'CREATE_TIME' => $view || $reading->dictionary() ? $started : null,
            'TABLE_COLLATION' => $table->collation,
            'CREATE_OPTIONS' => $table->options,
            'TABLE_COMMENT' => $table->comment,
        ];
    }
}
