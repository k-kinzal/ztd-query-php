<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Expression;

use SqlSemantics\Platform\Sqlite\Model\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Rendering\Output;
use SqlSemantics\Platform\Sqlite\Model\Resolution\Environment;
use SqlSemantics\Platform\Sqlite\Model\Statement\Fact\ScalarFact;
use SqlSemantics\Platform\Sqlite\Model\Statement\Scalar;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Known;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\Sqlite\Statement\Type\Vector;

/**
 * A row value: two or more expressions in parentheses, compared or assigned as one.
 *
 * Rule: SQLITE-ROW-VALUE-001. The type is a row of the width written. A
 * comparison of row values is NULL when a deciding element is NULL, so the
 * NULL fact is that of the elements combined.
 * Source: https://sqlite.org/rowvalue.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 * @example Refusing a row of one element, which is a grouping
 *     new \SqlSemantics\Platform\Sqlite\Statement\Expression\RowExpression([new \SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\NullLiteral()]) // throws \SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction
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
