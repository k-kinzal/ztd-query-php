<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Expression\Predicate;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\ScalarFact;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\OperandChecks;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Precedence;
use SqlSemantics\Platform\PostgreSql\Statement\Option\NormalForm;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;

/**
 * A test of whether a string is in a Unicode normal form: `a IS [NOT] [NFC|NFD|NFKC|NFKD] NORMALIZED`.
 *
 * The parser reads it as a call of `pg_catalog.is_normalized(a, form)`; the
 * form defaults to NFC.
 *
 * Rule: PG-NORMALIZATION-TEST-001. Facts: `boolean` for a text operand (or a
 * string constant read as text), NULL when the operand can be; any other
 * operand type is reported. The operand must keep its place before IS
 * (PG-PRECEDENCE-001).
 * Source: https://www.postgresql.org/docs/17/functions-string.html#FUNCTIONS-STRING-SQL. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class NormalizationTest implements Scalar
{
    use Snapshot;

    /**
     * @param Scalar $operand The tested string
     * @param bool $negated Whether NOT is written
     * @param NormalForm|null $form The normal form, or null when none is written
     */
    public function __construct(public readonly Scalar $operand, public readonly bool $negated, public readonly ?NormalForm $form = null)
    {
        Check::input((new Precedence())->before($operand, Precedence::IS), 'The tested value needs parentheses to keep its place.');
    }

    /**
     * Derives the operand and checks that it is text.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $operand = $derivation->scalar($this->operand, $environment);
        $checks = new OperandChecks();

        return new ScalarFact($checks->accepted($derivation, $operand->type, [Builtin::Text, Builtin::Varchar, Builtin::Bpchar, Builtin::Name], 'IS NORMALIZED'), $checks->nullability([$operand]));
    }

    /**
     * Writes the value and the test.
     */
    public function render(Output $out): void
    {
        $out->node($this->operand)->keyword('IS');
        if ($this->negated) {
            $out->keyword('NOT');
        }
        if ($this->form !== null) {
            $out->keyword($this->form->value);
        }
        $out->keyword('NORMALIZED');
    }
}
