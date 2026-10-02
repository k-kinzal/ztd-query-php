<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Shape;

use SqlSemantics\Statement\Snapshot;

/**
 * No output field has the looked-up name, and the shape is complete.
 *
 * @visibility public
 * @example Looking up an absent name
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT 1 AS a');
 *     $operation->lookupField('b')->name // => 'b'
 */
final class AbsentField implements FieldLookup
{
    use Snapshot;

    /**
     * @param string $name The looked-up name
     */
    public function __construct(public readonly string $name)
    {
    }
}
