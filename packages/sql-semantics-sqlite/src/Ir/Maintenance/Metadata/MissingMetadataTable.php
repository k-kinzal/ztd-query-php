<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata;

use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * A table-name selector known to be absent in the scope of the owning inspection.
 *
 * @visibility public
 * @example Inspecting a known empty result under the same catalog interpretation
 *     $name = new \SqlSemantics\Platform\Sqlite\Ir\Identifier('unknown table');
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata\MissingMetadataTable($name))->name->value // => 'unknown table'
 */
final class MissingMetadataTable
{
    use Snapshot;

    /**
     * Records the missing table's lookup name.
     */
    public function __construct(public readonly Identifier $name)
    {
    }
}
