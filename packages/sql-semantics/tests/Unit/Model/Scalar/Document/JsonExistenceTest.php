<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Document;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Scalar\Document\JsonExistence;
use SqlSemantics\Model\TableFunction\Json\Format;
use SqlSemantics\Model\TableFunction\Json\Input;
use SqlSemantics\Model\TableFunction\Json\Response\ExistsResponse;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(JsonExistence::class)]
#[Medium]
final class JsonExistenceTest extends TestCase
{
    public function testInputsListTheDocumentPathAndPassingValues(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(d text, n integer)'));
        $query = $binder->bind("SELECT JSON_EXISTS(d FORMAT JSON, '$[\$i]' PASSING n AS i ERROR ON ERROR) FROM t");
        self::assertInstanceOf(BoundSelect::class, $query);
        $value = $query->outputs[0]->expression;
        self::assertInstanceOf(JsonExistence::class, $value);
        self::assertSame(Format::Json, $value->document->format);
        self::assertSame(ExistsResponse::Error, $value->onError);
        self::assertSame([$value->document->expression, $value->path, $value->passing[0]->input->expression], $value->inputs());
        self::assertSame('boolean', $value->type->name);
        self::assertSame(ExpressionKind::JsonExists, $value->kind);
        self::assertSame('SELECT JSON_EXISTS("d" FORMAT JSON, \'$[$i]\' PASSING "n" AS "i" ERROR ON ERROR) FROM "public"."t"', $query->toString());
        self::assertSame($query->toString(), $binder->bind($query->toString())->toString());
    }

    public function testInputsRejectAnotherDialect(): void
    {
        $document = Expression::literal('{}', Dialect::MySql);
        $this->expectException(InvalidStructure::class);
        new JsonExistence($document->source, new Input($document), $document);
    }

    public function testSpellingIdentifiesJsonExists(): void
    {
        $document = Expression::literal('{}', Dialect::PostgreSql);
        self::assertSame('JSON_EXISTS', (new JsonExistence($document->source, new Input($document), $document))->spelling());
    }

    public function testWithFactsPreservesTheResponse(): void
    {
        $document = Expression::literal('{}', Dialect::PostgreSql);
        $value = new JsonExistence($document->source, new Input($document), $document, [], ExistsResponse::Unknown);
        self::assertSame(ExistsResponse::Unknown, $value->withFacts($value->facts)->onError);
        $this->expectException(InvalidStructure::class);
        $value->withFacts($document->facts);
    }

    public function testInputsListEveryPassingValue(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(d jsonb, n integer)')))->bind("SELECT JSON_EXISTS(d, '$.a' PASSING n AS x, 2 AS y) FROM t");
        self::assertInstanceOf(BoundSelect::class, $query);
        $value = $query->outputs[0]->expression;
        self::assertInstanceOf(JsonExistence::class, $value);
        self::assertCount(4, $value->inputs());
        self::assertSame([$value->passing[0]->input->expression, $value->passing[1]->input->expression], array_slice($value->inputs(), 2));
    }
}
