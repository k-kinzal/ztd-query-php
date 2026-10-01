<?php

declare(strict_types=1);

namespace SqlSemantics\Semantic\Reference;

/**
 * The visible relations have no column with this name.
 * @example Reading semantic relationships
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT foo');
 *     $statement->field('foo')->type->name // => 'invalid'
 *
 * @visibility public
 */
final class MissingColumn
{
}
