<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Model\Contract\Lookup;

use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName;

/**
 * Resolves stock names for catalog lookup roles that skip the session temporary namespace.
 *
 * @visibility SqlSemantics
 */
final class StockLookup
{
    /**
     * Tells whether application namespaces can shadow the stock namespace.
     * @param list<Name> $path Effective namespace order
     */
    public static function firstCatalog(array $path): bool
    {
        foreach ($path as $schema) {
            if ($schema->value !== 'pg_temp') {
                return $schema->value === 'pg_catalog';
            }
        }

        return false;
    }

    /**
     * Compares a decoded selector to a stock name at the known lookup boundary.
     */
    public static function matches(?QualifiedName $written, string $name, bool $stockFirst): bool
    {
        if ($written === null || $written->catalog !== null || $written->name->value !== $name) {
            return false;
        }

        return $written->schema === null ? $stockFirst : $written->schema->value === 'pg_catalog';
    }
}
