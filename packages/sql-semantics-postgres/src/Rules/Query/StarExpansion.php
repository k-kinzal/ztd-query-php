<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Query;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Rules\Resolution\Visibility;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Problem\QueryMisuse;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Problem\QueryMisuseRule;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Shape\OpenStar;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Known;

/**
 * Expands the stars of a select list into output fields.
 *
 * Rule: PG-STAR-001. `*` selects, in FROM order, every column a FROM item
 * shows to unqualified names: the columns of a join with the merged USING
 * and NATURAL columns once and first, and no column of a relation reachable
 * by qualifier only. Without any FROM item it is reported. `t.*` selects
 * every column of the relation the qualifier names, at the nearest query
 * level that has one, including the columns merged by a join. A relation
 * whose columns are not all known contributes its known columns and an open
 * star naming the missing inputs. `(value).*` selects the fields of a
 * composite value, which the context cannot describe: the shape is open on
 * the inputs the value depends on, and a value of a known type that is not
 * composite is reported. Every field refers to the slot it selects.
 * Terminates: one pass over the relations of a level.
 * Source: https://www.postgresql.org/docs/17/sql-select.html#SQL-SELECT-LIST,
 * https://www.postgresql.org/docs/17/rowtypes.html#ROWTYPES-USAGE. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class StarExpansion
{
    /**
     * Expands `*` over the relations of the level of an environment.
     *
     * @return list<Field|OpenStar>
     */
    public function all(Derivation $derivation, Environment $environment, int $position): array
    {
        $visibility = new Visibility();
        $items = [];
        $seen = false;
        foreach ($environment->relations as $relation) {
            if ($visibility->restricted($relation)) {
                continue;
            }
            $seen = true;
            $items = $this->expand($items, $relation, true, $position);
        }
        if (!$seen) {
            $derivation->report(new QueryMisuse(QueryMisuseRule::StarWithoutTables));
        }

        return $items;
    }

    /**
     * Expands `qualifier.*` over the relation the qualifier names at the nearest level that has one.
     *
     * @param non-empty-list<Name> $qualifiers
     * @return list<Field|OpenStar>
     */
    public function qualified(Environment $environment, array $qualifiers, int $position): array
    {
        $qualifier = (new DottedName($qualifiers))->qualified();
        if ($qualifier === null) {
            return [];
        }
        $visibility = new Visibility();
        for ($scope = $environment; $scope !== null; $scope = $scope->outer) {
            $items = [];
            foreach ($scope->relations as $relation) {
                if ($visibility->admits($scope, $relation, $qualifier)) {
                    $items = $this->expand($items, $relation, false, $position);
                }
            }
            if ($items !== []) {
                return $items;
            }
        }

        return [];
    }

    /**
     * Expands the fields of a composite value from its facts.
     *
     * @return list<Field|OpenStar>
     */
    public function composite(Derivation $derivation, ScalarFact $fact): array
    {
        if ($fact->type instanceof Dependent) {
            return [new OpenStar($fact->type->missing)];
        }
        if ($fact->type instanceof Known) {
            $derivation->report(new QueryMisuse(QueryMisuseRule::NotComposite, new Name($fact->type->descriptor->name())));
        }

        return [];
    }

    /**
     * Appends the slots of one relation, skipping hidden slots when the star is unqualified.
     *
     * @param list<Field|OpenStar> $items
     * @return list<Field|OpenStar>
     */
    public function expand(array $items, VisibleRelation $relation, bool $unqualified, int $position): array
    {
        foreach ($relation->shape->slots as $index => $slot) {
            if (!$unqualified || !in_array($index, $relation->hidden, true)) {
                $items[] = new Field($position + count($items), new OutputSlot($slot->name, $slot->type, $slot->nullability, null, $slot), null, new ResolvedColumn($relation->relation, $slot));
            }
        }
        if (!$relation->shape->complete()) {
            $items[] = new OpenStar($relation->shape->missing);
        }

        return $items;
    }
}
