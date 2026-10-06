<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Copy;

/**
 * Which FORCE option of the old COPY syntax an item sets: FORCE QUOTE, FORCE NOT NULL or FORCE NULL.
 *
 * Each sets the generic option `force_quote`, `force_not_null` or `force_null`.
 * Source: https://www.postgresql.org/docs/17/sql-copy.html#id-1.9.3.55.10.
 *
 * @visibility public
 * @example Reading the kind of a FORCE option
 *     $copy = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('COPY t FROM STDIN CSV FORCE NOT NULL a');
 *     $copy->statement->legacy[1]->kind // => \SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Copy\CopyForceKind::NotNull
 */
enum CopyForceKind
{
    case Quote;
    case NotNull;
    case Null;
}
