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
 * PostgreSQL XMLSERIALIZE(DOCUMENT|CONTENT value AS type [[NO] INDENT]): converts xml into a character string type.
 * @visibility public
 * @example Reading the option, the target type and the indentation
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t (n INTEGER, x XML)');
 *     $query = (new \SqlSemantics\Binder($schema))->bind('SELECT XMLSERIALIZE(CONTENT x AS varchar(20) INDENT) FROM t');
 *     $value = $query->outputs[0]->expression;
 *     [$value->option, $value->target->name, $value->indent, $value->type->name] // => [\SqlSemantics\Model\Scalar\Document\Xml\XmlOption::Content, 'varchar', true, 'varchar']
 */
final class XmlSerialization extends Expression
{
    /**
     * The result has the target type; NO INDENT is the default and is not kept apart from it.
     * @visibility SqlSemantics
     * @throws InvalidStructure
     */
    public function __construct(ExpressionFacts $facts, Node|Token $source, public readonly XmlOption $option, public readonly Expression $value, public readonly \SqlSemantics\Type\TypeDescriptor $target, public readonly bool $indent = false)
    {
        XmlInvariant::check('XMLSERIALIZE', $facts, $target->name, [$value]);
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
     * @return list<Expression> The serialized value
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
        return 'XMLSERIALIZE';
    }

    /**
     * Keeps the operands with new facts.
     * @visibility SqlSemantics
     * @throws InvalidStructure
     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        return new static($facts, $this->source, $this->option, $this->value, $this->target, $this->indent);
    }
}
