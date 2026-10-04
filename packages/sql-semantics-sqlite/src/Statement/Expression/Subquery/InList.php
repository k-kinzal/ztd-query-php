<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Expression\Subquery;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Rules\Expression\Precedence;
use SqlSemantics\Platform\Sqlite\Rules\Expression\RowValues;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

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
 * @visibility public
 * @example Reading the values of a membership test
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT a NOT IN (1, 2) FROM t');
 *     [$query->statement->columns[0]->expression->negated, count($query->statement->columns[0]->expression->items)] // => [true, 2]
 * @example Refusing an operand that the test would not cover when written
 *     $or = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT a OR b FROM t')->statement->columns[0]->expression;
 *     new \SqlSemantics\Platform\Sqlite\Statement\Expression\Subquery\InList($or, []) // throws \SqlSemantics\Diagnostic\InvalidConstruction
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
        $operand = $derivation->scalar($this->operand, $environment);
        $nullability = $operand->nullability;
        $elements = [];
        foreach ($this->items as $item) {
            $elements[] = $derivation->scalar($item, $environment);
            $nullability = $nullability->propagate($elements[count($elements) - 1]->nullability);
        }
        (new RowValues())->elements($operand, $elements, $derivation);

        return new ScalarFact(new Known(Storage::Integer), $this->items === [] ? Nullability::NotNull : $nullability);
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
