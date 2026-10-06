<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Expression\Operands;
use SqlSemantics\Platform\MySql\Rules\Query\From\JoinedInput;
use SqlSemantics\Platform\MySql\Statement\Name\TableWildcard;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Star;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Reference\Missing\MissingInput;
use SqlSemantics\Statement\Reference\Table\MissingTable;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Shape\OpenStar;
use SqlSemantics\Statement\Shape\OutputSlot;

/**
 * Derives the output fields of a select list.
 *
 * Rule: MYSQL-STAR-001. An expression item is one field named by
 * MYSQL-SELECT-ITEM-NAME-001. `*` contributes the columns the FROM clause
 * selects (MYSQL-JOIN-COLUMNS-001: merged columns once); `t.*` contributes
 * every column of the tables the qualifier names, merged ones included. A
 * relation whose columns are not all known contributes its known columns
 * and an open star that names the missing inputs; columns whose names
 * depend on missing inputs (MYSQL-DERIVED-SHAPES-001) are known columns.
 * An item whose name depends on missing inputs is a field without a name
 * that names those inputs (OutputSlot::$unnamed). A star without any table and a qualifier
 * that names no table are reported. Terminates: one pass over the list.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/select.html,
 * https://dev.mysql.com/doc/refman/8.4/en/join.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Projection
{
    /**
     * Derives the select list where the given environment is visible.
     *
     * @param list<SelectExpression|Star|TableWildcard> $items
     * @return list<Field|OpenStar>
     */
    public function items(array $items, Derivation $derivation, Environment $environment, JoinedInput $from): array
    {
        $fields = [];
        foreach ($items as $item) {
            if ($item instanceof SelectExpression) {
                $fact = (new Operands())->single($derivation->scalar($item->expression, $environment), $derivation);
                $origin = $fact->resolution instanceof ResolvedColumn ? $fact->resolution->slot : null;
                $name = (new ItemNaming($derivation->context->profile))->name($item);
                $slot = $name instanceof Name ? new OutputSlot($name, $fact->type, $fact->nullability, null, $origin) : new OutputSlot(null, $fact->type, $fact->nullability, null, $origin, [$name]);
                $fields[] = new Field(count($fields), $slot, $item->expression, $fact->resolution);
            } elseif ($item instanceof Star) {
                $fields = $this->star($fields, $derivation, $environment, $from);
            } else {
                $fields = $this->qualified($fields, $derivation, $environment, $item->table);
            }
        }

        return $fields;
    }

    /**
     * Appends the columns `*` selects.
     *
     * @param list<Field|OpenStar> $fields
     * @return list<Field|OpenStar>
     */
    public function star(array $fields, Derivation $derivation, Environment $environment, JoinedInput $from): array
    {
        if ($from->visible === []) {
            $derivation->report(new Misuse(MisuseRule::StarWithoutTables));

            return $fields;
        }
        foreach ($environment->relations as $index => $relation) {
            if (!$relation->shape->complete()) {
                foreach ($environment->relations as $each) {
                    $fields = $this->expand($fields, $each, true);
                }

                return $fields;
            }
        }
        foreach ($from->star as [$index, $position]) {
            $relation = $environment->relations[$index];
            $fields[] = $this->field(count($fields), $relation, $relation->shape->slots[$position]);
        }

        return $fields;
    }

    /**
     * Appends every column of the relations a qualifier names.
     *
     * @param list<Field|OpenStar> $fields
     * @return list<Field|OpenStar>
     */
    public function qualified(array $fields, Derivation $derivation, Environment $environment, QualifiedName $table): array
    {
        $found = false;
        foreach ($environment->relations as $relation) {
            if ($this->admits($derivation, $relation, $table)) {
                $found = true;
                $fields = $this->expand($fields, $relation, false);
            }
        }
        if (!$found) {
            $derivation->report(new MissingTable($table));
        }

        return $fields;
    }

    /**
     * Appends the columns of one relation, skipping the merged ones when asked, and an open star when its columns are not all known.
     *
     * @param list<Field|OpenStar> $fields
     * @return list<Field|OpenStar>
     */
    public function expand(array $fields, VisibleRelation $relation, bool $merged): array
    {
        foreach ($relation->shape->slots as $position => $slot) {
            if (!$merged || !in_array($position, $relation->hidden, true)) {
                $fields[] = $this->field(count($fields), $relation, $slot);
            }
        }
        if (!$relation->shape->complete()) {
            $fields[] = new OpenStar($relation->shape->missing);
        }

        return $fields;
    }

    /**
     * Answers the field a star selects for one column of a relation.
     */
    public function field(int $position, VisibleRelation $relation, OutputSlot $slot): Field
    {
        return new Field($position, new OutputSlot($slot->name, $slot->type, $slot->nullability, null, $slot, $slot->unnamed), null, new ResolvedColumn($relation->relation, $slot));
    }

    /**
     * Tells whether a qualifier names a visible relation: by its correlation name when it has one, else by its table name.
     */
    public function admits(Derivation $derivation, VisibleRelation $relation, QualifiedName $qualifier): bool
    {
        $names = $derivation->context->relationNames;
        if ($relation->alias !== null) {
            return $qualifier->schema === null && $names->equal($relation->alias->value, $qualifier->name->value);
        }
        if ($relation->name === null || !$names->equal($relation->name->name->value, $qualifier->name->value)) {
            return false;
        }

        return $qualifier->schema === null || $relation->name->schema === null || $names->equal($relation->name->schema->value, $qualifier->schema->value);
    }

    /**
     * Collects the missing inputs of open relations.
     *
     * @param list<VisibleRelation> $relations
     * @return list<MissingInput>
     */
    public function missing(array $relations): array
    {
        $missing = [];
        foreach ($relations as $relation) {
            array_push($missing, ...$relation->shape->missing);
        }

        return $missing;
    }
}
