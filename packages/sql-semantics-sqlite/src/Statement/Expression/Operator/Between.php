<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Expression\Operator;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Rules\Expression\Precedence;
use SqlSemantics\Platform\Sqlite\Rules\Expression\RowValueUse;
use SqlSemantics\Platform\Sqlite\Rules\Typing\Storages;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * A range test: an operand between a low and a high bound, optionally negated.
 *
 * Rule: SQLITE-BETWEEN-001. The test is the pair of comparisons with the
 * operand evaluated once: INTEGER, NULL when a deciding operand is NULL. The
 * operand and the bounds are rows of one width (SQLITE-ROW-VALUE-USE-001). The
 * operand may not end in an operator weaker than the equality group and the
 * bounds may not start with an operator of that group or a weaker one
 * (SQLITE-PRECEDENCE-001).
 * Source: https://sqlite.org/lang_expr.html#the_between_operator. Status: Implemented.
 *
 * @visibility public
 * @example Reading the bounds of a range test
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT a NOT BETWEEN 1 AND 9 FROM t');
 *     $test = $query->statement->columns[0]->expression;
 *     [$test->negated, $test->low->digits, $test->high->digits] // => [true, '1', '9']
 * @example Refusing a bound that the rendered SQL would associate differently
 *     $or = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT a OR b FROM t')->statement->columns[0]->expression;
 *     new \SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Between(new \SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\NullLiteral(), $or, new \SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\NullLiteral()) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class Between implements Scalar
{
    use Snapshot;

    /**
     * @param Scalar $operand The tested expression
     * @param Scalar $low The low bound
     * @param Scalar $high The high bound
     * @param bool $negated Whether NOT is written before BETWEEN
     */
    public function __construct(public readonly Scalar $operand, public readonly Scalar $low, public readonly Scalar $high, public readonly bool $negated = false)
    {
        $precedence = new Precedence();
        Check::input($precedence->closing($operand) >= Precedence::EQUALITY, 'The tested operand needs parentheses to keep its place.');
        Check::input($precedence->opening($low) > Precedence::EQUALITY && $precedence->closing($low) > Precedence::CONJUNCTION, 'The low bound needs parentheses to keep its place.');
        Check::input($precedence->opening($high) > Precedence::EQUALITY, 'The high bound needs parentheses to keep its place.');
    }

    /**
     * Derives the operands and the result of the comparisons.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $operand = $derivation->scalar($this->operand, $environment);
        $low = $derivation->scalar($this->low, $environment);
        $high = $derivation->scalar($this->high, $environment);
        (new RowValueUse())->uniform([$operand, $low, $high], $derivation);

        return new ScalarFact((new Storages())->strict(Storage::Integer, [$operand->type]), $operand->nullability->propagate($low->nullability)->propagate($high->nullability));
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
        $out->keyword('BETWEEN')->node($this->low)->keyword('AND')->node($this->high);
    }
}
