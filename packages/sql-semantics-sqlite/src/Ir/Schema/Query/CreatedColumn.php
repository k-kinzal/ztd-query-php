<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Query;

use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * The resolved catalog name and declaration type of a created column.
 *
 * @visibility public
 * @example Describing a result column with no declared type
 *     $name = new \SqlSemantics\Platform\Sqlite\Ir\Identifier('value');
 *     $type = \SqlSemantics\Platform\Sqlite\Ir\Schema\Query\CreatedColumnDeclaration::Untyped;
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Schema\Query\CreatedColumn($name, $type))->name->value // => 'value'
 */
final class CreatedColumn
{
    use Snapshot;

    /**
     * Defines one slot in the table's computed catalog layout.
     */
    public function __construct(public readonly Identifier $name, public readonly CreatedColumnDeclaration $type)
    {
    }
}
