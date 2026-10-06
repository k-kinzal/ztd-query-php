<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Server;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\Histogram;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\UpdateHistogram;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\BucketCountOutOfRange;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\HistogramTables;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\UnknownHistogramColumn;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Checks the histogram request of ANALYZE TABLE against the table it names.
 *
 * Rule: MYSQL-HISTOGRAM-001. A histogram request applies to exactly one
 * table; more than one is HistogramTables. A column name the table's
 * complete shape does not hold, compared under the context's column name
 * comparison, is UnknownHistogramColumn; an open shape (an undeclared table
 * or incomplete members) reports nothing. A bucket count outside 1 to 1024
 * (MAX_NUMBER_OF_HISTOGRAM_BUCKETS) is BucketCountOutOfRange. Terminates:
 * one pass over the columns and the slots.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/analyze-table.html,
 * https://dev.mysql.com/doc/refman/8.4/en/optimizer-statistics.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Histograms
{
    /**
     * Reports the problems of a histogram request.
     *
     * @param list<RelationFact> $tables The facts of the tables the statement names, in order
     */
    public function derive(Derivation $derivation, Histogram $histogram, array $tables): void
    {
        if ($histogram instanceof UpdateHistogram && $histogram->buckets !== null && !$this->buckets($histogram->buckets)) {
            $derivation->report(new BucketCountOutOfRange($histogram->buckets));
        }
        if (count($tables) !== 1) {
            $derivation->report(new HistogramTables(count($tables)));

            return;
        }
        $shape = $tables[0]->shape;
        if ($shape->missing !== []) {
            return;
        }
        foreach ($histogram->columns() as $column) {
            $found = false;
            foreach ($shape->slots as $slot) {
                $found = $found || ($slot->name !== null && $derivation->context->columnNames->equal($slot->name->value, $column->value));
            }
            if (!$found) {
                $derivation->report(new UnknownHistogramColumn($column));
            }
        }
    }

    /**
     * Tells whether a bucket count is within 1 to 1024.
     */
    public function buckets(Numeral $buckets): bool
    {
        $digits = ltrim($buckets->text, '0');

        return $digits !== '' && (strlen($digits) < 4 || (strlen($digits) === 4 && strcmp($digits, '1024') <= 0));
    }

    /**
     * Writes the column names separated by commas.
     *
     * @param list<Name> $columns
     */
    public function render(Output $out, array $columns): void
    {
        foreach ($columns as $position => $column) {
            if ($position > 0) {
                $out->symbol(',');
            }
            $out->name($column, NameUse::Column);
        }
    }
}
