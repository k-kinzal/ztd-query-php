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
use SqlSemantics\Model\Scalar\Document\JsonScalarExtraction;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\TableFunction\Json\Response\DefaultResponse;
use SqlSemantics\Model\TableFunction\Json\Response\ValueBehavior;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(JsonScalarExtraction::class)]
#[Medium]
final class JsonScalarExtractionTest extends TestCase
{
    public function testInputsListTheDocumentPathAndDefaultResponses(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.0.44'))->build());
        $query = $binder->bind("SELECT JSON_VALUE('{}', '$.a' RETURNING CHAR(3) DEFAULT 'x' ON EMPTY ERROR ON ERROR)");
        self::assertInstanceOf(BoundSelect::class, $query);
        $value = $query->outputs[0]->expression;
        self::assertInstanceOf(JsonScalarExtraction::class, $value);
        self::assertSame("'{}'", $value->document->spelling());
        self::assertInstanceOf(Literal::class, $value->path);
        self::assertSame("'$.a'", $value->path->text);
        self::assertSame('char', $value->returning?->name);
        self::assertInstanceOf(DefaultResponse::class, $value->onEmpty);
        self::assertSame(ValueBehavior::Error, $value->onError);
        self::assertSame([$value->document, $value->path, $value->onEmpty->expression], $value->inputs());
        self::assertSame('char', $value->type->name);
        self::assertSame(Nullability::MaybeNull, $value->nullability);
        self::assertSame("SELECT JSON_VALUE('{}', '$.a' RETURNING CHAR(3) DEFAULT 'x' ON EMPTY ERROR ON ERROR)", (new \SqlSemantics\SimpleSerializer())->serialize($query));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($query), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($query))));
    }

    public function testInputsDefaultToAStringWithoutResponses(): void
    {
        $path = Expression::literal('$.a', Dialect::MySql);
        self::assertInstanceOf(Literal::class, $path);
        $value = new JsonScalarExtraction($path->source, Expression::literal('{}', Dialect::MySql), $path);
        self::assertSame('varchar', $value->type->name);
        self::assertSame(ValueBehavior::Default, $value->onEmpty);
        self::assertCount(2, $value->inputs());
    }

    public function testInputsRejectSqlite(): void
    {
        $path = Expression::literal('$.a', Dialect::Sqlite);
        $this->expectException(InvalidStructure::class);
        new JsonScalarExtraction($path->source, Expression::literal('{}', Dialect::Sqlite), $path);
    }

    public function testInputsRejectAPathOfAnotherDialect(): void
    {
        $path = Expression::literal('$.a', Dialect::PostgreSql);
        $this->expectException(InvalidStructure::class);
        new JsonScalarExtraction($path->source, Expression::literal('{}', Dialect::MySql), $path);
    }

    public function testInputsRejectPostgreSqlOptionsOnMySql(): void
    {
        $path = Expression::literal('$.a', Dialect::MySql);
        $this->expectException(InvalidStructure::class);
        new JsonScalarExtraction($path->source, Expression::literal('{}', Dialect::MySql), $path, format: \SqlSemantics\Model\TableFunction\Json\Format::Json);
    }

    public function testInputsListThePostgreSqlPassingValues(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(d jsonb, p text, n integer)'));
        $query = $binder->bind('SELECT JSON_VALUE(d, p::jsonpath PASSING n AS x RETURNING integer DEFAULT n ON ERROR) FROM t');
        self::assertInstanceOf(BoundSelect::class, $query);
        $value = $query->outputs[0]->expression;
        self::assertInstanceOf(JsonScalarExtraction::class, $value);
        self::assertCount(4, $value->inputs());
        self::assertSame('integer', $value->type->name);
        self::assertSame(Nullability::MaybeNull, $value->nullability);
        self::assertSame('SELECT JSON_VALUE("d", CAST("p" AS "jsonpath") PASSING "n" AS "x" RETURNING integer DEFAULT "n" ON ERROR) FROM "public"."t"', (new \SqlSemantics\SimpleSerializer())->serialize($query));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($query), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($query))));
    }

    public function testInputsRejectAReturnedTypeOfAnotherDialect(): void
    {
        $path = Expression::literal('$.a', Dialect::MySql);
        self::assertInstanceOf(Literal::class, $path);
        $this->expectException(InvalidStructure::class);
        new JsonScalarExtraction($path->source, $path, $path, TypeDescriptor::builtin(Dialect::PostgreSql, 'text'));
    }

    public function testInputsRejectAPathThatIsNotAString(): void
    {
        $path = Expression::literal(1, Dialect::MySql);
        self::assertInstanceOf(Literal::class, $path);
        $this->expectException(InvalidStructure::class);
        new JsonScalarExtraction($path->source, $path, $path);
    }

    public function testInputsRejectAPostgreSqlOnlyResponse(): void
    {
        $path = Expression::literal('$.a', Dialect::MySql);
        self::assertInstanceOf(Literal::class, $path);
        $this->expectException(InvalidStructure::class);
        new JsonScalarExtraction($path->source, $path, $path, null, ValueBehavior::EmptyArray);
    }

    public function testSpellingIdentifiesJsonValue(): void
    {
        $path = Expression::literal('$.a', Dialect::MySql);
        self::assertInstanceOf(Literal::class, $path);
        self::assertSame('JSON_VALUE', (new JsonScalarExtraction($path->source, $path, $path))->spelling());
    }

    public function testWithFactsPreservesTheOperands(): void
    {
        $path = Expression::literal('$.a', Dialect::MySql);
        self::assertInstanceOf(Literal::class, $path);
        $value = new JsonScalarExtraction($path->source, $path, $path, null, ValueBehavior::Null);
        $copy = $value->withFacts($value->facts);
        self::assertNotSame($value, $copy);
        self::assertSame(ValueBehavior::Null, $copy->onEmpty);
        self::assertSame($path, $copy->path);
    }

    public function testWithFactsRejectsContradictoryFacts(): void
    {
        $path = Expression::literal('$.a', Dialect::MySql);
        self::assertInstanceOf(Literal::class, $path);
        $value = new JsonScalarExtraction($path->source, $path, $path);
        $this->expectException(InvalidStructure::class);
        $value->withFacts($path->facts);
    }
}
