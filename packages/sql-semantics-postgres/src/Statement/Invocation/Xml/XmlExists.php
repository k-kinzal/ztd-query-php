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
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Precedence;
use SqlSemantics\Platform\PostgreSql\Rules\Invocation\CallTyping;
use SqlSemantics\Platform\PostgreSql\Statement\OutputNaming;

/**
 * `XMLEXISTS (path PASSING document)`: whether an XPath expression returns any node.
 *
 * The server calls `pg_catalog.xmlexists(path, document)`; the path is a
 * primary expression (`c_expr`). Rule: PG-XMLEXISTS-001. Facts: those of the
 * call (PG-CALL-RESULT-001; `boolean`). The result column is named
 * `xmlexists`.
 * Source: https://www.postgresql.org/docs/17/functions-xml.html#FUNCTIONS-XML-PREDICATES-XMLEXISTS. Status: Implemented.
 *
 * @visibility SqlSemantics
 * @example Naming the result column
 *     $exists = new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Xml\XmlExists(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral(), new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Xml\XmlPassing(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral()));
 *     $exists->outputName()->value // => 'xmlexists'
 */
final class XmlExists implements Scalar, OutputNaming
{
    use Snapshot;

    /**
     * @param Scalar $path The XPath expression
     * @param XmlPassing $passing The document
     */
    public function __construct(public readonly Scalar $path, public readonly XmlPassing $passing)
    {
        Check::input((new Precedence())->primary($path), 'The path of XMLEXISTS is a primary expression; group it.');
    }

    /**
     * Names an unaliased result column after the function the server calls.
     */
    public function outputName(): Name
    {
        return new Name('xmlexists');
    }

    /**
     * Derives the path and the document and the result of `pg_catalog.xmlexists`.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $path = $derivation->scalar($this->path, $environment);
        $document = $derivation->scalar($this->passing->document, $environment);

        return (new CallTyping())->catalog($derivation->context, 'xmlexists', [$path, $document]);
    }

    /**
     * Writes XMLEXISTS with the path and the PASSING clause.
     */
    public function render(Output $out): void
    {
        $out->keyword('XMLEXISTS')->glue()->symbol('(')->node($this->path)->node($this->passing)->symbol(')');
    }
}
