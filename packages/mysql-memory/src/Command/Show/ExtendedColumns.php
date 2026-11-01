<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Show;

use MySqlMemory\Dictionary\TableDefinition;

/**
 * Lists the InnoDB system fields exposed by SHOW EXTENDED COLUMNS.
 * An explicit or non-null unique key supplies the row identity; temporary
 * tables do not expose these fields. Verified on MySQL 8.0, 8.4 and 9.1.
 * @visibility MySqlMemory
 */
final class ExtendedColumns
{
    /**
     * Adds hidden fields only for an explicit EXTENDED listing.
     *
     * @return list<list<string|null>>
     */
    public function listing(\SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowColumns|\SqlSemantics\Platform\MySql\Statement\Utility\Explain\DescribeTable $statement, TableDefinition $table): array
    {
        if (!$statement instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowColumns || $statement->listing === null || $statement->listing === \SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowListing::Full) {
            return [];
        }
        return $this->rows($table, $statement->listing->full());
    }

    /**
     * Returns storage fields in their reported order, before the SHOW filter.
     *
     * @return list<list<string|null>>
     */
    public function rows(TableDefinition $table, bool $full): array
    {
        if ($table->temporary || !in_array(strtolower($table->engine), ['innodb', 'innobase'], true)) {
            return [];
        }
        $names = (new Keys())->primary($table) === null ? ['DB_ROW_ID', 'DB_TRX_ID', 'DB_ROLL_PTR'] : ['DB_TRX_ID', 'DB_ROLL_PTR'];
        return array_map(static fn (string $name): array => [$name, '', ...($full ? [null] : []), 'NO', '', null, '', ...($full ? [ShowColumnsCommand::PRIVILEGES, ''] : [])], $names);
    }
}
