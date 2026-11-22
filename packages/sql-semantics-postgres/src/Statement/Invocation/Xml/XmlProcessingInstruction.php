<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation\Xml;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\ScalarFact;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Known;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\NullOnly;
use SqlSemantics\Platform\PostgreSql\Statement\OutputNaming;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;

/**
 * `XMLPI (NAME target [, content])`: an XML processing instruction.
 *
 * Mirrors PostgreSQL's `XmlExpr` of kind `IS_XMLPI`. Rule: PG-XMLPI-001.
 * Facts: `xml`; NULL exactly when the content is. The result column is named
 * `xmlpi`.
 * Source: https://www.postgresql.org/docs/17/functions-xml.html#FUNCTIONS-PRODUCING-XML-XMLPI. Status: Implemented.
 *
 * @visibility SqlSemantics
 * @example Reading a processing instruction without content
 *     $pi = new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Xml\XmlProcessingInstruction(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('php'));
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
