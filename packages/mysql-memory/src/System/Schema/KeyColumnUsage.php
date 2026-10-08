<?php

declare(strict_types=1);

namespace MySqlMemory\System\Schema;

use MySqlMemory\Dictionary\StoredTable;
use MySqlMemory\System\Listed;
use MySqlMemory\System\Reading;
use MySqlMemory\System\SystemRows;
use Override;

/**
 * The rows of INFORMATION_SCHEMA.KEY_COLUMN_USAGE: one for each column of each primary key, unique key and foreign key of each base table.
 *
 * The keys of a table come first, by name without regard to case, then its foreign keys, by
 * name without regard to case. A column of a foreign key names the column it references and
 * its position in the referenced key (verified on a live 8.4.7 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/information-schema-key-column-usage-table.html.
 *
 * @visibility MySqlMemory
 */
final class KeyColumnUsage implements SystemRows
{
    /**
     * Answers a row for each column of each key and foreign key.
     */
    #[Override]
    public function rows(Reading $reading): array
    {
        $rows = [];
        foreach (Listed::schemas($reading) as $schema) {
            foreach (Listed::of($schema, $reading) as $name => $table) {
                if (!$table instanceof StoredTable) {
                    continue;
                }
                $definition = $table->definition;
                $base = ['CONSTRAINT_CATALOG' => 'def', 'CONSTRAINT_SCHEMA' => $schema->name];
                $located = ['TABLE_CATALOG' => 'def', 'TABLE_SCHEMA' => $schema->name, 'TABLE_NAME' => $name];
                foreach (Constraints::keys($definition, $reading->dictionary()) as $key) {
                    foreach ($key->columns as $sequence => $position) {
                        $rows[] = $base + ['CONSTRAINT_NAME' => $key->name] + $located + ['COLUMN_NAME' => $definition->columns[$position]->name, 'ORDINAL_POSITION' => $sequence + 1];
                    }
                }
                foreach (Constraints::foreign($definition) as $foreign) {
                    foreach ($foreign->columns as $sequence => $position) {
                        $rows[] = $base + ['CONSTRAINT_NAME' => $foreign->name] + $located + [
                            'COLUMN_NAME' => $definition->columns[$position]->name,
                            'ORDINAL_POSITION' => $sequence + 1,
                            'POSITION_IN_UNIQUE_CONSTRAINT' => $sequence + 1,
                            'REFERENCED_TABLE_SCHEMA' => $foreign->parentSchema,
                            'REFERENCED_TABLE_NAME' => $foreign->parentTable,
                            'REFERENCED_COLUMN_NAME' => $foreign->parentColumns[$sequence] ?? null,
                        ];
                    }
                }
            }
        }

        return $rows;
    }
}
