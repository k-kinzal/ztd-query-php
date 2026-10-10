<?php

declare(strict_types=1);

namespace MySqlMemory\Dictionary\Cache;

/**
 * The names of non-temporary tables whose handles remain open in the server.
 *
 * This state is separate from the catalog: creating a table does not open a query handle,
 * and flushing a handle does not remove the definition or rows. Multiple uses share one
 * reported name. Lock and handler counts belong to the sessions that retain those uses.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-open-tables.html.
 *
 * @visibility MySqlMemory
 */
final class TableCache
{
    /**
     * @var array<string, array{string, string}> The database and table names, keyed by identity
     */
    private array $opened = [];

    /**
     * Retains the handle of a table, without counting repeated opens as separate names.
     */
    public function open(string $schema, string $table): void
    {
        $this->opened[$schema . "\0" . $table] = [$schema, $table];
    }

    /**
     * Closes one table, every table of a database, or every handle when no name is supplied.
     */
    public function close(?string $schema = null, ?string $table = null): void
    {
        foreach ($this->opened as $key => [$database, $name]) {
            if (($schema === null || $schema === $database) && ($table === null || $table === $name)) {
                unset($this->opened[$key]);
            }
        }
    }

    /**
     * Answers the distinct database and table names with retained handles.
     *
     * @return list<array{string, string}>
     */
    public function names(): array
    {
        return array_values($this->opened);
    }
}
