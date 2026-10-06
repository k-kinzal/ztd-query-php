<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation\Xml;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Option\XmlOption;
use SqlSemantics\Platform\PostgreSql\Statement\OutputNaming;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;
use SqlSemantics\Statement\Type\NullOnly;

/**
 * `XMLPARSE ({DOCUMENT | CONTENT} value [PRESERVE | STRIP WHITESPACE])`: a string parsed as XML.
 *
 * Mirrors PostgreSQL's `XmlExpr` of kind `IS_XMLPARSE`. Rule:
 * PG-XMLPARSE-001. Facts: `xml`, NULL exactly when the value is. The result
 * column is named `xmlparse`.
 * Source: https://www.postgresql.org/docs/17/functions-xml.html#FUNCTIONS-PRODUCING-XML-XMLPARSE. Status: Implemented.
 *
 * @visibility public
 * @example Reading a document parse
 *     $parse = new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Xml\XmlParse(\SqlSemantics\Platform\PostgreSql\Statement\Option\XmlOption::Document, new \SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral());
 *     [$parse->option->value, $parse->whitespace] // => ['DOCUMENT', null]
 */
final class XmlParse implements Scalar, OutputNaming
{
    use Snapshot;

    /**
     * @param XmlOption $option Whether a document or content is parsed
     * @param Scalar $value The string parsed
     * @param XmlWhitespace|null $whitespace The whitespace handling written
     */
    public function __construct(public readonly XmlOption $option, public readonly Scalar $value, public readonly ?XmlWhitespace $whitespace = null)
    {
    }

    /**
     * Names an unaliased result column.
     */
    public function outputName(): Name
    {
        return new Name('xmlparse');
    }

    /**
     * Derives the value, the type and the NULL fact.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $value = $derivation->scalar($this->value, $environment);

        return new ScalarFact(new Known(Builtin::Xml), $value->type instanceof NullOnly ? Nullability::Nullable : $value->nullability);
    }

    /**
     * Writes XMLPARSE with the option, the value and the whitespace handling.
     */
    public function render(Output $out): void
    {
        $out->keyword('XMLPARSE')->glue()->symbol('(')->keyword($this->option->value)->node($this->value);
        if ($this->whitespace !== null) {
            $out->keyword($this->whitespace->value, 'WHITESPACE');
        }
        $out->symbol(')');
    }
}
