<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Query\With;

/**
 * The materialization hint of a common table expression.
 *
 * Source: https://sqlite.org/lang_with.html#materialization_hints.
 *
 * @visibility SqlSemantics
 */
enum Materialization: string
{
    case Materialized = 'MATERIALIZED';
    case NotMaterialized = 'NOT MATERIALIZED';
}
