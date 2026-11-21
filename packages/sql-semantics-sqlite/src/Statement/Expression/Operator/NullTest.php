<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Expression\Operator;

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
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;

/**
 * A postfix test for NULL: ISNULL, NOTNULL or NOT NULL.
 *
 * Rule: SQLITE-NULL-TEST-001. The result is the INTEGER 0 or 1 and never
 * NULL. The operand may not end in an operator weaker than the equality
 * group (SQLITE-PRECEDENCE-001).
 * Source: https://sqlite.org/lang_expr.html#operators_and_parse_affecting_attributes.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading a NULL test
 *     $query = (new \SqlSemantics\Platform\Sqlite\Semantics())->analyze('SELECT a ISNULL FROM t');
 *     [$query->statement->columns[0]->expression->form, $query->field(0)->nullability] // => [\SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\NullTestForm::IsNull, \SqlSemantics\Platform\Sqlite\Model\Statement\Type\Nullability::NotNull]
 * @example Refusing an operand that the test would not cover when written
 *     $query = (new \SqlSemantics\Platform\Sqlite\Semantics())->analyze('SELECT a OR b FROM t');
 *     new \SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\NullTest($query->statement->columns[0]->expression, \SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\NullTestForm::IsNull) // throws \SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction
 */
final class NullTest implements Scalar
{
    use Snapshot;

    /**
     * @param Scalar $operand The tested expression
     * @param NullTestForm $form The spelling of the test
     */
    public function __construct(public readonly Scalar $operand, public readonly NullTestForm $form)
    {
        Check::input((new Precedence())->closing($operand) >= Precedence::EQUALITY, 'The operand needs parentheses to keep its place.');
    }

    /**
     * Derives the operand; the test is an INTEGER that is never NULL.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $derivation->scalar($this->operand, $environment);

        return new ScalarFact(new Known(Storage::Integer), Nullability::NotNull);
    }

    /**
     * Writes the operand and the test.
     */
    public function render(Output $out): void
    {
        $out->node($this->operand);
        match ($this->form) {
            NullTestForm::IsNull => $out->keyword('ISNULL'),
            NullTestForm::NotNull => $out->keyword('NOTNULL'),
            NullTestForm::NotNullWords => $out->keyword('NOT', 'NULL'),
        };
    }
}
