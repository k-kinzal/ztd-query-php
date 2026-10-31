<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Access;

/**
 * A table HANDLER ... OPEN opened: the table it reads and where its cursor stands.
 *
 * The cursor stands at a row of the order last read, a scan or an index; a read in the other
 * order, or a first read, starts that order afresh.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/handler.html.
 *
 * @visibility MySqlMemory
 */
final class Handler
{
    /**
     * The order the cursor stands in: null for the natural order, else the lowercase index name.
     */
    public ?string $order = null;

    /**
     * Whether a read has placed the cursor.
     */
    public bool $placed = false;

    /**
     * The position of the cursor in its order: -1 before the first row, the row count after the last one.
     */
    public int $position = -1;

    /**
     * @param string $schema The database of the table
     * @param string $table The table name
     * @param string $name The name of the handler, which the read rows are reported under
     */
    public function __construct(public readonly string $schema, public readonly string $table, public readonly string $name)
    {
    }
}
