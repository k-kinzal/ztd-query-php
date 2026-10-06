<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Query\Having\GroupedRow;
use SqlSemantics\Platform\MySql\Rules\Query\Having\HavingScope;
use SqlSemantics\Platform\MySql\Rules\Query\Having\ResultReferences;
use SqlSemantics\Platform\MySql\Statement\Name\AmbiguousAlias;
use SqlSemantics\Resolution\ColumnLookup;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Resolution\LookupLevel;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Column\AliasTarget;
use SqlSemantics\Statement\Reference\Column\AmbiguousColumn;
use SqlSemantics\Statement\Reference\Column\ConditionalColumn;
use SqlSemantics\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Statement\Reference\Column\Resolution;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Reference\Missing\MissingInput;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Shape\OpenStar;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Nullability;
use SqlSemantics\Validation\Equivalence;

/**
 * Resolves a column name the way MySQL does: the columns of a query first, then its select list aliases.
 *
 * Rule: MYSQL-COLUMN-LOOKUP-001. The lookup starts at the innermost query and
 * moves outwards one query at a time. At one query the relation occurrences
 * the qualifier admits are searched as in CORE-COLUMN-LOOKUP-001: one known
 * slot resolves, several are ambiguous, and an incompletely known occurrence
 * at that or a nearer query makes the outcome conditional. When no slot has
 * the name, an unqualified name is searched among the select list aliases
 * the position may use (GROUP BY, HAVING, ORDER BY): one item, or several
 * items computing the same expression, resolve to that item; several
 * different items are ambiguous (ER_NON_UNIQ_ERROR). While an incompletely
 * known occurrence could still own the name, the alias is not chosen and the
 * outcome is conditional; so it is while a select list item whose name
 * depends on missing inputs (OutputSlot::$unnamed) could have the name.
 * Only a name found neither way continues outwards.
 * At a HAVING position (MYSQL-HAVING-SCOPE-001) the GROUP BY columns and
 * the select list are searched first (MYSQL-HAVING-REFERENCE-001); a name
 * written there outside set functions never sees the columns of the FROM
 * clause of its own query (ER_BAD_FIELD_ERROR "in 'having clause'"), and a
 * name of a nested query sees them only when the enclosing block neither
 * groups, aggregates nor is DISTINCT (Item_ref::fix_fields,
 * Item_field::fix_outer_field). A select list star over an incompletely
 * known occurrence, and a GROUP BY or select list column of the name that
 * belongs to such an occurrence, leave a name not found there conditional.
 * Terminates: the scopes form a finite chain. Source:
 * https://dev.mysql.com/doc/refman/8.4/en/select.html ("For GROUP BY or
 * HAVING clauses, it searches the FROM clause before searching in the
 * select_expr values"), https://dev.mysql.com/doc/refman/8.4/en/problems-with-alias.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ColumnResolver
{
    /**
     * Resolves a column name written with an optional relation qualifier.
     */
    public function find(Environment $environment, Name $column, ?QualifiedName $qualifier = null): Resolution
    {
        $lookup = new ColumnLookup();
        $open = [];
        $depth = 0;
        for ($scope = $environment; $scope !== null; $scope = $scope->outer) {
            $row = (new HavingScope())->row($scope);
            if ($row !== null) {
                $result = (new ResultReferences())->find($row, $scope, $column, $qualifier, $depth);
                if ($result instanceof ConditionalColumn) {
                    return $this->undecided($column, $result->candidates, $open, $result->missing);
                }
                if ($result !== null) {
                    return $open === [] ? $result : $lookup->conditional($column, $result instanceof ResolvedColumn ? [$result] : [], $open);
                }
                $open = [...$open, ...$this->unlisted($row, $scope, $column)];
                if ($depth === 0 || $row->grouped) {
                    $depth++;
                    continue;
                }
            }
            $level = new LookupLevel($scope, $column, $qualifier, $depth);
            $found = $level->found();
            $open = [...$open, ...$level->open()];
            if ($found !== []) {
                if ($open !== []) {
                    return $lookup->conditional($column, $found, $open);
                }

                return count($found) === 1 ? $found[0] : new AmbiguousColumn($column, $found);
            }
            $unnamed = $qualifier === null ? $this->unnamed($scope->aliases) : [];
            if ($unnamed !== []) {
                return $this->undecided($column, [], $open, $unnamed);
            }
            $aliases = $qualifier === null ? $scope->aliased($column) : [];
            if ($aliases !== []) {
                return $open === [] ? $this->alias($column, $aliases) : $lookup->conditional($column, [], $open);
            }
            $depth++;
        }

        return $open === [] ? new MissingColumn($column, $qualifier) : $lookup->conditional($column, [], $open);
    }

    /**
     * Answers the facts of a column name: those of the column or select list item it resolves to, else the outcome as the cause.
     */
    public function fact(Environment $environment, Name $column, ?QualifiedName $qualifier = null): ScalarFact
    {
        $resolution = $this->find($environment, $column, $qualifier);
        if ($resolution instanceof ResolvedColumn) {
            return new ScalarFact($resolution->slot->type, $resolution->slot->nullability, $resolution);
        }
        if ($resolution instanceof AliasTarget) {
            return new ScalarFact($resolution->field->type, $resolution->field->nullability, $resolution);
        }
        if ($resolution instanceof ConditionalColumn) {
            return new ScalarFact(new Dependent($resolution->missing), Nullability::Dependent, $resolution);
        }
        Check::invariant($resolution instanceof Diagnostic, 'A column lookup resolves, depends on missing inputs, or reports a problem.');

        return new ScalarFact(new Invalid($resolution), Nullability::Dependent, $resolution);
    }

    /**
     * Tells whether a qualifier is NEW or OLD and a trigger body makes that row visible at the position.
     *
     * The parser reads `NEW.x` and `OLD.x` in a trigger body as a column of
     * the row (Item_trigger_field), whatever the position.
     */
    public function row(Environment $environment, QualifiedName $qualifier): bool
    {
        $word = $qualifier->name->value;
        if ($qualifier->schema !== null || (strcasecmp($word, 'NEW') !== 0 && strcasecmp($word, 'OLD') !== 0)) {
            return false;
        }
        for ($scope = $environment; $scope !== null; $scope = $scope->outer) {
            foreach ($scope->relations as $relation) {
                if ($relation->alias !== null && strcasecmp($relation->alias->value, $word) === 0) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Answers the incompletely known occurrences that may give a HAVING position a column of the name unseen.
     *
     * They are the occurrences a star of the select list may list, and the
     * occurrences that may own a GROUP BY or select list column of the
     * name that no known occurrence decides.
     *
     * @return list<VisibleRelation>
     */
    public function unlisted(GroupedRow $row, Environment $scope, Name $column): array
    {
        foreach ($row->selected as $item) {
            if ($item instanceof OpenStar) {
                return array_values(array_filter($scope->relations, static fn (VisibleRelation $relation): bool => !$relation->shape->complete()));
            }
        }
        $owners = [];
        foreach ($row->undecided as $undecided) {
            if ($scope->context->columnNames->equal($undecided->name->value, $column->value)) {
                array_push($owners, ...$undecided->relations);
            }
        }
        $open = [];
        for ($level = $scope; $level !== null; $level = $level->outer) {
            foreach ($level->relations as $relation) {
                if (in_array($relation->relation, $owners, true)) {
                    $open[] = $relation;
                }
            }
        }

        return $open;
    }

    /**
     * Answers the inputs the names of select list items depend on: an item without a decided name may be named anything.
     *
     * @param list<Field> $fields
     * @return list<MissingInput>
     */
    public function unnamed(array $fields): array
    {
        $missing = [];
        foreach ($fields as $field) {
            array_push($missing, ...$field->slot->unnamed);
        }

        return $missing;
    }

    /**
     * Builds the conditional outcome of a name that incompletely known occurrences or undecided item names may own.
     *
     * @param list<ResolvedColumn> $candidates
     * @param list<VisibleRelation> $open
     * @param list<MissingInput> $unnamed The inputs the names of select list items depend on
     */
    public function undecided(Name $column, array $candidates, array $open, array $unnamed): ConditionalColumn
    {
        $relations = [];
        $missing = [];
        foreach ($open as $relation) {
            $relations[] = $relation->relation;
            array_push($missing, ...LookupLevel::undecided($relation));
        }

        return new ConditionalColumn($column, $candidates, $relations, [...$missing, ...$unnamed]);
    }

    /**
     * Chooses the select list item an alias names: the only one, or the first of items that compute the same expression.
     *
     * @param non-empty-list<Field> $fields
     */
    public function alias(Name $column, array $fields): AliasTarget|AmbiguousAlias
    {
        $first = $fields[0];
        $equivalence = new Equivalence();
        foreach ($fields as $field) {
            if ($field->expression === null || $first->expression === null || $equivalence->difference($first->expression, $field->expression) !== null) {
                return count($fields) === 1 ? new AliasTarget($first) : new AmbiguousAlias($column, $fields);
            }
        }

        return new AliasTarget($first);
    }
}
