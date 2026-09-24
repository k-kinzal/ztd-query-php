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
 * PostgreSQL XMLEXISTS(path PASSING [BY REF|BY VALUE] document [BY REF|BY VALUE]): whether an XPath expression selects any node of the document.
 * @visibility public
 * @example Reading the path, the document and the passing modes
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t (n INTEGER, x XML)');
 *     $query = (new \SqlSemantics\Binder($schema))->bind("SELECT XMLEXISTS('//a' PASSING BY REF x) FROM t");
 *     $value = $query->outputs[0]->expression;
 *     [$value->path->spelling(), $value->document->referenceParts(), $value->inputMode] // => ["'//a'", ['x'], \SqlSemantics\Model\TableFunction\Xml\PassingMode::Reference]
 *     $query->toString() // => 'SELECT XMLEXISTS(\'//a\' PASSING BY REF "x") FROM "public"."t"'
 */
final class XmlExistence extends Expression
{
    /**
     * Requires a boolean result.
     * @visibility SqlSemantics
     * @throws InvalidStructure
     */
    public function __construct(ExpressionFacts $facts, Node|Token $source, public readonly Expression $path, public readonly Expression $document, public readonly \SqlSemantics\Model\TableFunction\Xml\PassingMode $inputMode = \SqlSemantics\Model\TableFunction\Xml\PassingMode::Default, public readonly \SqlSemantics\Model\TableFunction\Xml\PassingMode $outputMode = \SqlSemantics\Model\TableFunction\Xml\PassingMode::Default)
    {
        XmlInvariant::check('XMLEXISTS', $facts, 'boolean', [$path, $document]);
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
     * @return list<Expression> The path followed by the document
     */
    #[Override]
    public function inputs(): array
    {
        return [$this->path, $this->document];
    }

    /**
     * Returns the fixed operation name.
     */
    #[Override]
    public function spelling(): string
    {
        return 'XMLEXISTS';
    }

    /**
     * Keeps the operands with new facts.
     * @visibility SqlSemantics
     * @throws InvalidStructure
     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        return new static($facts, $this->source, $this->path, $this->document, $this->inputMode, $this->outputMode);
    }
}
