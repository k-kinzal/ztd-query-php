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
use SqlSemantics\Platform\PostgreSql\Rules\Invocation\XmlChecks;
use SqlSemantics\Platform\PostgreSql\Statement\OutputNaming;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;

/**
 * `XMLFOREST (values)`: a sequence of XML elements, one per value.
 *
 * Mirrors PostgreSQL's `XmlExpr` of kind `IS_XMLFOREST`. Rule:
 * PG-XMLFOREST-001. Facts: `xml`; NULL values are omitted
 * (PG-COALESCING-NULL-001). Diagnostics: an unnamed value that is not a
 * column reference (PG-XML-CHECKS-001). The result column is named
 * `xmlforest`.
 * Source: https://www.postgresql.org/docs/17/functions-xml.html#FUNCTIONS-PRODUCING-XML-XMLFOREST. Status: Implemented.
 *
 * @visibility SqlSemantics
 * @example Naming the result column
 *     (new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Xml\XmlForest([new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Xml\XmlAttribute(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral(), new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('a'))]))->outputName()->value // => 'xmlforest'
 */
final class XmlForest implements Scalar, OutputNaming
{
    use Snapshot;

    /**
     * @var non-empty-list<XmlAttribute> The values and their element names
     */
    public readonly array $elements;

    /**
     * @param list<XmlAttribute> $elements The values and their element names; at least one
     */
    public function __construct(array $elements)
    {
        $this->elements = Check::listOf($elements, XmlAttribute::class, 'XMLFOREST takes at least one value.', 1);
    }

    /**
     * Names an unaliased result column.
     */
    public function outputName(): Name
    {
        return new Name('xmlforest');
    }

    /**
     * Derives the values, reports unnamed values, and derives the type and NULL fact.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $facts = [];
        foreach ($this->elements as $element) {
            $facts[] = $derivation->scalar($element->value, $environment);
        }
        (new XmlChecks())->elements($derivation, $this->elements);

        return new ScalarFact(new Known(Builtin::Xml), (new Coalescing())->nullability($facts));
    }

    /**
     * Writes XMLFOREST with the values.
     */
    public function render(Output $out): void
    {
        $out->keyword('XMLFOREST')->glue()->symbol('(')->list($this->elements)->symbol(')');
    }
}
