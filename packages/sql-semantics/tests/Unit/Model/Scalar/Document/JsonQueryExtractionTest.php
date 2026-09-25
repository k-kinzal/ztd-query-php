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
use SqlSemantics\Model\Scalar\Document\JsonQueryExtraction;
use SqlSemantics\Model\TableFunction\Json\ArrayWrapping;
use SqlSemantics\Model\TableFunction\Json\Input;
use SqlSemantics\Model\TableFunction\Json\Quotes;
use SqlSemantics\Model\TableFunction\Json\Response\DefaultResponse;
use SqlSemantics\Model\TableFunction\Json\Response\ValueBehavior;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;

#[CoversClass(JsonQueryExtraction::class)]
#[Medium]
final class JsonQueryExtractionTest extends TestCase
{
    public function testInputsListTheDocumentPathPassingAndDefaults(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(d jsonb, n integer)'));
        $query = $binder->bind("SELECT JSON_QUERY(d, '$[\$i]' PASSING n AS i RETURNING text WITHOUT ARRAY WRAPPER OMIT QUOTES ON SCALAR STRING EMPTY ON EMPTY DEFAULT '[]' ON ERROR) FROM t");
        self::assertInstanceOf(BoundSelect::class, $query);
        $value = $query->outputs[0]->expression;
        self::assertInstanceOf(JsonQueryExtraction::class, $value);
        self::assertSame(ArrayWrapping::Without, $value->wrapper);
        self::assertSame(Quotes::Omit, $value->quotes);
        self::assertSame(ValueBehavior::EmptyArray, $value->onEmpty);
        self::assertInstanceOf(DefaultResponse::class, $value->onError);
        self::assertSame([$value->document->expression, $value->path, $value->passing[0]->input->expression, $value->onError->expression], $value->inputs());
        self::assertSame('text', $value->type->name);
        self::assertSame(Nullability::MaybeNull, $value->nullability);
        self::assertSame(ExpressionKind::JsonQuery, $value->kind);
        self::assertSame('SELECT JSON_QUERY("d", \'$[$i]\' PASSING "n" AS "i" RETURNING text WITHOUT WRAPPER OMIT QUOTES EMPTY ARRAY ON EMPTY DEFAULT \'[]\' ON ERROR) FROM "public"."t"', (new \SqlSemantics\SimpleSerializer())->serialize($query));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($query), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($query))));
    }

    public function testInputsDefaultToJsonb(): void
    {
        $document = Expression::literal('{}', Dialect::PostgreSql);
        $value = new JsonQueryExtraction($document->source, new Input($document), Expression::literal('$', Dialect::PostgreSql));
        self::assertSame('jsonb', $value->type->name);
        self::assertCount(2, $value->inputs());
    }

    public function testInputsRejectOmittedQuotesWithAWrapper(): void
    {
        $document = Expression::literal('{}', Dialect::PostgreSql);
        $this->expectException(InvalidStructure::class);
        new JsonQueryExtraction($document->source, new Input($document), $document, wrapper: ArrayWrapping::Conditional, quotes: Quotes::Omit);
    }

    public function testInputsRejectAMySqlDocument(): void
    {
        $document = Expression::literal('{}', Dialect::MySql);
        $this->expectException(InvalidStructure::class);
        new JsonQueryExtraction($document->source, new Input($document), Expression::literal('$', Dialect::PostgreSql));
    }

    public function testSpellingIdentifiesJsonQuery(): void
    {
        $document = Expression::literal('{}', Dialect::PostgreSql);
        self::assertSame('JSON_QUERY', (new JsonQueryExtraction($document->source, new Input($document), $document))->spelling());
    }

    public function testWithFactsPreservesTheOperands(): void
    {
        $document = Expression::literal('{}', Dialect::PostgreSql);
        $value = new JsonQueryExtraction($document->source, new Input($document), $document, wrapper: ArrayWrapping::Unconditional, quotes: Quotes::Keep);
        $copy = $value->withFacts($value->facts);
        self::assertNotSame($value, $copy);
        self::assertSame(Quotes::Keep, $copy->quotes);
        $this->expectException(InvalidStructure::class);
        $value->withFacts($document->facts);
    }
}
