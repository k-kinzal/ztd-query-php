<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration;

/**
 * A known stock text collation declared on a physical table column.
 *
 * @visibility SqlSemantics
 */
enum DeclaredTextCollation: string
{
    case StockC = 'pg_catalog.C';
}
