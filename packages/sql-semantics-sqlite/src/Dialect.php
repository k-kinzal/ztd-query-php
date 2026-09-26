<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite;

/**
 * Selects the Sqlite implementation installed with this package.
 *
 * @visibility public
 * @example Reconstruct SQL using this database package
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite);
 *     $semantics->analyze('DROP TABLE example')->toString() // => 'DROP TABLE example'
 */
enum Dialect: string implements \SqlSemantics\Core\Dialect
{
    case Sqlite = 'sqlite';

    /**
     * Supplies this database's parser, models, and semantic policies.
     */
    public function platform(): \SqlSemantics\Core\Platform
    {
        return new Platform($this);
    }
}
