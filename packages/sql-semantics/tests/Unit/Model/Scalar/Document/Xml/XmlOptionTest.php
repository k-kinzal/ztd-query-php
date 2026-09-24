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
use SqlSemantics\Model\Scalar\Document\Xml\XmlOption;
use SqlSemantics\Model\Scalar\Document\Xml\XmlParse;
use SqlSemantics\SchemaBuilder;

#[CoversClass(XmlOption::class)]
#[Medium]
final class XmlOptionTest extends TestCase
{
    #[TestWith(['DOCUMENT', XmlOption::Document])]
    #[TestWith(['content', XmlOption::Content])]
    public function testCasesAreTheKeywords(string $keyword, XmlOption $expected): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT XMLPARSE(' . $keyword . " '<a/>')");
        self::assertInstanceOf(BoundSelect::class, $query);
        $parse = $query->outputs[0]->expression;
        self::assertInstanceOf(XmlParse::class, $parse);
        self::assertSame($expected, $parse->option);
        self::assertSame(strtoupper($keyword), $expected->value);
    }
}
