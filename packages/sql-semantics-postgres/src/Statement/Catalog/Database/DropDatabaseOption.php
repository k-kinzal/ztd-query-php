<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Database;

/**
 * An option of DROP DATABASE.
 *
 * FORCE terminates the existing connections to the database first.
 * Source: https://www.postgresql.org/docs/17/sql-dropdatabase.html.
 *
 * @visibility public
 * @example Spelling the option
 *     \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Database\DropDatabaseOption::Force->value // => 'FORCE'
 */
enum DropDatabaseOption: string
{
    case Force = 'FORCE';
}
