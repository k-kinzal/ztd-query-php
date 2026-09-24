<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Document\Xml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Scalar\Document\Xml\XmlRoot;
use SqlSemantics\Model\Scalar\Document\Xml\XmlStandalone;
use SqlSemantics\SchemaBuilder;

#[CoversClass(XmlStandalone::class)]
#[Medium]
final class XmlStandaloneTest extends TestCase
{
    #[TestWith(['', XmlStandalone::Omitted])]
    #[TestWith([', STANDALONE YES', XmlStandalone::Yes])]
    #[TestWith([', standalone no', XmlStandalone::No])]
    #[TestWith([', STANDALONE NO VALUE', XmlStandalone::NoValue])]
    public function testCasesAreTheWrittenRequests(string $clause, XmlStandalone $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $query = $binder->bind("SELECT XMLROOT(XMLPARSE(DOCUMENT '<a/>'), VERSION NO VALUE" . $clause . ')');
        self::assertInstanceOf(BoundSelect::class, $query);
        $root = $query->outputs[0]->expression;
        self::assertInstanceOf(XmlRoot::class, $root);
        self::assertSame($expected, $root->standalone);
        self::assertNull($root->version);
        self::assertSame($query->toString(), $binder->bind($query->toString())->toString());
    }
}
