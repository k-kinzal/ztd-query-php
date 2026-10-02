<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Expression;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * An expression written in parentheses.
 *
 * The grouping fixes the evaluation structure of its operand and is kept as
 * written; rendering never adds or removes a grouping.
 *
 * Rule: SQLITE-GROUPED-001. The facts, including the column a grouped column
 * use denotes, are those of the operand.
 * Source: https://sqlite.org/lang_expr.html. Status: Implemented.
 *
 * @visibility public
 * @example Keeping a grouping
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT (1 + 2) * 3')->toString() // => 'SELECT (1 + 2) * 3'
 */
final class Grouped implements Scalar
{
    use Snapshot;

    /**
     * @param Scalar $operand The grouped expression
     */
    public function __construct(public readonly Scalar $operand)
    {
    }

    /**
     * Derives the operand; the grouping has the same facts.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $fact = $derivation->scalar($this->operand, $environment);

        return new ScalarFact($fact->type, $fact->nullability, $fact->resolution);
    }

    /**
     * Writes the operand in parentheses.
     */
    public function render(Output $out): void
    {
        $out->symbol('(')->node($this->operand)->symbol(')');
    }
}
