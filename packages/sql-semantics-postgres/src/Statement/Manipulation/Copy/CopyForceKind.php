<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Copy;

/**
 * Which FORCE option of the old COPY syntax an item sets: FORCE QUOTE, FORCE NOT NULL or FORCE NULL.
 *
 * Each sets the generic option `force_quote`, `force_not_null` or `force_null`.
 * Source: https://www.postgresql.org/docs/17/sql-copy.html#id-1.9.3.55.10.
 *
 * @visibility SqlSemantics
 */
enum CopyForceKind
{
    case Quote;
    case NotNull;
    case Null;
}
