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
 * PostgreSQL XMLELEMENT(NAME name [, XMLATTRIBUTES(value [AS name], ...)] [, content, ...]): an element with attributes and content.
 * @visibility public
 * @example Reading the element name, the attributes and the content
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t (n INTEGER, x XML)');
 *     $query = (new \SqlSemantics\Binder($schema))->bind("SELECT XMLELEMENT(NAME item, XMLATTRIBUTES(n, 'x' AS kind), x) FROM t");
 *     $value = $query->outputs[0]->expression;
 *     [$value->name, array_map(static fn ($attribute) => $attribute->label(), $value->attributes), count($value->content)] // => ['item', ['n', 'kind'], 1]
 *     (new \SqlSemantics\SimpleSerializer())->serialize($query) // => 'SELECT XMLELEMENT(NAME "item", XMLATTRIBUTES("n", \'x\' AS "kind"), "x") FROM "public"."t"'
 */
final class XmlElement extends Expression
{
    /**
     * @var list<XmlNamedArgument> The attributes in order, each name used once
     */
    public readonly array $attributes;

    /**
     * @var list<Expression> The content values in order
     */
    public readonly array $content;

    /**
     * Requires an xml result, a nonempty element name and distinct attribute names.
     * @visibility SqlSemantics
     * @param list<XmlNamedArgument> $attributes
     * @param list<Expression> $content
     * @throws InvalidStructure
     */
    public function __construct(ExpressionFacts $facts, Node|Token $source, public readonly string $name, array $attributes = [], array $content = [])
    {
        \SqlSemantics\Model\Validation\Collections::objects($attributes, XmlNamedArgument::class);
        \SqlSemantics\Model\Validation\Collections::objects($content, Expression::class);
        $labels = array_map(static fn (XmlNamedArgument $attribute): string => $attribute->label(), $attributes);
        if ($name === '' || count(array_unique($labels)) !== count($labels)) {
            throw new InvalidStructure('XMLELEMENT requires a name and distinct attribute names.');
        }
        XmlInvariant::check('XMLELEMENT', $facts, 'xml', [...array_map(static fn (XmlNamedArgument $attribute): Expression => $attribute->value, $attributes), ...$content]);
        $this->attributes = $attributes;
        $this->content = $content;
        parent::__construct($facts, $source);
    }

    /**
     * Identifies an XML constructor.
     */
    #[Override]
    protected function operation(): ExpressionKind
    {
        return ExpressionKind::XmlConstructor;
    }

    /**
     * @return list<Expression> The attribute values followed by the content
     */
    #[Override]
    public function inputs(): array
    {
        return [...array_map(static fn (XmlNamedArgument $attribute): Expression => $attribute->value, $this->attributes), ...$this->content];
    }

    /**
     * Returns the fixed operation name.
     */
    #[Override]
    public function spelling(): string
    {
        return 'XMLELEMENT';
    }

    /**
     * Keeps the operands with new facts.
     * @visibility SqlSemantics
     * @throws InvalidStructure
     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        return new static($facts, $this->source, $this->name, $this->attributes, $this->content);
    }
}
