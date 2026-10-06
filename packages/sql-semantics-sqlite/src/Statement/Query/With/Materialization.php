<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Query\With;

/**
 * The materialization hint of a common table expression.
 *
 * Source: https://sqlite.org/lang_with.html#materialization_hints.
 *
 * @visibility public
 * @example Reading a materialization hint
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('WITH c AS NOT MATERIALIZED (SELECT 1) SELECT * FROM c');
 *     $query->statement->with->tables[0]->materialization // => \SqlSemantics\Platform\Sqlite\Statement\Query\With\Materialization::NotMaterialized
 */
enum Materialization: string
{
    case Materialized = 'MATERIALIZED';
    case NotMaterialized = 'NOT MATERIALIZED';
}
