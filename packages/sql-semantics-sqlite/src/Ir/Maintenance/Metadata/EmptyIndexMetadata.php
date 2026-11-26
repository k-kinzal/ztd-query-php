<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata;

use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * Index metadata with no requested name or a known absence in the same SQLite catalog.
 *
 * @visibility public
 * @example Selecting no index within the temporary database
 *     $empty = new \SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata\EmptyIndexMetadata(new \SqlSemantics\Platform\Sqlite\Ir\Identifier('temp'));
 *     $empty->name === null // => true
 */
final class EmptyIndexMetadata
{
    use Snapshot;

    /**
     * Retains the explicit schema access and the optional missing name.
     */
    public function __construct(public readonly ?Identifier $schema = null, public readonly ?Identifier $name = null)
    {
    }
}
