<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Scalar\Intrinsic;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Scalar\Intrinsic\JsonPathBinder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Scalar\Reference\JsonPathExtraction;
use SqlSemantics\SchemaBuilder;

#[CoversClass(JsonPathBinder::class)]
#[Medium]
final class JsonPathBinderTest extends TestCase
{
    public function testBindRetainsTheColumnPathAndUnquoting(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t (doc JSON)')))->bind("SELECT doc->'$.a', doc->>'$.b' FROM t");
        self::assertInstanceOf(BoundSelect::class, $query);
        $extract = $query->outputs[0]->expression;
        $text = $query->outputs[1]->expression;
        self::assertInstanceOf(JsonPathExtraction::class, $extract);
        self::assertInstanceOf(JsonPathExtraction::class, $text);
        self::assertFalse($extract->unquoted);
        self::assertTrue($text->unquoted);
        self::assertSame("'$.b'", $text->path->text);
    }

    public function testBindLeavesOtherExpressionsAlone(): void
    {
        $tree = (new \SqlSemantics\Ast\DialectParser(Dialect::MySql))->parse('SELECT a + 1');
        $scope = new \SqlSemantics\Binding\Scope(new \SqlSemantics\Ast\Identifiers(Dialect::MySql));
        self::assertNull(JsonPathBinder::bind($tree->find('bit_expr')[0], $scope));
    }
}
