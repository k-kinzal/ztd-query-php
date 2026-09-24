<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Document\Xml;

use Override;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * PostgreSQL XMLROOT(value, VERSION text|NO VALUE [, STANDALONE YES|NO|NO VALUE]): replaces the version and standalone properties of the root node.
 * @visibility public
 * @example Reading the version and the standalone request
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t (n INTEGER, x XML)');
 *     $query = (new \SqlSemantics\Binder($schema))->bind("SELECT XMLROOT(x, VERSION '1.0', STANDALONE YES) FROM t");
 *     $value = $query->outputs[0]->expression;
 *     [$value->version?->spelling(), $value->standalone] // => ["'1.0'", \SqlSemantics\Model\Scalar\Document\Xml\XmlStandalone::Yes]
 */
final class XmlRoot extends Expression
{
    /**
     * The version text, or null for VERSION NO VALUE, which removes the version.
     */
    public readonly ?Expression $version;

    /**
     * Requires an xml result.
     * @visibility SqlSemantics
     * @throws InvalidStructure
     */
    public function __construct(ExpressionFacts $facts, Node|Token $source, public readonly Expression $value, ?Expression $version, public readonly XmlStandalone $standalone = XmlStandalone::Omitted)
    {
        XmlInvariant::check('XMLROOT', $facts, 'xml', [$value, ...($version === null ? [] : [$version])]);
        $this->version = $version;
        parent::__construct($facts, $source);
    }

    /**
     * Identifies an XML conversion.
     */
    #[Override]
    protected function operation(): ExpressionKind
    {
        return ExpressionKind::XmlConversion;
    }

    /**
     * @return list<Expression> The value followed by the version when present
     */
    #[Override]
    public function inputs(): array
    {
        return [$this->value, ...($this->version === null ? [] : [$this->version])];
    }

    /**
     * Returns the fixed operation name.
     */
    #[Override]
    public function spelling(): string
    {
        return 'XMLROOT';
    }

    /**
     * Keeps the operands with new facts.
     * @visibility SqlSemantics
     * @throws InvalidStructure
     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        return new static($facts, $this->source, $this->value, $this->version, $this->standalone);
    }
}
