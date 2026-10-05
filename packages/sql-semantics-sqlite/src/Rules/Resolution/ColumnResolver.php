<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Rules\Resolution;

use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\RandomColumnName;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Column\AliasTarget;
use SqlSemantics\Statement\Reference\Column\AmbiguousColumn;
use SqlSemantics\Statement\Reference\Column\ConditionalColumn;
use SqlSemantics\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Statement\Reference\Column\Resolution;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Reference\Missing\MissingInput;

/**
 * Resolves a column name the way SQLite does.
 *
 * Rule: SQLITE-COLUMN-LOOKUP-001. The lookup starts at the innermost query
 * and moves outwards one query at a time. In a query, every input relation
 * the qualifier admits is asked for the name; a relation answers with its
 * first column of that name, an unqualified name skips the columns a USING
 * or NATURAL join merged away, and a relation without such a column answers
 * with its implicit column of that name. One answer resolves the name, more
 * than one makes it ambiguous. With no answer, an unqualified name that is a
 * result column alias of the query denotes that result column. A relation
 * whose columns are not all known (an undeclared table, or a column of a
 * subquery whose name follows an unexpanded star or is picked at random)
 * that does not answer makes the outcome conditional on what is missing. A
 * relation whose hidden list holds QUALIFIED_ONLY is reachable with a
 * qualifier only (NEW, OLD, excluded). An environment that holds nothing
 * but common tables is no query level.
 * Terminates: the scopes form a finite chain.
 * Source: https://sqlite.org/lang_expr.html#column_names,
 * https://sqlite.org/lang_select.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class ColumnResolver
{
    /**
     * The hidden-list entry that marks a relation as reachable with a qualifier only.
     */
    public const QUALIFIED_ONLY = -1;

    /**
     * Resolves a column name at a position.
     */
    public function find(Environment $environment, Name $column, ?QualifiedName $qualifier = null): Resolution
    {
        $relations = [];
        $missing = [];
        $depth = 0;
        for ($scope = $environment; $scope !== null; $scope = $scope->outer) {
            $found = [];
            foreach ($scope->relations as $relation) {
                if (!$this->admits($scope, $relation, $qualifier)) {
                    continue;
                }
                $match = $this->match($scope, $relation, $column, $qualifier === null, $depth);
                if ($match instanceof ResolvedColumn) {
                    $found[] = $match;
                } elseif ($match !== []) {
                    $relations[] = $relation->relation;
                    array_push($missing, ...$match);
                }
            }
            $aliases = $qualifier === null ? $scope->aliased($column) : [];
            if ($found !== [] || ($aliases !== [] && $missing !== [])) {
                if ($missing !== []) {
                    return new ConditionalColumn($column, $found, $relations, $missing);
                }

                return count($found) === 1 ? $found[0] : new AmbiguousColumn($column, $found);
            }
            if ($aliases !== []) {
                return new AliasTarget($aliases[0]);
            }
            if ($scope->relations !== [] || $scope->aliases !== [] || $scope->commonTables === []) {
                $depth++;
            }
        }

        return $missing === [] ? new MissingColumn($column, $qualifier) : new ConditionalColumn($column, [], $relations, $missing);
    }

    /**
     * Tells whether a qualifier admits a relation; no qualifier admits every relation that is not reachable by qualifier only.
     */
    public function admits(Environment $scope, VisibleRelation $relation, ?QualifiedName $qualifier): bool
    {
        if ($qualifier === null) {
            return !in_array(self::QUALIFIED_ONLY, $relation->hidden, true);
        }
        $names = $scope->context->relationNames;
        $schema = $relation->name?->schema;
        if ($qualifier->schema !== null && ($relation->name === null || ($schema !== null && !$names->equal($schema->value, $qualifier->schema->value)))) {
            return false;
        }
        if ($relation->alias !== null) {
            return $names->equal($relation->alias->value, $qualifier->name->value);
        }

        return $relation->name !== null && $names->equal($relation->name->name->value, $qualifier->name->value);
    }

    /**
     * Asks one relation for a column: the column found, or the inputs whose absence leaves the answer open (none when the relation certainly has no such column).
     *
     * @return ResolvedColumn|list<MissingInput>
     */
    public function match(Environment $scope, VisibleRelation $relation, Name $column, bool $merged, int $depth): ResolvedColumn|array
    {
        $names = $scope->context->columnNames;
        $unnamed = false;
        foreach ($relation->shape->slots as $position => $slot) {
            if ($slot->name === null) {
                $unnamed = true;
            } elseif ($names->equal($slot->name->value, $column->value) && !($merged && in_array($position, $relation->hidden, true))) {
                return new ResolvedColumn($relation->relation, $slot, $depth);
            }
        }
        foreach ($relation->implicit as $implicit) {
            foreach ($implicit->names as $candidate) {
                if ($names->equal($candidate->value, $column->value)) {
                    return new ResolvedColumn($relation->relation, $implicit->slot, $depth);
                }
            }
        }

        return $unnamed && $relation->shape->missing === [] ? [new RandomColumnName()] : $relation->shape->missing;
    }
}
