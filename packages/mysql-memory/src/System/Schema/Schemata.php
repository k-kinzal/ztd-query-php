<?php

declare(strict_types=1);

namespace MySqlMemory\System\Schema;

use MySqlMemory\System\Listed;
use MySqlMemory\System\Reading;
use MySqlMemory\System\SystemRows;
use Override;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

/**
 * The rows of INFORMATION_SCHEMA.SCHEMATA: one for each database, with its default character set and collation.
 *
 * MySQL 8.4 and 9.1 list the databases by name, 8.0 in the order they were created, the
 * system databases first; 5.6 and 5.7 list INFORMATION_SCHEMA first, then the others by name
 * (verified on live 5.6.51, 5.7.44, 8.0.44, 8.4.7 and 9.1.0 servers). No database has
 * an SQL path, and none is encrypted by default.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/information-schema-schemata-table.html.
 *
 * @visibility MySqlMemory
 */
final class Schemata implements SystemRows
{
    /**
     * Answers a row for each database.
     */
    #[Override]
    public function rows(Reading $reading): array
    {
        $rows = [];
        foreach (Listed::schemas($reading, $reading->release === GrammarRelease::MySql8044) as $schema) {
            $name = $schema->name;
            $collation = Collation::named($schema->collation) ?? Collation::known('utf8mb4_0900_ai_ci');
            $rows[] = [
                'CATALOG_NAME' => 'def',
                'SCHEMA_NAME' => $name,
                'DEFAULT_CHARACTER_SET_NAME' => $collation->charset->nameIn($reading->release),
                'DEFAULT_COLLATION_NAME' => $collation->nameIn($reading->release),
                'SQL_PATH' => null,
                'DEFAULT_ENCRYPTION' => 'NO',
            ];
        }

        return $rows;
    }
}
