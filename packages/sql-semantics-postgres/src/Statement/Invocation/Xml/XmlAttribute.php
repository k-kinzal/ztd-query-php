<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation\Xml;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;

/**
 * A value of XMLATTRIBUTES or XMLFOREST, optionally named with AS.
 *
 * Mirrors the `ResTarget` the grammar builds for `xml_attribute_el`. An
 * unnamed value takes the name of the column it references.
 * Source: https://www.postgresql.org/docs/17/functions-xml.html#FUNCTIONS-PRODUCING-XML-XMLELEMENT.
 *
 * @visibility SqlSemantics
 * @example Reading a named attribute
 *     $attribute = new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Xml\XmlAttribute(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral(), new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('id'));
 *     $attribute->name->value // => 'id'
 */
final class XmlAttribute implements Clause
{
    use Snapshot;

    /**
     * @param Scalar $value The value
     * @param Name|null $name The name written after AS
     */
    public function __construct(public readonly Scalar $value, public readonly ?Name $name = null)
    {
    }

    /**
     * Derives the value.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        $derivation->scalar($this->value, $environment);
    }

    /**
     * Writes the value and the name.
     */
    public function render(Output $out): void
    {
        $out->node($this->value);
        if ($this->name !== null) {
            $out->keyword('AS')->name($this->name, NameUse::Label);
        }
    }
}
