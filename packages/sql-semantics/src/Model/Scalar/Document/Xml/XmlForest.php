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
 * PostgreSQL XMLFOREST(value [AS name], ...): a sequence of elements, one per value, named by the alias or by the referenced column.
 * @visibility public
 * @example Reading the element names
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t (n INTEGER, x XML)');
 *     $query = (new \SqlSemantics\Binder($schema))->bind('SELECT XMLFOREST(n, x AS doc) FROM t');
 *     $value = $query->outputs[0]->expression;
 *     array_map(static fn ($element) => $element->label(), $value->elements) // => ['n', 'doc']
 */
final class XmlForest extends Expression
{
    /**
     * @var non-empty-list<XmlNamedArgument> The elements in order
     */
    public readonly array $elements;

    /**
     * Requires an xml result and at least one element.
     * @visibility SqlSemantics
     * @param list<XmlNamedArgument> $elements
     * @throws InvalidStructure
     */
    public function __construct(ExpressionFacts $facts, Node|Token $source, array $elements)
    {
        $this->elements = \SqlSemantics\Model\Validation\Collections::nonEmpty($elements);
        \SqlSemantics\Model\Validation\Collections::objects($elements, XmlNamedArgument::class);
        XmlInvariant::check('XMLFOREST', $facts, 'xml', array_map(static fn (XmlNamedArgument $element): Expression => $element->value, $elements));
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
     * @return list<Expression> The element values
     */
    #[Override]
    public function inputs(): array
    {
        return array_map(static fn (XmlNamedArgument $element): Expression => $element->value, $this->elements);
    }

    /**
     * Returns the fixed operation name.
     */
    #[Override]
    public function spelling(): string
    {
        return 'XMLFOREST';
    }

    /**
     * Keeps the operands with new facts.
     * @visibility SqlSemantics
     * @throws InvalidStructure
     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        return new static($facts, $this->source, $this->elements);
    }
}
