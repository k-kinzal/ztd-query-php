<?php

declare(strict_types=1);

namespace MySqlMemory\System\Schema;

use MySqlMemory\Command\Show\Keys;
use MySqlMemory\Command\Show\ShowKeysCommand;
use MySqlMemory\Dictionary\KeyKind;
use MySqlMemory\Dictionary\StoredTable;
use MySqlMemory\System\Listed;
use MySqlMemory\System\Reading;
use MySqlMemory\System\SystemRows;
use Override;
use SqlSemantics\Contract\GrammarRelease;

/**
 * The rows of INFORMATION_SCHEMA.STATISTICS: one for each column of each index of each base table.
 *
 * MySQL 8.4 lists the tables by name and the indexes of each by name without regard to case;
 * 8.0 and 9.1 list the tables in the order they were created and the indexes in the order the
 * server keeps them, 5.6 and 5.7 the tables by name and the indexes in that order. The columns
 * of an index come by their position in it. The figures are those SHOW INDEX reports, read once and kept as the data dictionary
 * keeps them for information_schema_stats_expiry seconds (verified on live 5.7.44, 8.0.44, 8.4.7 and 9.1.0 servers).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/information-schema-statistics-table.html.
 *
 * @visibility MySqlMemory
 */
final class Statistics implements SystemRows
{
    /**
     * Answers a row for each column of each index.
     */
    #[Override]
    public function rows(Reading $reading): array
    {
        $show = new ShowKeysCommand();
        $rows = [];
        $named = $reading->release === GrammarRelease::MySql847 || !$reading->dictionary();
        foreach (Listed::schemas($reading) as $schema) {
            foreach (Listed::of($schema, $reading, !$named) as $name => $table) {
                if (!$table instanceof StoredTable) {
                    continue;
                }
                $definition = $table->definition;
                $keys = (new Keys())->ordered($definition);
                if ($named && $reading->dictionary()) {
                    usort($keys, static fn ($left, $right): int => strcasecmp($left->name, $right->name));
                }
                foreach ($keys as $key) {
                    foreach ($key->columns as $sequence => $position) {
                        $table->statistics['index ' . $key->name . ' ' . $sequence] ??= [$show->cardinality($table, $key, $sequence)];
                        $column = $definition->columns[$position];
                        $rows[] = [
                            'TABLE_CATALOG' => 'def',
                            'TABLE_SCHEMA' => $schema->name,
                            'TABLE_NAME' => $name,
                            'NON_UNIQUE' => $key->unique() ? 0 : 1,
                            'INDEX_SCHEMA' => $schema->name,
                            'INDEX_NAME' => $key->name,
                            'SEQ_IN_INDEX' => $sequence + 1,
                            'COLUMN_NAME' => $column->name,
                            'COLLATION' => $key->kind === KeyKind::FullText ? null : (($key->descending[$sequence] ?? false) ? 'D' : 'A'),
                            'CARDINALITY' => $table->statistics['index ' . $key->name . ' ' . $sequence][0],
                            'SUB_PART' => $key->kind === KeyKind::Spatial ? 32 : ($key->prefixes[$sequence] ?? null),
                            'PACKED' => null,
                            'NULLABLE' => $column->nullable() ? 'YES' : '',
                            'INDEX_TYPE' => $show->type($key->kind, $definition->engine),
                            'COMMENT' => '',
                            'INDEX_COMMENT' => '',
                            'IS_VISIBLE' => 'YES',
                            'EXPRESSION' => null,
                        ];
                    }
                }
            }
        }

        return $rows;
    }
}
