<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation\Xml;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Precedence;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;

/**
 * The PASSING clause of XMLEXISTS and XMLTABLE: the document the path is evaluated on.
 *
 * The document is a primary expression (`c_expr`); an operand that would
 * need parentheses there is rejected. BY REF and BY VALUE are accepted and
 * ignored by the server, so they are not kept.
 * Source: https://www.postgresql.org/docs/17/functions-xml.html#FUNCTIONS-XML-PREDICATES-XMLEXISTS.
 *
 * @visibility SqlSemantics
 * @example Reading the document passed
 *     $passing = new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Xml\XmlPassing(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral());
 *     $passing->document instanceof \SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral // => true
 */
final class XmlPassing implements Clause
{
    use Snapshot;

    /**
     * @param Scalar $document The XML document
     */
    public function __construct(public readonly Scalar $document)
    {
        Check::input((new Precedence())->primary($document), 'The document passed is a primary expression; group it.');
    }

    /**
     * Derives the document.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        $derivation->scalar($this->document, $environment);
    }

    /**
     * Writes PASSING and the document.
     */
    public function render(Output $out): void
    {
        $out->keyword('PASSING')->node($this->document);
    }
}
