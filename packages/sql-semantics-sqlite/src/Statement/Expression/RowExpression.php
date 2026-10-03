<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Expression;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Statement\Type\Vector;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

/**
 * A row value: two or more expressions in parentheses, compared or assigned as one.
 *
 * Rule: SQLITE-ROW-VALUE-001. The type is a row of the width written. A
 * comparison of row values is NULL when a deciding element is NULL, so the
 * NULL fact is that of the elements combined.
 * Source: https://sqlite.org/rowvalue.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading the elements of a row value
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT (a, b) = (1, 2) FROM t');
 *     count($query->statement->columns[0]->expression->left->items) // => 2
 * @example Refusing a row of one element, which is a grouping
 *     new \SqlSemantics\Platform\Sqlite\Statement\Expression\RowExpression([new \SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\NullLiteral()]) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class RowExpression implements Scalar
{
    use Snapshot;

    /**
     * @var non-empty-list<Scalar> The elements in order
     */
    public readonly array $items;

    /**
     * @param list<Scalar> $items The elements in order; at least two
     */
    public function __construct(array $items)
    {
        $this->items = Check::listOf($items, Scalar::class, 'A row value has at least two elements.', 2);
    }

    /**
     * Derives every element and the row type.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $nullability = Nullability::NotNull;
        foreach ($this->items as $item) {
            $nullability = $nullability->propagate($derivation->scalar($item, $environment)->nullability);
        }

        return new ScalarFact(new Known(new Vector(count($this->items))), $nullability);
    }

    /**
     * Writes the elements in parentheses.
     */
    public function render(Output $out): void
    {
        $out->symbol('(')->list($this->items)->symbol(')');
    }
}
