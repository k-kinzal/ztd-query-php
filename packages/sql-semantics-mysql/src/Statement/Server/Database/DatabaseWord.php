<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Database;

/**
 * The keyword naming a database in DDL and SHOW CREATE.
 *
 * @visibility public
 * @example Choosing the SCHEMA synonym
 *     \SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseWord::Schema->value // => 'SCHEMA'
 */
enum DatabaseWord: string
{
    case Database = 'DATABASE';
    case Schema = 'SCHEMA';
}
