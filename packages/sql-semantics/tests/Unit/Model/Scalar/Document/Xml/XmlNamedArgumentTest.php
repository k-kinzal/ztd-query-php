<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Document\Xml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Document\Xml\XmlForest;
use SqlSemantics\Model\Scalar\Document\Xml\XmlNamedArgument;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(XmlNamedArgument::class)]
#[Medium]
final class XmlNamedArgumentTest extends TestCase
{
    public function testLabelUsesTheAliasOrTheReferencedName(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (n INTEGER)')))->bind('SELECT XMLFOREST(t.n, t.*, n + 1 AS "Next") FROM t');
        self::assertInstanceOf(BoundSelect::class, $query);
        $forest = $query->outputs[0]->expression;
        self::assertInstanceOf(XmlForest::class, $forest);
        self::assertSame(['n', 't', 'Next'], array_map(static fn (XmlNamedArgument $element): string => $element->label(), $forest->elements));
        self::assertNull($forest->elements[0]->alias);
    }

    public function testLabelRequiresAnAliasForAnExpression(): void
    {
        $this->expectException(InvalidStructure::class);
        new XmlNamedArgument(Expression::literal(1, Dialect::PostgreSql));
    }

    public function testLabelRejectsAnEmptyAlias(): void
    {
        $this->expectException(InvalidStructure::class);
        new XmlNamedArgument(Expression::literal(1, Dialect::PostgreSql), '');
    }
}
