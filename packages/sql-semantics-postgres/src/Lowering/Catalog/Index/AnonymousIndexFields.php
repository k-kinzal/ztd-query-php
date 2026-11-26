<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Catalog\Index;

use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\Table;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Index\ColumnKey;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Index\CreateIndex;

/**
 * Selects physical fields from a bound catalog definition.
 *
 * @visibility SqlSemantics
 */
final class AnonymousIndexFields
{
    /**
     * Reads the exact physical columns in key and covering occurrence order.
     * @return non-empty-list<string>|null
     */
    public static function resolve(BoundStatement $binding, CreateIndex $index, Table $owner): ?array
    {
        $fields = [];
        foreach ([...$index->elements, ...$index->included] as $element) {
            if (!$element->key instanceof ColumnKey) {
                return null;
            }
            $resolution = $binding->facts->scalar($element->key)->resolution;
            if (!$resolution instanceof ResolvedColumn || $resolution->depth !== 0 || $resolution->relation !== $index
                || ($column = $resolution->declaration()) === null || !in_array($column, $owner->columns, true)) {
                return null;
            }
            $fields[] = $column->name->value;
        }

        return $fields;
    }
}
