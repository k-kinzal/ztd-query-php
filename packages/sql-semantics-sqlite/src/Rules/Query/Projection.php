<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Rules\Query;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Rules\Resolution\ColumnResolver;
use SqlSemantics\Platform\Sqlite\Statement\Expression\ColumnUse;
use SqlSemantics\Platform\Sqlite\Statement\Expression\DoubleQuotedWord;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Grouped;
use SqlSemantics\Platform\Sqlite\Statement\Expression\TruthWord;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Star;
use SqlSemantics\Platform\Sqlite\Statement\Query\TableStar;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Column\AliasTarget;
use SqlSemantics\Statement\Reference\Column\ConditionalColumn;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Reference\Table\MissingTable;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Shape\OpenStar;
use SqlSemantics\Statement\Shape\OutputSlot;

/**
 * Derives the output fields of a result column list.
 *
 * Rule: SQLITE-RESULT-NAME-001. A result column with an alias is named by
 * the alias. A result column that is a column reference, possibly in
 * parentheses, is named after the column it denotes. Every other result
 * column is named by SQLite after the source text of its expression, which
 * the model does not keep: its name is not fixed. `*` contributes every
 * column of every input relation except the columns merged away by USING and
 * NATURAL joins; `t.*` contributes every column of the relations the
 * qualifier names. A relation whose columns are not all known contributes
 * its known columns and an open star that names the missing inputs. A star
 * without any input relation and a qualifier that names no input relation
 * are reported. Terminates: one pass over the list.
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
                $origin = $fact->resolution instanceof ResolvedColumn ? $fact->resolution->slot : null;
                $items[] = new Field(count($items), new OutputSlot($column->alias ?? $this->named($column, $fact), $fact->type, $fact->nullability, null, $origin), $column->expression, $fact->resolution);
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

    /**
     * Answers the fixed name of a result column without an alias, or null when SQLite names it after its source text.
     */
    public function named(ResultColumn $column, ScalarFact $fact): ?Name
    {
        $expression = $column->expression;
        while ($expression instanceof Grouped) {
            $expression = $expression->operand;
        }
        if (!$expression instanceof ColumnUse && !$expression instanceof DoubleQuotedWord && !$expression instanceof TruthWord) {
            return null;
        }
        if ($fact->resolution instanceof ResolvedColumn) {
            return $fact->resolution->slot->name;
        }
        if ($expression instanceof ColumnUse) {
            return $fact->resolution instanceof AliasTarget ? $fact->resolution->field->name : $expression->name;
        }

        return $expression instanceof DoubleQuotedWord && $fact->resolution instanceof ConditionalColumn ? $expression->word : null;
    }
}
