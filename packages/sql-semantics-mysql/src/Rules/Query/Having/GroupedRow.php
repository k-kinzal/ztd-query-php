<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query\Having;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Reference\Column\ConditionalColumn;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Shape\OpenStar;
use SqlSemantics\Statement\Shape\RowShape;

/**
 * The entry of a HAVING environment that hands the select list and the GROUP BY columns to the names written there.
 *
 * Rule: MYSQL-HAVING-REFERENCE-001 (see `ResultReferences`). The entry
 * travels as a visible occurrence with neither alias nor name and without
 * any column, so no column lookup, qualifier or star can reach it; only
 * `HavingScope` and `ResultReferences` recognise it, by its class. It is a
 * working value of one derivation and never part of a statement.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/select.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class GroupedRow implements Relation
{
    /**
     * @param list<Field|OpenStar> $selected The output fields of the select list, in order
     * @param list<Field> $grouping The GROUP BY items that are columns, each with the resolution of its column at the HAVING position
     * @param bool $grouped Whether the query block groups, aggregates or is DISTINCT
     * @param list<ConditionalColumn> $undecided The GROUP BY items and select list items that are columns of an incompletely known occurrence
     */
    public function __construct(public readonly array $selected, public readonly array $grouping, public readonly bool $grouped, public readonly array $undecided = [])
    {
    }

    /**
     * Answers the row without columns the entry contributes.
     */
    public function deriveRelation(Derivation $derivation, Environment $environment): RelationFact
    {
        return new RelationFact(new RowShape([]));
    }

    /**
     * Writes nothing: the entry is not written.
     */
    public function render(Output $out): void
    {
    }
}
