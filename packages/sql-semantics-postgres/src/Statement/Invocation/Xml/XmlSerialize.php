<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation\Xml;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Rules\Invocation\XmlChecks;
use SqlSemantics\Platform\PostgreSql\Statement\Option\XmlOption;
use SqlSemantics\Platform\PostgreSql\Statement\OutputNaming;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Nullability;
use SqlSemantics\Statement\Type\NullOnly;

/**
 * `XMLSERIALIZE ({DOCUMENT | CONTENT} value AS type [[NO] INDENT])`: an XML value as a character string.
 *
 * Mirrors PostgreSQL's `XmlSerialize` node; the type is a simple type name
 * without an array part. Rule: PG-XMLSERIALIZE-001. Facts: the type named,
 * NULL exactly when the value is. Diagnostics: a type that is not a
 * character string type (PG-XML-CHECKS-001). The result column is named
 * `xmlserialize`.
 * Source: https://www.postgresql.org/docs/17/functions-xml.html#FUNCTIONS-XML-SERIALIZE. Status: Implemented.
 *
 * @visibility public
 * @example Reading a serialization
 *     $serialize = new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Xml\XmlSerialize(
 *         \SqlSemantics\Platform\PostgreSql\Statement\Option\XmlOption::Content,
 *         new \SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral(),
 *         new \SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName(new \SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\NamedDesignation(new \SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName([new \SqlSemantics\Statement\Identifier\Name('text')]))),
 *         \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Xml\XmlIndent::Indent,
 *     );
 *     [$serialize->option->value, $serialize->indent->value] // => ['CONTENT', 'INDENT']
 */
final class XmlSerialize implements Scalar, OutputNaming
{
    use Snapshot;

    /**
     * @param XmlOption $option Whether a document or content is serialized
     * @param Scalar $value The XML value
     * @param TypeName $type The character string type of the result
     * @param XmlIndent|null $indent The indentation written
     */
    public function __construct(public readonly XmlOption $option, public readonly Scalar $value, public readonly TypeName $type, public readonly ?XmlIndent $indent = null)
    {
    }

    /**
     * Names an unaliased result column.
     */
    public function outputName(): Name
    {
        return new Name('xmlserialize');
    }

    /**
     * Derives the value and the type's modifiers, the type and the NULL fact.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $value = $derivation->scalar($this->value, $environment);
        $this->type->deriveClause($derivation, $environment);
        $type = (new XmlChecks())->serialized($derivation, $this->type->typeFact($derivation->context));

        return new ScalarFact($type, $value->type instanceof NullOnly ? Nullability::Nullable : $value->nullability);
    }

    /**
     * Writes XMLSERIALIZE with the option, the value, the type and the indentation.
     */
    public function render(Output $out): void
    {
        $out->keyword('XMLSERIALIZE')->glue()->symbol('(')->keyword($this->option->value)->node($this->value)->keyword('AS')->node($this->type);
        if ($this->indent !== null) {
            $out->keyword(...explode(' ', $this->indent->value));
        }
        $out->symbol(')');
    }
}
