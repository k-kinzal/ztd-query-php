<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Rules\Expression\Operands;
use SqlSemantics\Platform\MySql\Rules\Query\From\FromScope;
use SqlSemantics\Platform\MySql\Rules\Query\From\JoinedInput;
use SqlSemantics\Platform\MySql\Rules\Query\From\Joining;
use SqlSemantics\Platform\MySql\Rules\Query\Having\GroupedRow;
use SqlSemantics\Platform\MySql\Rules\Query\Having\HavingScope;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Query\SelectOption;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Shape\OpenStar;
use SqlSemantics\Statement\Shape\RowShape;

/**
 * Derives the facts of a query block.
 *
 * Rule: MYSQL-SELECT-FACTS-001. The FROM clause is derived in the enclosing
 * environment (MYSQL-FROM-SCOPE-001). The select list sees the FROM tables
 * and, beyond them, the enclosing queries; it does not see its own aliases.
 * WHERE sees the FROM tables. GROUP BY, QUALIFY and ORDER BY also see
 * the aliases of the select list (MYSQL-SORT-SCOPE-001); HAVING sees the
 * GROUP BY columns and the select list first (MYSQL-HAVING-SCOPE-001). The named windows
 * see the FROM tables. In a block that aggregates without GROUP BY
 * (MYSQL-AGGREGATE-QUERY-001; HAVING alone filters rows like WHERE) or groups WITH ROLLUP, ROLLUP or CUBE, every
 * column of the FROM tables read by the select list, HAVING, the windows,
 * QUALIFY and ORDER BY can be NULL. LIMIT, PROCEDURE ANALYSE, INTO and the
 * locking clauses follow MYSQL-TAIL-FACTS-001; a late ordering is derived
 * like the ORDER BY and LIMIT of the block. A window name defined twice
 * is reported, and so is a window name the block does not define
 * (MYSQL-WINDOW-NAME-001). The output fields follow MYSQL-STAR-001. Terminates: every
 * clause is a strict part of the block. Source:
 * https://dev.mysql.com/doc/refman/8.4/en/select.html,
 * https://dev.mysql.com/doc/refman/8.4/en/group-by-modifiers.html ("the
 * super-aggregate rows ... NULL"). Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SelectFacts
{
    /**
     * Derives every part of the block and answers its output.
     */
    public function derive(Select $select, Derivation $derivation, Environment $outer): QueryFact
    {
        $context = $derivation->context;
        $from = $select->from === null ? new JoinedInput(new RelationFact(new RowShape([])), [], []) : (new FromScope())->open($select->from, $derivation, $outer, []);
        $visible = $from->visible;
        $ordering = array_map(static fn (OrderItem $item): object => $item->expression, [...$select->orderBy, ...($select->late === null ? [] : $select->late->orderBy)]);
        $expressions = array_map(static fn (object $item): object => $item instanceof SelectExpression ? $item->expression : $item, $select->items);
        $aggregate = $select->groupBy === null && (new Aggregation())->aggregates([...$expressions, ...$ordering, ...array_values(array_filter([$select->having, $select->qualify]))]);
        $output = $aggregate || $select->groupBy?->modifier !== null ? array_map(static fn ($relation) => (new Joining())->extend($relation), $visible) : $visible;
        $items = (new Projection())->items($select->items, $derivation, new Environment($context, $outer, $output), new JoinedInput($from->fact, $output, $from->star));
        $aliases = $this->aliases($select, $items, $context->profile);
        if ($select->where !== null) {
            (new Operands())->single($derivation->scalar($select->where, new Environment($context, $outer, $visible)), $derivation);
        }
        $grouping = $select->groupBy === null ? [] : (new SortScopes())->derive($select->groupBy->items, $derivation, new Environment($context, $outer, $visible, [], $aliases), $items, false);
        $results = new Environment($context, $outer, $output, [], $aliases);
        if ($select->having !== null) {
            $scope = new HavingScope();
            $row = new GroupedRow($items, $scope->grouping($grouping, $visible, $output), $select->groupBy !== null || $aggregate || in_array(SelectOption::Distinct, $select->options, true), $scope->undecided($grouping, $items));
            (new Operands())->single($derivation->scalar($select->having, $scope->enter($results, $row)), $derivation);
        }
        $this->windows($select, $derivation, new Environment($context, $outer, $output));
        (new WindowReferences())->check($select, $derivation);
        if ($select->qualify !== null) {
            $derivation->scalar($select->qualify, $results);
        }
        (new SortScopes())->derive([...$select->orderBy, ...($select->late === null ? [] : $select->late->orderBy)], $derivation, $results, $items, true);
        (new TailFacts())->limit($select->limit, $derivation, $outer);
        (new TailFacts())->limit($select->late?->limit, $derivation, $outer);
        foreach ($select->procedure === null ? [] : $select->procedure->arguments as $argument) {
            $derivation->scalar($argument, new Environment($context, $outer));
        }
        $fact = new QueryFact($items, $context->columnNames);
        (new TailFacts())->derive($derivation, $outer, $fact, $select->into, $select->locking, $visible);

        return $fact;
    }

    /**
     * Derives the named windows and reports a name defined twice.
     */
    public function windows(Select $select, Derivation $derivation, Environment $environment): void
    {
        $seen = [];
        foreach ($select->windows as $window) {
            $window->specification->deriveWindow($derivation, $environment);
            $key = $derivation->context->columnNames->fold($window->name->value);
            if (isset($seen[$key])) {
                $derivation->report(new Misuse(MisuseRule::DuplicateWindow));
            }
            $seen[$key] = true;
        }
    }

    /**
     * Answers the output fields a name can refer to by their item name, in output order: aliased items and the items not named after a column.
     *
     * The server finds an item of the select list by its name, alias or
     * name given after its text alike, unless the item is a column
     * reference, which is found as the column it reads.
     *
     * @param list<Field|OpenStar> $items
     * @return list<Field>
     */
    public function aliases(Select $select, array $items, LanguageProfile $profile): array
    {
        $aliased = [];
        $naming = new ItemNaming($profile);
        foreach ($select->items as $item) {
            if ($item instanceof SelectExpression && ($item->alias !== null || !$naming->own($item->expression) instanceof ColumnUse)) {
                $aliased[spl_object_id($item->expression)] = true;
            }
        }
        $fields = [];
        foreach ($items as $field) {
            if ($field instanceof Field && $field->expression !== null && isset($aliased[spl_object_id($field->expression)])) {
                $fields[] = $field;
            }
        }

        return $fields;
    }
}
