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
 * PostgreSQL XMLPI(NAME target [, content]): an XML processing instruction.
 * @visibility public
 * @example Reading the target and the content
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t (n INTEGER, x XML)');
 *     $query = (new \SqlSemantics\Binder($schema))->bind("SELECT XMLPI(NAME php, 'echo 1;')");
 *     $value = $query->outputs[0]->expression;
 *     [$value->target, $value->content?->spelling()] // => ['php', "'echo 1;'"]
 */
final class XmlProcessingInstruction extends Expression
{
    /**
     * The instruction text, or null for an instruction without content.
     */
    public readonly ?Expression $content;

    /**
     * Requires an xml result and a nonempty target name.
     * @visibility SqlSemantics
     * @throws InvalidStructure
     */
    public function __construct(ExpressionFacts $facts, Node|Token $source, public readonly string $target, ?Expression $content = null)
    {
        if ($target === '') {
            throw new InvalidStructure('XMLPI requires a target name.');
        }
        XmlInvariant::check('XMLPI', $facts, 'xml', $content === null ? [] : [$content]);
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
     * @return list<Expression> The content when present
     */
    #[Override]
    public function inputs(): array
    {
        return $this->content === null ? [] : [$this->content];
    }

    /**
     * Returns the fixed operation name.
     */
    #[Override]
    public function spelling(): string
    {
        return 'XMLPI';
    }

    /**
     * Keeps the operands with new facts.
     * @visibility SqlSemantics
     * @throws InvalidStructure
     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        return new static($facts, $this->source, $this->target, $this->content);
    }
}
