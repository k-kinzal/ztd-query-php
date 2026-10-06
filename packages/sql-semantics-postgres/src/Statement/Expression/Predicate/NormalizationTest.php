<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Expression\Predicate;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\OperandChecks;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Precedence;
use SqlSemantics\Platform\PostgreSql\Statement\Option\NormalForm;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

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
 * @visibility public
 * @example Reading the normal form of a test
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze("SELECT 'a' IS NOT NFKC NORMALIZED");
 *     [$query->field(0)->expression->form, $query->toString()] // => [\SqlSemantics\Platform\PostgreSql\Statement\Option\NormalForm::Nfkc, "SELECT 'a' IS NOT NFKC NORMALIZED"]
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
