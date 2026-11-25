<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query;

use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\NativeLocalNames;
use SqlSemantics\Platform\MySql\Model\Statement\Query;
use SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;

/**
 * Derives inherited names for a CTE or derived table from its own explicit aliases.
 * @visibility SqlSemantics\Platform\MySql
 */
final class InheritedAliases
{
    /**
     * Resolves explicit UTF-8 aliases for the relation that inherits the query's labels.
     * @return array<int, Name> Native labels keyed by the original item expression
     */
    public function names(Query $query): array
    {
        while ($query instanceof ParenthesizedQuery || $query instanceof QueryExpression) {
            $query = $query instanceof ParenthesizedQuery ? $query->query : $query->body;
        }
        $aliases = [];
        if ($query instanceof Select) {
            $names = new NativeLocalNames();
            foreach ($query->items as $item) {
                if ($item instanceof SelectExpression && $item->alias !== null
                    && preg_match('/\A[^\x00\x{10000}-\x{10FFFF}]*\z/u', $item->alias->value) === 1) {
                    $name = $names->prefix($item->alias->value);
                    $aliases[spl_object_id($item->expression)] = new Name($name);
                }
            }
        }

        return $aliases;
    }
}
