<?php

declare(strict_types=1);

namespace MySqlMemory\System\Schema;

use MySqlMemory\Dictionary\KeyKind;
use MySqlMemory\Dictionary\StoredTable;
use MySqlMemory\System\Listed;
use MySqlMemory\System\Reading;
use MySqlMemory\System\SystemRows;
use Override;

/**
 * The rows of INFORMATION_SCHEMA.TABLE_CONSTRAINTS: one for each primary key, unique key, foreign key and CHECK constraint of each base table.
 *
 * The keys of a table come first, then its foreign keys, then its CHECK constraints, each kind
 * by name without regard to case. ENFORCED is NO only for a CHECK constraint declared NOT
 * ENFORCED (verified on a live 8.4.7 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/information-schema-table-constraints-table.html.
 *
 * @visibility MySqlMemory
 */
final class TableConstraints implements SystemRows
{
    /**
     * Answers a row for each constraint.
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
                $row = static fn (string $constraint, string $type, string $enforced): array => ['CONSTRAINT_CATALOG' => 'def', 'CONSTRAINT_SCHEMA' => $schema->name, 'CONSTRAINT_NAME' => $constraint, 'TABLE_SCHEMA' => $schema->name, 'TABLE_NAME' => $name, 'CONSTRAINT_TYPE' => $type, 'ENFORCED' => $enforced];
                foreach (Constraints::keys($definition, $reading->dictionary()) as $key) {
                    $rows[] = $row($key->name, $key->kind === KeyKind::Primary ? 'PRIMARY KEY' : 'UNIQUE', 'YES');
                }
                foreach (Constraints::foreign($definition) as $foreign) {
                    $rows[] = $row($foreign->name, 'FOREIGN KEY', 'YES');
                }
                foreach (Constraints::checks($definition) as $check) {
                    $rows[] = $row($check->name, 'CHECK', $check->enforced ? 'YES' : 'NO');
                }
            }
        }

        return $rows;
    }
}
