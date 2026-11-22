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
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Known;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Precedence;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;

/**
 * A test for NULL: `a IS [NOT] NULL`, `a ISNULL`, `a NOTNULL`.
 *
 * Mirrors PostgreSQL's `NullTest` node (`IS_NULL`, `IS_NOT_NULL`). For a row
 * value the test applies to every field.
 *
 * Rule: PG-NULL-TEST-001. Facts: `boolean`, never NULL. The operand must keep
 * its place before IS (PG-PRECEDENCE-001).
 * Source: https://www.postgresql.org/docs/17/functions-comparison.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class NullTest implements Scalar
{
    use Snapshot;

    /**
     * @param Scalar $operand The tested value
     * @param bool $negated Whether the test is for a value that is not NULL
     * @param NullTestSpelling $spelling How the test is written
     */
    public function __construct(public readonly Scalar $operand, public readonly bool $negated, public readonly NullTestSpelling $spelling = NullTestSpelling::Keywords)
    {
        Check::input((new Precedence())->before($operand, Precedence::IS), 'The tested value needs parentheses to keep its place.');
    }

    /**
     * Derives the operand; the test is a boolean that is never NULL.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $derivation->scalar($this->operand, $environment);

        return new ScalarFact(new Known(Builtin::Bool), Nullability::NotNull);
    }

    /**
     * Writes the value and the test.
     */
    public function render(Output $out): void
    {
        $out->node($this->operand);
        match ($this->spelling) {
            NullTestSpelling::Keywords => $this->negated ? $out->keyword('IS', 'NOT', 'NULL') : $out->keyword('IS', 'NULL'),
            NullTestSpelling::Postfix => $out->keyword($this->negated ? 'NOTNULL' : 'ISNULL'),
        };
    }
}
