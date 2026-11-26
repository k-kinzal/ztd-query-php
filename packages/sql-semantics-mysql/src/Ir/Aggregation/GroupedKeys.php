<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Aggregation;

use SqlSemantics\Platform\MySql\Ir\ColumnReference;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\SourceBinding;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectOption;

/**
 * Checks the grouped query clauses and resolves the ordered key layout.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class GroupedKeys
{
    /**
     * Checks the supported grouped input and maps each named key to its slot.
     *
     * @return list<ColumnReference>
     */
    public function lower(Select $statement, SourceBinding $binding): array
    {
        Check::input(
            $statement->windows === [] && $statement->qualify === null
                && $statement->orderBy === [] && $statement->limit === null && $statement->procedure === null
                && $statement->locking === [] && $statement->into === null && $statement->late === null
                && ($statement->options === [] || $statement->options === [SelectOption::All]),
            'The grouped projection requires an ordinary unbounded GROUP BY without result modifiers.',
        );
        $keys = [];
        Check::input($statement->groupBy?->modifier === null, 'A grouped projection requires simple grouping keys.');
        foreach ($statement->groupBy->items ?? [] as $key) {
            Check::input($key->direction === null, 'A grouped projection requires simple grouping keys without a direction.');
            $keys[] = $binding->reference($key->expression);
        }

        return $keys;
    }

    /**
     * Retains a bare spelling when the resolved grouping key also names a different result.
     * @param list<ColumnReference> $keys
     * @param list<GroupColumn> $results
     * @return list<ColumnReference|GroupNameReference>
     */
    public function withNames(Select $statement, array $keys, array $results): array
    {
        $scope = new GroupNameScope();
        $checked = [];
        foreach ($keys as $index => $key) {
            $expression = $statement->groupBy->items[$index]->expression ?? null;
            while ($expression instanceof Grouped) {
                $expression = $expression->operand;
            }
            $name = $expression instanceof ColumnUse && $expression->qualifier === null
                ? new Identifier($expression->name->value) : null;
            $checked[] = $name !== null && $scope->collides($key, $name, $results) ? new GroupNameReference($key, $name) : $key;
        }

        return $checked;
    }
}
