<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation\Xml;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\ScalarFact;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Known;
use SqlSemantics\Platform\PostgreSql\Rules\Invocation\Coalescing;
use SqlSemantics\Platform\PostgreSql\Statement\OutputNaming;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;

/**
 * `XMLCONCAT (values)`: XML values concatenated.
 *
 * Mirrors PostgreSQL's `XmlExpr` of kind `IS_XMLCONCAT`. Rule:
 * PG-XMLCONCAT-001. Facts: `xml`; NULL values are omitted, so the NULL rule
 * is PG-COALESCING-NULL-001. The result column is named `xmlconcat`.
 * Source: https://www.postgresql.org/docs/17/functions-xml.html#FUNCTIONS-PRODUCING-XML-XMLCONCAT. Status: Implemented.
 *
 * @visibility SqlSemantics
 * @example Naming the result column
 *     (new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Xml\XmlConcat([new \SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral()]))->outputName()->value // => 'xmlconcat'
 */
final class XmlConcat implements Scalar, OutputNaming
{
    use Snapshot;

    /**
     * @var non-empty-list<Scalar> The values in order
     */
    public readonly array $values;

    /**
     * @param list<Scalar> $values The values in order; at least one
     */
    public function __construct(array $values)
    {
        $this->values = Check::listOf($values, Scalar::class, 'XMLCONCAT takes at least one value.', 1);
    }

    /**
     * Names an unaliased result column.
     */
    public function outputName(): Name
    {
        return new Name('xmlconcat');
    }

    /**
     * Derives the values, the type and the NULL fact.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $facts = [];
        foreach ($this->values as $value) {
            $facts[] = $derivation->scalar($value, $environment);
        }

        return new ScalarFact(new Known(Builtin::Xml), (new Coalescing())->nullability($facts));
    }

    /**
     * Writes XMLCONCAT with the values.
     */
    public function render(Output $out): void
    {
        $out->keyword('XMLCONCAT')->glue()->symbol('(')->list($this->values)->symbol(')');
    }
}
