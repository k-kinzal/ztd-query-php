<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata;

use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * The builtin schema catalog of one resolved SQLite database.
 *
 * @visibility public
 * @example Choosing the catalog of the temporary database
 *     $catalog = new \SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata\CatalogTable(new \SqlSemantics\Platform\Sqlite\Ir\Identifier('temp'));
 *     $catalog->name()->value // => 'sqlite_temp_master'
 */
final class CatalogTable
{
    use Snapshot;

    /**
     * Records the catalog's native schema.
     */
    public function __construct(public readonly Identifier $schema)
    {
    }

    /**
     * Gives the native lookup key of the catalog.
     */
    public function name(): Identifier
    {
        return new Identifier($this->schema->key() === 'temp' ? 'sqlite_temp_master' : 'sqlite_master');
    }
}
