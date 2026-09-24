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
 * PostgreSQL XMLPARSE(DOCUMENT|CONTENT value [PRESERVE|STRIP WHITESPACE]): converts a character string into xml.
 * @visibility public
 * @example Reading the option, the value and the whitespace handling
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t (n INTEGER, x XML)');
 *     $query = (new \SqlSemantics\Binder($schema))->bind("SELECT XMLPARSE(DOCUMENT '<a/>' PRESERVE WHITESPACE)");
 *     $value = $query->outputs[0]->expression;
 *     [$value->option, $value->value->spelling(), $value->preserveWhitespace, $value->type->name] // => [\SqlSemantics\Model\Scalar\Document\Xml\XmlOption::Document, "'<a/>'", true, 'xml']
 */
final class XmlParse extends Expression
{
    /**
     * Requires an xml result; STRIP WHITESPACE is the default and is not kept apart from it.
     * @visibility SqlSemantics
     * @throws InvalidStructure
     */
    public function __construct(ExpressionFacts $facts, Node|Token $source, public readonly XmlOption $option, public readonly Expression $value, public readonly bool $preserveWhitespace = false)
    {
        XmlInvariant::check('XMLPARSE', $facts, 'xml', [$value]);
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
     * @return list<Expression> The parsed value
     */
    #[Override]
    public function inputs(): array
    {
        return [$this->value];
    }

    /**
     * Returns the fixed operation name.
     */
    #[Override]
    public function spelling(): string
    {
        return 'XMLPARSE';
    }

    /**
     * Keeps the operands with new facts.
     * @visibility SqlSemantics
     * @throws InvalidStructure
     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        return new static($facts, $this->source, $this->option, $this->value, $this->preserveWhitespace);
    }
}
