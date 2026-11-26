<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql;

use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\ColumnStar;

/**
 * Checks catalog qualifiers before relation or column binding can erase them.
 *
 * @visibility SqlSemantics
 */
final class LocalCatalogBinding
{
    /**
     * Checks the catalog position of each decoded relation or column name.
     *
     * @param list<object> $objects The checked parsed value graph
     */
    public function check(array $objects, ?Name $currentDatabase): void
    {
        foreach ($objects as $object) {
            $explicit = match (true) {
                $object instanceof QualifiedName => $object->catalog,
                $object instanceof ColumnReference && count($object->parts) === 4 => $object->parts[0],
                $object instanceof ColumnStar && count($object->qualifiers) === 3 => $object->qualifiers[0],
                default => null,
            };
            Check::input($explicit === null || $explicit->value === $currentDatabase?->value, 'An explicit PostgreSQL catalog requires the matching current database.');
        }
    }
}
