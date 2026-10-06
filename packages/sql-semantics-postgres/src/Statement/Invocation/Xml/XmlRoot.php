<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation\Xml;

use SqlSemantics\Construction\Derivation;
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
 * `XMLROOT (value, VERSION {version | NO VALUE} [, STANDALONE {YES | NO | NO VALUE}])`: an XML value with a new root declaration.
 *
 * Mirrors PostgreSQL's `XmlExpr` of kind `IS_XMLROOT`. Rule: PG-XMLROOT-001.
 * Facts: `xml`; NULL exactly when the value is. The result column is named
 * `xmlroot`.
 * Source: https://www.postgresql.org/docs/17/functions-xml.html#FUNCTIONS-PRODUCING-XML-XMLROOT. Status: Implemented.
 *
 * @visibility public
 * @example Reading a root without a version
 *     $root = new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Xml\XmlRoot(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral(), null, \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Xml\XmlStandalone::Yes);
 *     [$root->version, $root->standalone->value] // => [null, 'YES']
 */
final class XmlRoot implements Scalar, OutputNaming
{
    use Snapshot;

    /**
     * @param Scalar $value The XML value
     * @param Scalar|null $version The version; null for VERSION NO VALUE
     * @param XmlStandalone|null $standalone The standalone declaration written
     */
    public function __construct(public readonly Scalar $value, public readonly ?Scalar $version = null, public readonly ?XmlStandalone $standalone = null)
    {
    }

    /**
     * Names an unaliased result column.
     */
    public function outputName(): Name
    {
        return new Name('xmlroot');
    }

    /**
     * Derives the value and the version, the type and the NULL fact.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $value = $derivation->scalar($this->value, $environment);
        if ($this->version !== null) {
            $derivation->scalar($this->version, $environment);
        }

        return new ScalarFact(new Known(Builtin::Xml), $value->type instanceof NullOnly ? Nullability::Nullable : $value->nullability);
    }

    /**
     * Writes XMLROOT with the value, the version and the standalone declaration.
     */
    public function render(Output $out): void
    {
        $out->keyword('XMLROOT')->glue()->symbol('(')->node($this->value)->symbol(',')->keyword('VERSION');
        if ($this->version === null) {
            $out->keyword('NO', 'VALUE');
        } else {
            $out->node($this->version);
        }
        if ($this->standalone !== null) {
            $out->symbol(',')->keyword('STANDALONE', ...explode(' ', $this->standalone->value));
        }
        $out->symbol(')');
    }
}
