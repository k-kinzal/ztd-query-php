<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query\Having;

use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\RelationQualifiers;
use SqlSemantics\Platform\MySql\Rules\SessionDatabase;
use SqlSemantics\Platform\MySql\Rules\Typing\Materialization;
use SqlSemantics\Platform\MySql\Statement\Query\With\CommonTableExpression;
use SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Resolution\LookupLevel;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\NamedRelation;
use SqlSemantics\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;

/**
 * Resolves the boundary between a HAVING result and the input rows it aggregates.
 *
 * Aggregate arguments prefer input columns, including in nested queries. Ordinary
 * nested expressions can read selected results, but cannot reach unselected input
 * columns of a grouped block before MySQL 9.1, except through eligible merged inputs.
 * An inaccessible column is reported with the qualifying name of the input it would
 * otherwise read. Verified through SQL on MySQL 5.6, 8.0, 8.4 and 9.1.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/select.html.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class HavingLookup
{
    /**
     * Answers the result row to search, unless an aggregate argument can read input first.
     */
    public function row(Environment $environment, Name $column, ?QualifiedName $qualifier, int $depth, bool $argument): ?GroupedRow
    {
        $row = (new HavingScope())->row($environment);
        if ($row === null || $depth === 0) {
            return $row;
        }
        $input = $this->input($environment, $column, $qualifier, $depth);

        return ($argument || $this->merged($environment, $input->found())) && ($input->found() !== [] || $input->open() !== []) ? null : $row;
    }

    /**
     * Tells whether an outer input is exposed by merging a derived or common table.
     *
     * From MySQL 5.7, an eligible derived table with derived_merge enabled exposes
     * its columns to nested HAVING expressions. LIMIT, grouping and set operations
     * prevent that merge. MySQL 9.1 also exposes materialized inputs separately.
     *
     * @param list<ResolvedColumn> $columns
     */
    public function merged(Environment $environment, array $columns): bool
    {
        if (count($columns) !== 1 || $environment->context->profile->grammar === GrammarRelease::MySql5651 || !Settings::of($environment->context)->derivedMerge) {
            return false;
        }
        $relation = $columns[0]->relation;
        $definition = $relation instanceof NamedRelation && $relation->name()->schema === null ? $environment->commonTable($relation->name()->name)?->definition : null;
        $query = $relation instanceof DerivedTable ? $relation->query : ($definition instanceof CommonTableExpression ? $definition->query : null);

        return $query !== null && (new Materialization())->mergeable($query);
    }

    /**
     * Opens an input lookup, including a qualifier that explicitly names a schema.
     */
    public function input(Environment $environment, Name $column, ?QualifiedName $qualifier, int $depth): LookupLevel
    {
        return $qualifier?->schema === null ? new LookupLevel($environment, $column, $qualifier, $depth) : new LookupLevel((new RelationQualifiers())->narrowed($environment, $qualifier), $column, new QualifiedName($qualifier->name), $depth);
    }

    /**
     * Tells whether ordinary lookup must skip the input of a HAVING position.
     */
    public function blocked(GroupedRow $row, Environment $environment, int $depth): bool
    {
        return $depth === 0 || ($environment->context->profile->grammar !== GrammarRelease::MySql910 && ($row->grouped || ($environment->aggregation !== null && $environment->aggregation->expressions() !== [])));
    }

    /**
     * Names an inaccessible outer input column when its underlying occurrence is known.
     */
    public function missing(Environment $environment, Name $column, ?QualifiedName $qualifier): MissingColumn
    {
        $depth = 1;
        for ($scope = $environment->outer; $scope !== null; $scope = $scope->outer, $depth++) {
            $row = (new HavingScope())->row($scope);
            if ($row === null || !$this->blocked($row, $scope, $depth)) {
                continue;
            }
            $found = $this->input($scope, $column, $qualifier, $depth)->found();
            if (count($found) !== 1) {
                continue;
            }
            foreach ($scope->relations as $visible) {
                if ($visible->relation !== $found[0]->relation) {
                    continue;
                }
                $name = $visible->alias ?? $visible->name?->name;
                $node = $visible->relation;
                $schema = $node instanceof NamedRelation && ($node->name()->schema !== null || $scope->commonTable($node->name()->name) === null) ? ($node->name()->schema ?? (new SessionDatabase())->named($scope->context)) : null;

                return new MissingColumn($column, $name === null ? $qualifier : new QualifiedName($name, $schema));
            }
        }

        return new MissingColumn($column, $qualifier);
    }
}
