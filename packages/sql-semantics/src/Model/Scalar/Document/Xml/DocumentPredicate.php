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
 * PostgreSQL `value IS [NOT] DOCUMENT`: whether an xml value is a well-formed document rather than a content fragment; NULL for a NULL value.
 * @visibility public
 * @example Reading the tested value and the negation
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t (n INTEGER, x XML)');
 *     $query = (new \SqlSemantics\Binder($schema))->bind('SELECT x IS NOT DOCUMENT FROM t');
 *     $value = $query->outputs[0]->expression;
 *     [$value->value->referenceParts(), $value->negated, $value->type->name] // => [['x'], true, 'boolean']
 *     $query->toString() // => 'SELECT ("x" IS NOT DOCUMENT) FROM "public"."t"'
 */
final class DocumentPredicate extends Expression
{
    /**
     * Requires a boolean result.
     * @visibility SqlSemantics
     * @throws InvalidStructure
     */
    public function __construct(ExpressionFacts $facts, Node|Token $source, public readonly Expression $value, public readonly bool $negated)
    {
        XmlInvariant::check('IS DOCUMENT', $facts, 'boolean', [$value]);
        parent::__construct($facts, $source);
    }

    /**
     * Identifies an XML predicate.
     */
    #[Override]
    protected function operation(): ExpressionKind
    {
        return ExpressionKind::XmlPredicate;
    }

    /**
     * @return list<Expression> The tested value
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
        return $this->negated ? 'IS NOT DOCUMENT' : 'IS DOCUMENT';
    }

    /**
     * Keeps the operands with new facts.
     * @visibility SqlSemantics
     * @throws InvalidStructure
     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        return new static($facts, $this->source, $this->value, $this->negated);
    }
}
