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
 * PostgreSQL XMLCONCAT(value, ...): concatenates xml values, skipping NULL ones.
 * @visibility public
 * @example Reading the concatenated values
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t (n INTEGER, x XML)');
 *     $query = (new \SqlSemantics\Binder($schema))->bind('SELECT XMLCONCAT(x, x) FROM t');
 *     $value = $query->outputs[0]->expression;
 *     count($value->values) // => 2
 */
final class XmlConcatenation extends Expression
{
    /**
     * @var non-empty-list<Expression> The concatenated values in order
     */
    public readonly array $values;

    /**
     * Requires an xml result and at least one value.
     * @visibility SqlSemantics
     * @param list<Expression> $values
     * @throws InvalidStructure
     */
    public function __construct(ExpressionFacts $facts, Node|Token $source, array $values)
    {
        $this->values = \SqlSemantics\Model\Validation\Collections::nonEmpty($values);
        \SqlSemantics\Model\Validation\Collections::objects($values, Expression::class);
        XmlInvariant::check('XMLCONCAT', $facts, 'xml', $values);
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
     * @return list<Expression> The concatenated values
     */
    #[Override]
    public function inputs(): array
    {
        return $this->values;
    }

    /**
     * Returns the fixed operation name.
     */
    #[Override]
    public function spelling(): string
    {
        return 'XMLCONCAT';
    }

    /**
     * Keeps the operands with new facts.
     * @visibility SqlSemantics
     * @throws InvalidStructure
     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        return new static($facts, $this->source, $this->values);
    }
}
