<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Expression;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Rules\Expression\Precedence;
use SqlSemantics\Platform\Sqlite\Rules\Expression\RowValueUse;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Invalid;

/**
 * An expression with an explicit collating sequence for the comparisons it takes part in.
 *
 * Rule: SQLITE-COLLATE-001. The operator changes no value: the type and
 * the NULL fact are those of the operand, and the name resolution stays with
 * the operand. The operand is a single value (SQLITE-ROW-VALUE-USE-001): a collated
 * row value is invalid. The operand must bind at least as tightly as COLLATE
 * (SQLITE-PRECEDENCE-001).
 * Source: https://sqlite.org/lang_expr.html#collate_operator,
 * https://sqlite.org/datatype3.html#collation. Status: Implemented.
 *
 * @visibility public
 * @example Reading the collation of an expression
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT a COLLATE NOCASE FROM t');
 *     $query->statement->columns[0]->expression->collation->value // => 'NOCASE'
 * @example Refusing an operand that would be read back differently
 *     $sum = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT 1 + 2')->statement->columns[0]->expression;
 *     new \SqlSemantics\Platform\Sqlite\Statement\Expression\Collate($sum, new \SqlSemantics\Statement\Identifier\Name('NOCASE')) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class Collate implements Scalar
{
    use Snapshot;

    /**
     * @param Scalar $operand The collated expression
     * @param Name $collation The collating sequence name
     */
    public function __construct(public readonly Scalar $operand, public readonly Name $collation)
    {
        Check::input((new Precedence())->closing($operand) >= Precedence::COLLATION, 'The operand of COLLATE needs parentheses to keep its place.');
    }

    /**
     * Derives the operand; the collation changes no value.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $fact = $derivation->scalar($this->operand, $environment);
        $problem = (new RowValueUse())->single($fact, $derivation);
        if ($problem !== null) {
            return new ScalarFact(new Invalid($problem), $fact->nullability);
        }

        return new ScalarFact($fact->type, $fact->nullability);
    }

    /**
     * Writes the operand and the collation.
     */
    public function render(Output $out): void
    {
        $out->node($this->operand)->keyword('COLLATE')->name($this->collation, NameUse::Label);
    }
}
