<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation\Xml;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Invocation\XmlChecks;
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

/**
 * `XMLELEMENT (NAME name [, XMLATTRIBUTES (attributes)] [, content])`: an XML element.
 *
 * Mirrors PostgreSQL's `XmlExpr` of kind `IS_XMLELEMENT`. Rule:
 * PG-XMLELEMENT-001. Facts: `xml`, not NULL. Diagnostics: an unnamed
 * attribute that is not a column reference, and an attribute name used
 * twice (PG-XML-CHECKS-001). The result column is named `xmlelement`.
 * Source: https://www.postgresql.org/docs/17/functions-xml.html#FUNCTIONS-PRODUCING-XML-XMLELEMENT. Status: Implemented.
 *
 * @visibility public
 * @example Reading an empty element
 *     $element = new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Xml\XmlElement(new \SqlSemantics\Statement\Identifier\Name('br'));
 *     [$element->name->value, $element->attributes, $element->content] // => ['br', [], []]
 */
final class XmlElement implements Scalar, OutputNaming
{
    use Snapshot;

    /**
     * @var list<XmlAttribute> The attributes; none when XMLATTRIBUTES is not written
     */
    public readonly array $attributes;

    /**
     * @var list<Scalar> The content values
     */
    public readonly array $content;

    /**
     * @param Name $name The element name
     * @param list<XmlAttribute> $attributes The attributes; none when XMLATTRIBUTES is not written
     * @param list<Scalar> $content The content values
     */
    public function __construct(public readonly Name $name, array $attributes = [], array $content = [])
    {
        $this->attributes = Check::listOf($attributes, XmlAttribute::class, 'XML attributes are attribute values.');
        $this->content = Check::listOf($content, Scalar::class, 'XML element content is a list of values.');
    }

    /**
     * Names an unaliased result column.
     */
    public function outputName(): Name
    {
        return new Name('xmlelement');
    }

    /**
     * Derives the attributes and the content, and reports misnamed attributes.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        foreach ($this->attributes as $attribute) {
            $attribute->deriveClause($derivation, $environment);
        }
        foreach ($this->content as $value) {
            $derivation->scalar($value, $environment);
        }
        (new XmlChecks())->attributes($derivation, $this->attributes);

        return new ScalarFact(new Known(Builtin::Xml), Nullability::NotNull);
    }

    /**
     * Writes XMLELEMENT with the name, the attributes and the content.
     */
    public function render(Output $out): void
    {
        $out->keyword('XMLELEMENT')->glue()->symbol('(')->keyword('NAME')->name($this->name, NameUse::Label);
        if ($this->attributes !== []) {
            $out->symbol(',')->keyword('XMLATTRIBUTES')->glue()->symbol('(')->list($this->attributes)->symbol(')');
        }
        if ($this->content !== []) {
            $out->symbol(',')->list($this->content);
        }
        $out->symbol(')');
    }
}
