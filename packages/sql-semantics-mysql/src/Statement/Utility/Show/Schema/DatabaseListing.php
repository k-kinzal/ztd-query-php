<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema;

/**
 * The database-listing keyword selected by a SHOW request.
 *
 * @visibility public
 * @example Selecting SHOW SCHEMAS
 *     \SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\DatabaseListing::Schemas->value // => 'SCHEMAS'
 */
enum DatabaseListing: string
{
    case Databases = 'DATABASES';
    case Schemas = 'SCHEMAS';
}
