<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Resolution\Environment;
use SqlSemantics\Platform\MySql\Model\Statement\Fact\ScalarFact;
use SqlSemantics\Platform\MySql\Model\Statement\Scalar;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Known;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Nullability;

/**
 * A row constructor: `(expr, expr …)` or `ROW(expr, expr …)` (`Item_row`).
 *
 * The keyword ROW is optional and does not change the row, so it is not
 * part of the structure; the row is written in parentheses.
 *
 * Rule: MYSQL-ROW-001. Facts: a row of as many columns as elements; it can
 * be NULL when an element can. A row may appear only where a comparison,
 * IN or a row subquery takes it; elsewhere MYSQL-OPERAND-COLUMNS-001 reports
 * it. Terminates: the elements are strict parts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/row-subqueries.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class Row implements Scalar
{
    use Snapshot;

    /**
     * @var non-empty-list<Scalar> The elements in order; at least two
     */
    public readonly array $elements;

    /**
     * @param list<Scalar> $elements The elements in order; at least two
     * @param OptionalWords $keyword Whether the optional ROW keyword is written
     */
    public function __construct(array $elements, public readonly OptionalWords $keyword = OptionalWords::Omitted)
    {
        $this->elements = Check::listOf($elements, Scalar::class, 'A row constructor has at least two elements.', 2);
    }

    /**
     * Derives every element and the row.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $nullability = Nullability::NotNull;
        foreach ($this->elements as $element) {
            $nullability = $nullability->propagate($derivation->scalar($element, $environment)->nullability);
        }

        return new ScalarFact(new Known(new Tuple(count($this->elements))), $nullability);
    }

    /**
     * Writes the ROW keyword when it is written and the elements in parentheses.
     */
    public function render(Output $out): void
    {
        if ($this->keyword === OptionalWords::Written) {
            $out->keyword('ROW')->glue();
        }
        $out->symbol('(')->list($this->elements)->symbol(')');
    }
}
