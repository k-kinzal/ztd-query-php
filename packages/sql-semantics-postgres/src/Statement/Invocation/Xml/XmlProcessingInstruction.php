<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation\Xml;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
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
 * `XMLPI (NAME target [, content])`: an XML processing instruction.
 *
 * Mirrors PostgreSQL's `XmlExpr` of kind `IS_XMLPI`. Rule: PG-XMLPI-001.
 * Facts: `xml`; NULL exactly when the content is. The result column is named
 * `xmlpi`.
 * Source: https://www.postgresql.org/docs/17/functions-xml.html#FUNCTIONS-PRODUCING-XML-XMLPI. Status: Implemented.
 *
 * @visibility public
 * @example Reading a processing instruction without content
 *     $pi = new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Xml\XmlProcessingInstruction(new \SqlSemantics\Statement\Identifier\Name('php'));
 *     [$pi->target->value, $pi->content] // => ['php', null]
 */
final class XmlProcessingInstruction implements Scalar, OutputNaming
{
    use Snapshot;

    /**
     * @param Name $target The processing instruction target
     * @param Scalar|null $content The content
     */
    public function __construct(public readonly Name $target, public readonly ?Scalar $content = null)
    {
    }

    /**
     * Names an unaliased result column.
     */
    public function outputName(): Name
    {
        return new Name('xmlpi');
    }

    /**
     * Derives the content, the type and the NULL fact.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        if ($this->content === null) {
            return new ScalarFact(new Known(Builtin::Xml), Nullability::NotNull);
        }
        $content = $derivation->scalar($this->content, $environment);

        return new ScalarFact(new Known(Builtin::Xml), $content->type instanceof NullOnly ? Nullability::Nullable : $content->nullability);
    }

    /**
     * Writes XMLPI with the target and the content.
     */
    public function render(Output $out): void
    {
        $out->keyword('XMLPI')->glue()->symbol('(')->keyword('NAME')->name($this->target, NameUse::Label);
        if ($this->content !== null) {
            $out->symbol(',')->node($this->content);
        }
        $out->symbol(')');
    }
}
