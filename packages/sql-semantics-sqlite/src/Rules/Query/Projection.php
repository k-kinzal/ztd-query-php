<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Rules\Query;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Rules\Resolution\ColumnFacts;
use SqlSemantics\Platform\Sqlite\Rules\Resolution\ColumnResolver;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Grouped;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Star;
use SqlSemantics\Platform\Sqlite\Statement\Query\TableStar;
use SqlSemantics\Platform\Sqlite\Statement\Type\Vector;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Reference\Table\MissingTable;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Shape\OpenStar;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Type\Known;

/**
 * Derives the output fields of a result column list.
 *
 * Rule: SQLITE-PROJECTION-001. A result column is named by
 * SQLITE-RESULT-NAME-001 for the rows a statement returns: by its alias, by
 * the column a column reference denotes, or by the span of its expression.
 * `*` contributes every column of every input relation except the columns
 * merged away by USING and NATURAL joins; `t.*` contributes every column of the relations the
 * qualifier names. A relation whose columns are not all known contributes
 * its known columns and an open star that names the missing inputs. A star
 * without any input relation and a qualifier that names no input relation
 * are reported, as is a result column that is a row value, which has no
 * single value. Terminates: one pass over the list.
 * Source: https://sqlite.org/c3ref/column_name.html,
 * https://sqlite.org/lang_select.html#generation_of_the_set_of_result_rows.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class Projection
{
    /**
     * Derives the result columns where the given environment is visible.
     *
     * @param list<ResultColumn|Star|TableStar> $columns
     * @return list<Field|OpenStar>
     */
    public function items(array $columns, Derivation $derivation, Environment $environment): array
    {
        $items = [];
        foreach ($columns as $column) {
            if ($column instanceof ResultColumn) {
                $fact = $derivation->scalar($column->expression, $environment);
                if ($fact->type instanceof Known && $fact->type->descriptor instanceof Vector) {
                    $derivation->report(new Misuse(MisuseRule::TooManyValueColumns));
                }
                $resolution = $column->expression instanceof Grouped ? (new ColumnFacts())->denoted($column->expression, $environment) : $fact->resolution;
                $origin = $resolution instanceof ResolvedColumn ? $resolution->slot : null;
                $items[] = new Field(count($items), new OutputSlot((new ResultNames())->output($column, $resolution), $fact->type, $fact->nullability, null, $origin), $column->expression, $resolution);
                continue;
            }
            $selected = [];
            foreach ($environment->relations as $relation) {
                if ((new ColumnResolver())->admits($environment, $relation, $column instanceof TableStar ? new QualifiedName($column->table) : null)) {
                    $selected[] = $relation;
                }
            }
            if ($selected === [] && ($column instanceof TableStar || $environment->relations === [])) {
                $derivation->report($column instanceof TableStar ? new MissingTable(new QualifiedName($column->table)) : new Misuse(MisuseRule::StarWithoutTables));
            }
            foreach ($selected as $relation) {
                $items = $this->expand($items, $relation, $column instanceof Star);
            }
        }

        return $items;
    }

    /**
     * Appends the columns a star selects from one relation.
     *
     * @param list<Field|OpenStar> $items
     * @return list<Field|OpenStar>
     */
    public function expand(array $items, VisibleRelation $relation, bool $merged): array
    {
        foreach ($relation->shape->slots as $position => $slot) {
            if (!$merged || !in_array($position, $relation->hidden, true)) {
                $items[] = new Field(count($items), new OutputSlot($slot->name, $slot->type, $slot->nullability, null, $slot), null, new ResolvedColumn($relation->relation, $slot));
            }
        }
        if (!$relation->shape->complete()) {
            $items[] = new OpenStar($relation->shape->missing);
        }

        return $items;
    }
}
