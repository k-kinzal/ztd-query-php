<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query;

use SqlSemantics\Platform\MySql\Rules\Query\Grouping\OrderingAggregates;
use SqlSemantics\Platform\MySql\Rules\Query\Having\ResultReferences;
use SqlSemantics\Platform\MySql\Statement\Name\AliasRule;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Name\InvalidProjectionAlias;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Reference\Column\AliasTarget;
use SqlSemantics\Statement\Reference\Column\ConditionalColumn;
use SqlSemantics\Statement\Reference\Column\Resolution;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;

/**
 * Resolves select-list names reached from a nested query in that list.
 *
 * Input columns take precedence. Among matching result names, a computed item
 * takes precedence over column items. Unresolved items are forward references;
 * repeated column items agree only after both bind to the same input. An item
 * containing an aggregate owned by this block cannot be referenced here.
 * Verified through SQL on MySQL 5.6.51 and 8.4.7.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/problems-with-alias.html.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ProjectionLookup
{
    /**
     * Answers a visible enclosing select item, or the reason it cannot be used.
     */
    public function find(Environment $scope, Name $name, int $depth): ?Resolution
    {
        if ($depth === 0 || $scope->projection === null) {
            return null;
        }
        $matches = [];
        $missing = [];
        foreach ($scope->projection->items as $position => [$declared, $expression]) {
            if (!$declared instanceof Name) {
                $missing[] = $declared;
                continue;
            }
            if ($scope->context->columnNames->equal($declared->value, $name->value)) {
                $matches[] = $position;
                if (!(new ItemNaming($scope->context->profile))->own($expression) instanceof ColumnUse) {
                    $matches = [$position];
                    break;
                }
            }
        }
        if ($missing !== []) {
            return new ConditionalColumn($name, [], [], $missing);
        }

        return $matches === [] ? null : $this->chosen($scope, $name, $matches, $depth);
    }

    /**
     * Chooses among matching names and retains the dependency on the outer row.
     *
     * @param non-empty-list<int> $matches The matching select-item positions
     */
    public function chosen(Environment $scope, Name $name, array $matches, int $depth): Resolution
    {
        $projection = $scope->projection;
        \SqlSemantics\Diagnostic\Check::invariant($projection !== null, 'A select-list lookup has a projection scope.');
        $field = $projection->field($matches[0]);
        $candidates = array_map(static fn (int $position) => $projection->items[$position][1], $matches);
        foreach (array_slice($matches, 1) as $position) {
            $other = $projection->field($position);
            if (!$field?->resolution instanceof ResolvedColumn || !$other?->resolution instanceof ResolvedColumn || !(new ResultReferences())->same($field->resolution, $other->resolution)) {
                return new InvalidProjectionAlias($name, AliasRule::Ambiguous, $candidates);
            }
        }
        if ($field === null) {
            return new InvalidProjectionAlias($name, AliasRule::Forward, $candidates);
        }
        $owned = array_fill_keys(array_map(spl_object_id(...), $scope->aggregation?->expressions() ?? []), true);
        if ($field->expression !== null && (new OrderingAggregates())->occurrences($field->expression, $owned) !== []) {
            return new InvalidProjectionAlias($name, AliasRule::Aggregate, $candidates);
        }

        return new AliasTarget($field, $depth);
    }
}
