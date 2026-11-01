<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

/**
 * A row constructor: `(expr, expr …)` or `ROW(expr, expr …)` (`Item_row`).
 *
 * The keyword ROW is optional and does not change the row. Its explicit
 * spelling is retained by the keyword operand.
 *
 * Rule: MYSQL-ROW-001. Facts: a row of as many columns as elements; it can
 * be NULL when an element can. Comparisons and IN explicitly accept rows.
 * Stored program operand positions may retain them until a reached instruction
 * applies MYSQL-OPERAND-COLUMNS-001. Ordinary scalar positions require Scalar
 * and cannot accept a row. Terminates: the elements are strict parts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/row-subqueries.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Comparing two rows
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT a FROM t WHERE ROW(a, b) = (1, 2)');
 *     [count($query->query->where->left->elements), $query->toString()] // => [2, 'SELECT a FROM t WHERE ROW(a, b) = (1, 2)']
 */
final class Row implements RowOperand
{
    use \SqlSemantics\Statement\Semantics\ScalarSemantics;
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @var non-empty-list<Scalar|RowOperand> The elements in order; at least two
     */
    public readonly array $elements;

    /**
     * @param list<Scalar|RowOperand> $elements The elements in order; at least two
     * @param OptionalWords $keyword Whether the optional ROW keyword is written
     */
    public function __construct(array $elements, public readonly OptionalWords $keyword = OptionalWords::Omitted, ?ScalarFact $scalarInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        if ($scalarInformation !== null) {
            $this->initializeScalarInformation($scalarInformation);
        }
        $checked = Check::listOf($elements, \SqlSemantics\Statement\Operand::class, 'A row constructor has at least two elements.', 2);
        $accepted = [];
        foreach ($checked as $element) {
            Check::input($element instanceof Scalar || $element instanceof RowOperand, 'A row element is a scalar or another row.');
            $accepted[] = $element;
        }
        $this->elements = $accepted;
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
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
