<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Database;

/**
 * A database option the grammar spells with keywords instead of an identifier.
 *
 * Each spelling names the same option as the identifier the server turns it
 * into, so `CONNECTION LIMIT 5` and `connection_limit 5` request the same.
 * Source: https://www.postgresql.org/docs/17/sql-createdatabase.html.
 *
 * @visibility public
 * @example Reading the option a keyword spelling names
 *     \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Database\DatabaseOptionKeyword::ConnectionLimit->option() // => 'connection_limit'
 */
enum DatabaseOptionKeyword: string
{
    case ConnectionLimit = 'CONNECTION LIMIT';
    case Encoding = 'ENCODING';
    case Location = 'LOCATION';
    case Owner = 'OWNER';
    case Tablespace = 'TABLESPACE';
    case Template = 'TEMPLATE';

    /**
     * Answers the option name the server receives.
     */
    public function option(): string
    {
        return strtolower(str_replace(' ', '_', $this->value));
    }
}
