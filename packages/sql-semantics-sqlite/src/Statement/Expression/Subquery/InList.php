<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Expression\Subquery;

use SqlSemantics\Platform\Sqlite\Model\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Rendering\Output;
use SqlSemantics\Platform\Sqlite\Model\Resolution\Environment;
use SqlSemantics\Platform\Sqlite\Model\Statement\Fact\ScalarFact;
use SqlSemantics\Platform\Sqlite\Model\Statement\Scalar;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Known;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\Sqlite\Rules\Expression\Precedence;
use SqlSemantics\Platform\Sqlite\Rules\Expression\RowValueUse;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;

/**
 * A membership test against a written list of values, optionally negated.
 *
 * Rule: SQLITE-IN-LIST-001. The result is INTEGER. It is NULL when the
 * operand is NULL or when no value matches and a value is NULL; with an
 * empty list it is never NULL. Every value has the width of the operand
 * (SQLITE-ROW-VALUE-USE-001). The operand may not end in an operator weaker
 * than the equality group (SQLITE-PRECEDENCE-001).
 * Source: https://sqlite.org/lang_expr.html#the_in_and_not_in_operators.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class InList implements Scalar
{
    use Snapshot;

    /**
     * @var list<Scalar> The values in order
     */
    public readonly array $items;

    /**
     * @param Scalar $operand The tested expression
     * @param list<Scalar> $items The values in order; possibly none
     * @param bool $negated Whether NOT is written before IN
     */
    public function __construct(public readonly Scalar $operand, array $items, public readonly bool $negated = false)
    {
        $this->items = Check::listOf($items, Scalar::class, 'The values of IN are expressions.');
        Check::input((new Precedence())->closing($operand) >= Precedence::EQUALITY, 'The operand needs parentheses to keep its place.');
    }

    /**
     * Derives the operand, the values and the result.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        if ($this->items === []) {
            return new ScalarFact(new Known(Storage::Integer), Nullability::NotNull);
        }
        $operand = $derivation->scalar($this->operand, $environment);
        $nullability = $operand->nullability;
        $elements = [];
        foreach ($this->items as $item) {
            $elements[] = $derivation->scalar($item, $environment);
            $nullability = $nullability->propagate($elements[count($elements) - 1]->nullability);
        }
        (new RowValueUse())->elements($operand, $elements, $derivation);

        return new ScalarFact(new Known(Storage::Integer), $nullability);
    }

    /**
     * Writes the test.
     */
    public function render(Output $out): void
    {
        $out->node($this->operand);
        if ($this->negated) {
            $out->keyword('NOT');
        }
        $out->keyword('IN')->symbol('(')->list($this->items)->symbol(')');
    }
}
