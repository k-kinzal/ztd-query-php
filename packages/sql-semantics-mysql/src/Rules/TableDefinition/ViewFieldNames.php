<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\TableDefinition;

use SqlSemantics\Platform\MySql\Model\Statement\Identifier\NativeLocalNames;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;

/**
 * Supplies the requested names and native assignment of written view fields.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ViewFieldNames
{
    /**
     * Gives the result of a native alias assignment when its bytes can be read.
     */
    public function assignedAlias(string $written): ?string
    {
        $name = (new NativeLocalNames())->prefix($written);

        return preg_match('/\A[^\x00\x{10000}-\x{10FFFF}]*\z/u', $name) === 1 ? $name : null;
    }

    /**
     * Indexes written scalar items by their occurrence in the projection facts.
     * @return array<int, SelectExpression>
     */
    public function items(?Select $block): array
    {
        $items = [];
        foreach ($block->items ?? [] as $item) {
            if ($item instanceof SelectExpression) {
                $items[spl_object_id($item->expression)] = $item;
            }
        }

        return $items;
    }
}
