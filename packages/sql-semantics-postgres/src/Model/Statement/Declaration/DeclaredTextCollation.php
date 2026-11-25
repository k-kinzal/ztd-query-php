<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration;

use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName;

/**
 * A known stock text collation declared on a physical table column.
 *
 * @visibility SqlSemantics
 */
enum DeclaredTextCollation: string
{
    case StockC = 'pg_catalog.C';

    /**
     * Resolves a supported absolute native collation.
     */
    public static function resolve(?QualifiedName $name): ?self
    {
        return $name !== null && $name->catalog === null && $name->schema?->value === 'pg_catalog' && $name->name->value === 'C'
            ? self::StockC : null;
    }
}
