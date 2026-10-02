<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Reference\Column;

use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Snapshot;

/**
 * A name that resolves to an output field of the same query by its alias.
 *
 * The reference stays a reference: the aliased expression is not copied to the use position.
 *
 * @visibility public
 * @example Resolving an ORDER BY name to an output alias
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT 1 AS x ORDER BY x');
 *     $query->facts->scalar($query->statement->ordering[0]->expression)->resolution->field === $query->field('x') // => true
 */
final class AliasTarget implements Resolution
{
    use Snapshot;

    /**
     * @param Field $field The output field the alias names
     */
    public function __construct(public readonly Field $field)
    {
    }
}
