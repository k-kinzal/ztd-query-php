<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema;

/**
 * A builtin PostgreSQL character-storage role with the default collation.
 *
 * @visibility public
 * @example Naming builtin text independently of the search path
 *     \SqlSemantics\Platform\PostgreSql\Ir\Schema\CharacterStorage::Text->value // => 'pg_catalog.text'
 */
enum CharacterStorage: string
{
    case Text = 'pg_catalog.text';
}
