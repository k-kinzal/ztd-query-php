<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Rules\Expression;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\ArityMismatch;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\ArityRule;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\Sqlite\Statement\Type\Vector;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Known;

/**
 * Reports the places where a row value is used as a single value, or where two row widths disagree.
 *
 * Rule: SQLITE-ROW-VALUE-USE-001. A row value is admitted only where SQLite
 * compares rows: on both sides of a comparison or IS operator, in BETWEEN
 * when the bounds are rows of the same width, as the operand of IN against
 * elements, rows or a relation of the same width, and as the base of a CASE
 * with WHEN values of the same width. Anywhere else a row value is misused:
 * as an operand of any other operator, as a condition, a grouping or
 * ordering term, or a result column. The width of a value whose type
 * depends on missing inputs is unknown and nothing is reported for it.
 * Terminates: a fixed number of tests per use.
 * Source: https://sqlite.org/rowvalue.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class RowValues
{
    /**
     * Answers the number of values a fact stands for: 1 for a single value, the row width for a row value, null when unknown.
     */
    public function width(ScalarFact $fact): ?int
    {
        if ($fact->type instanceof Dependent || $fact->type instanceof Invalid) {
            return null;
        }

        return $fact->type instanceof Known && $fact->type->descriptor instanceof Vector ? $fact->type->descriptor->width : 1;
    }

    /**
     * Reports a row value at a position that takes a single value, and answers the reported problem.
     */
    public function single(ScalarFact $fact, Derivation $derivation): ?Misuse
    {
        if (($this->width($fact) ?? 1) === 1) {
            return null;
        }
        $problem = new Misuse(MisuseRule::TooManyValueColumns);
        $derivation->report($problem);

        return $problem;
    }

    /**
     * Reports values compared with each other whose known widths disagree.
     *
     * @param list<ScalarFact> $facts
     */
    public function uniform(array $facts, Derivation $derivation): void
    {
        $widths = [];
        foreach ($facts as $fact) {
            $width = $this->width($fact);
            if ($width !== null && !in_array($width, $widths, true)) {
                $widths[] = $width;
            }
        }
        if (count($widths) < 2) {
            return;
        }
        $derivation->report(in_array(1, $widths, true) ? new Misuse(MisuseRule::TooManyValueColumns) : new ArityMismatch(ArityRule::RowComparison, $widths[0], $widths[1]));
    }

    /**
     * Reports an IN operand whose width differs from the number of columns of the rows it is compared with.
     */
    public function membership(ScalarFact $operand, int $columns, Derivation $derivation): void
    {
        $width = $this->width($operand);
        if ($width !== null && $width !== $columns) {
            $derivation->report(new ArityMismatch(ArityRule::ScalarSubquery, $width, $columns));
        }
    }

    /**
     * Reports the first element of an IN list whose known width differs from that of the operand.
     *
     * @param list<ScalarFact> $elements
     */
    public function elements(ScalarFact $operand, array $elements, Derivation $derivation): void
    {
        $width = $this->width($operand);
        foreach ($elements as $element) {
            $actual = $this->width($element);
            if ($width !== null && $actual !== null && $actual !== $width) {
                $derivation->report(new ArityMismatch(ArityRule::InListElement, $width, $actual));

                return;
            }
        }
    }
}
