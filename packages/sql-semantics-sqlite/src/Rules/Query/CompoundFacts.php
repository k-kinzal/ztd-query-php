<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Rules\Query;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Rules\Typing\Storages;
use SqlSemantics\Platform\Sqlite\Statement\Query\Compound;
use SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\SortTerm;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\ArityMismatch;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\ArityRule;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Query\With\CommonTable;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Resolution\CommonBinding;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Shape\OpenStar;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Shape\RowShape;
use SqlSemantics\Statement\Type\Choice;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Nullability;

/**
 * Derives the facts of a compound query.
 *
 * Rule: SQLITE-COMPOUND-SCOPE-001. Every arm is derived in the enclosing
 * environment. The output has the names of the first arm; the type of a
 * column is the choice over the arms and it can be NULL when it can in an
 * arm. Arms of different widths are reported, as are ORDER BY and LIMIT on
 * an arm that is not the last. When the compound is the query of a common
 * table that refers to itself, the arms after the first see the table with
 * the column names of its column list or of the first arm; because later
 * rounds of the recursion feed their own results back, a column of the
 * recursive reference can hold any storage class or NULL. An ORDER BY term
 * is a result column position, or an expression over the result column names
 * of the arms. Terminates: one pass over the arms; no fixpoint is computed.
 * Source: https://sqlite.org/lang_select.html#compound_select_statements,
 * https://sqlite.org/lang_with.html#recursive_common_table_expressions.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class CompoundFacts
{
    /**
     * Derives every part of the compound query and answers its output.
     */
    public function derive(Compound $compound, Derivation $derivation, Environment $outer): QueryFact
    {
        $arms = [$compound->first];
        foreach ($compound->steps as $step) {
            $arms[] = $step->query;
        }
        $facts = [];
        $environment = $outer;
        $open = [];
        foreach ($arms as $index => $arm) {
            $fact = $derivation->query($arm, $environment);
            $facts[] = $fact;
            if ($index === 0) {
                $environment = $this->rebound($compound, $fact, $outer);
            }
            if ($arm instanceof Select && $index < count($arms) - 1 && ($arm->orderBy !== [] || $arm->limit !== null)) {
                $derivation->report(new Misuse($arm->orderBy !== [] ? MisuseRule::OrderByBeforeCompound : MisuseRule::LimitBeforeCompound));
            }
            if ($arm instanceof Select && $arm->from !== null && !$fact->shape->complete()) {
                $open[] = new VisibleRelation($arm->from, new RowShape([], $fact->shape->missing));
            }
        }
        $items = $this->combined($facts, $derivation);
        $terms = array_map(static fn (SortTerm $term): Scalar => $term->expression, $compound->orderBy);
        (new SortScopes())->derive($terms, $derivation, new Environment($derivation->context, $outer, $open, [], $this->aliases($facts, $items)), $items, false);
        (new SelectFacts())->limit($compound->limit, $derivation, $outer);

        return new QueryFact($items, $derivation->context->columnNames);
    }

    /**
     * Combines the outputs of the arms into the output of the compound query.
     *
     * @param non-empty-list<QueryFact> $facts The outputs of the arms in written order
     * @return list<Field|OpenStar>
     */
    public function combined(array $facts, Derivation $derivation): array
    {
        $base = $facts[0]->fields();
        if ($base === null) {
            return [new OpenStar($facts[0]->shape->missing)];
        }
        $reported = false;
        $items = [];
        foreach ($base as $position => $field) {
            $types = [];
            $nullability = Nullability::NotNull;
            foreach ($facts as $fact) {
                $fields = $fact->fields();
                if ($fields !== null && count($fields) !== count($base)) {
                    if (!$reported) {
                        $derivation->report(new ArityMismatch(ArityRule::CompoundArms, count($base), count($fields)));
                        $reported = true;
                    }
                    continue;
                }
                $types[] = $fields === null ? new Dependent($fact->shape->missing) : $fields->at($position)->type;
                $nullability = $nullability->propagate($fields === null ? Nullability::Dependent : $fields->at($position)->nullability);
            }
            $items[] = new Field($position, new OutputSlot($field->name, (new Storages())->either($types), $nullability, null, $field->slot));
        }

        return $items;
    }

    /**
     * Answers the result column names of every arm as aliases of the output fields at the same positions.
     *
     * @param list<QueryFact> $facts
     * @param list<Field|OpenStar> $items
     * @return list<Field>
     */
    public function aliases(array $facts, array $items): array
    {
        $aliases = [];
        foreach ($facts as $fact) {
            foreach ($fact->fields() ?? [] as $position => $field) {
                $output = $items[$position] ?? null;
                if ($field->name !== null && $output instanceof Field) {
                    $aliases[] = new Field($position, new OutputSlot($field->name, $output->type, $output->nullability, null, $output->slot));
                }
            }
        }

        return $aliases;
    }

    /**
     * Answers the environment of the arms after the first: the one given, with the common table this compound defines bound to its recursive shape.
     */
    public function rebound(Compound $compound, QueryFact $anchor, Environment $outer): Environment
    {
        $tables = [];
        $changed = false;
        foreach ($outer->commonTables as $binding) {
            if ($binding->definition instanceof CommonTable && $binding->definition->query === $compound) {
                $slots = [];
                foreach ((new RelationNames())->shape($anchor)->slots as $position => $slot) {
                    $name = $binding->definition->columns === [] ? $slot->name : ($binding->definition->columns[$position]->name ?? null);
                    $slots[] = new OutputSlot($name, new Choice(Storage::cases()), Nullability::Nullable);
                }
                $binding = new CommonBinding($binding->name, $binding->definition, new RowShape($slots, $anchor->shape->missing));
                $changed = true;
            }
            $tables[] = $binding;
        }

        return $changed ? new Environment($outer->context, $outer->outer, $outer->relations, $tables, $outer->aliases) : $outer;
    }
}
