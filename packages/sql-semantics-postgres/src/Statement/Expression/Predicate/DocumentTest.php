<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Expression\Predicate;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\OperandChecks;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Precedence;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * A test of whether an XML value is a document: `a IS [NOT] DOCUMENT`.
 *
 * Mirrors PostgreSQL's `XmlExpr` node of kind `IS_DOCUMENT` (negated by a `NOT`).
 *
 * Rule: PG-DOCUMENT-TEST-001. Facts: `boolean` when the operand is `xml` or
 * an unknown-typed constant read as `xml`, NULL when the operand can be; any
 * other operand type is reported. The operand must keep its place before IS
 * (PG-PRECEDENCE-001).
 * Source: https://www.postgresql.org/docs/17/functions-xml.html#FUNCTIONS-XML-PREDICATES. Status: Implemented.
 *
 * @visibility public
 * @example Testing an XML constant
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze("SELECT '<a/>' IS DOCUMENT")->field(0)->type->descriptor->name() // => 'boolean'
 */
final class DocumentTest implements Scalar
{
    use Snapshot;

    /**
     * @param Scalar $operand The tested XML value
     * @param bool $negated Whether NOT is written
     */
    public function __construct(public readonly Scalar $operand, public readonly bool $negated)
    {
        Check::input((new Precedence())->before($operand, Precedence::IS), 'The tested value needs parentheses to keep its place.');
    }

    /**
     * Derives the operand and checks that it is XML.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $operand = $derivation->scalar($this->operand, $environment);
        $checks = new OperandChecks();

        return new ScalarFact($checks->accepted($derivation, $operand->type, [Builtin::Xml], 'IS DOCUMENT'), $checks->nullability([$operand]));
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
        $out->keyword('DOCUMENT');
    }
}
