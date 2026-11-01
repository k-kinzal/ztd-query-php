<?php

declare(strict_types=1);

namespace MySqlMemory\System\Schema;

use MySqlMemory\Dictionary\StoredTable;
use MySqlMemory\System\Listed;
use MySqlMemory\System\Reading;
use MySqlMemory\System\SystemRows;
use Override;

/**
 * The rows of INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS: one for each foreign key of each base table.
 *
 * The foreign keys of a table are listed in the order the table declares them, by name in 5.6
 * and 5.7. A key names the
 * primary or unique key of the parent table it references; an action not written is NO ACTION
 * in MySQL 8.0 and later and RESTRICT in 5.6 and 5.7, and MATCH_OPTION is NONE (verified on live
 * 5.7.44 and 8.4.7 servers).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/information-schema-referential-constraints-table.html.
 *
 * @visibility MySqlMemory
 */
final class ReferentialConstraints implements SystemRows
{
    /**
     * Answers a row for each foreign key.
     */
    #[Override]
    public function rows(Reading $reading): array
    {
        $unwritten = $reading->dictionary() ? 'NO ACTION' : 'RESTRICT';
        $rows = [];
        foreach (Listed::schemas($reading) as $schema) {
            foreach (Listed::of($schema, $reading) as $name => $table) {
                if (!$table instanceof StoredTable) {
                    continue;
                }
                foreach ($reading->dictionary() ? $table->definition->foreignKeys : Constraints::foreign($table->definition) as $foreign) {
                    $rows[] = [
                        'CONSTRAINT_CATALOG' => 'def',
                        'CONSTRAINT_SCHEMA' => $schema->name,
                        'CONSTRAINT_NAME' => $foreign->name,
                        'UNIQUE_CONSTRAINT_CATALOG' => 'def',
                        'UNIQUE_CONSTRAINT_SCHEMA' => $foreign->parentSchema,
                        'UNIQUE_CONSTRAINT_NAME' => Constraints::referenced($foreign, $reading->instance->dictionary),
                        'MATCH_OPTION' => 'NONE',
                        'UPDATE_RULE' => $foreign->onUpdate->value ?? $unwritten,
                        'DELETE_RULE' => $foreign->onDelete->value ?? $unwritten,
                        'TABLE_NAME' => $name,
                        'REFERENCED_TABLE_NAME' => $foreign->parentTable,
                    ];
                }
            }
        }

        return $rows;
    }
}
