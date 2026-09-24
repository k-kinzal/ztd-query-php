<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Scalar\Document;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Scalar\Document\JsonQueryBinder;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Scalar\Document\JsonExistence;
use SqlSemantics\Model\Scalar\Document\JsonQueryExtraction;
use SqlSemantics\Model\Scalar\Document\JsonScalarExtraction;
use SqlSemantics\Model\TableFunction\Json\ArrayWrapping;
use SqlSemantics\Model\TableFunction\Json\Format;
use SqlSemantics\Model\TableFunction\Json\Quotes;
use SqlSemantics\Model\TableFunction\Json\Response\DefaultResponse;
use SqlSemantics\Model\TableFunction\Json\Response\ExistsResponse;
use SqlSemantics\Model\TableFunction\Json\Response\ValueBehavior;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(JsonQueryBinder::class)]
#[Medium]
final class JsonQueryBinderTest extends TestCase
{
    public function testValueKeepsTheFormatPassingReturnedTypeAndResponses(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(d text, n integer)'));
        $query = $binder->bind("SELECT JSON_VALUE(d FORMAT JSON, '$[\$i]' PASSING n AS i, 'x' AS s RETURNING numeric(5,2) NULL ON EMPTY DEFAULT -1 ON ERROR) FROM t");
        self::assertInstanceOf(BoundSelect::class, $query);
        $value = $query->outputs[0]->expression;
        self::assertInstanceOf(JsonScalarExtraction::class, $value);
        self::assertSame(Format::Json, $value->format);
        self::assertSame(['i', 's'], array_map(static fn ($argument): string => $argument->name, $value->passing));
        self::assertSame('numeric', $value->returning?->name);
        self::assertSame(ValueBehavior::Null, $value->onEmpty);
        self::assertInstanceOf(DefaultResponse::class, $value->onError);
        self::assertSame('SELECT JSON_VALUE("d" FORMAT JSON, \'$[$i]\' PASSING "n" AS "i", \'x\' AS "s" RETURNING numeric(5, 2) NULL ON EMPTY DEFAULT - 1 ON ERROR) FROM "public"."t"', $query->toString());
        self::assertSame($query->toString(), $binder->bind($query->toString())->toString());
    }

    #[TestWith(["SELECT JSON_VALUE(jsonb '1', '$' EMPTY ON ERROR)"])]
    #[TestWith(["SELECT JSON_VALUE(jsonb '1', '$' EMPTY OBJECT ON EMPTY)"])]
    #[TestWith(["SELECT JSON_VALUE(jsonb '1', '$' TRUE ON ERROR)"])]
    #[TestWith(["SELECT JSON_VALUE(jsonb '1', '$' RETURNING text FORMAT JSON)"])]
    public function testValueRejectsOptionsPostgreSqlRejects(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        try {
            $binder->bind($sql);
            self::fail('PostgreSQL rejects this JSON_VALUE option.');
        } catch (InvalidSql $error) {
            self::assertSame(InputViolation::JsonOption, $error->violation);
        }
    }

    #[TestWith(["SELECT JSON_QUERY(jsonb '1', '$' WITH WRAPPER)", ArrayWrapping::Unconditional, Quotes::Default, "SELECT JSON_QUERY(CAST('1' AS jsonb), '$' WITH UNCONDITIONAL WRAPPER)"])]
    #[TestWith(["SELECT JSON_QUERY(jsonb '1', '$' WITH CONDITIONAL ARRAY WRAPPER KEEP QUOTES)", ArrayWrapping::Conditional, Quotes::Keep, "SELECT JSON_QUERY(CAST('1' AS jsonb), '$' WITH CONDITIONAL WRAPPER KEEP QUOTES)"])]
    #[TestWith(["SELECT JSON_QUERY(jsonb '1', '$' WITHOUT ARRAY WRAPPER OMIT QUOTES ON SCALAR STRING)", ArrayWrapping::Without, Quotes::Omit, "SELECT JSON_QUERY(CAST('1' AS jsonb), '$' WITHOUT WRAPPER OMIT QUOTES)"])]
    public function testQueryKeepsTheWrapperAndQuotes(string $sql, ArrayWrapping $wrapper, Quotes $quotes, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $query = $binder->bind($sql);
        self::assertInstanceOf(BoundSelect::class, $query);
        $value = $query->outputs[0]->expression;
        self::assertInstanceOf(JsonQueryExtraction::class, $value);
        self::assertSame($wrapper, $value->wrapper);
        self::assertSame($quotes, $value->quotes);
        self::assertSame($expected, $query->toString());
        self::assertSame($expected, $binder->bind($expected)->toString());
    }

    #[TestWith(["SELECT JSON_QUERY(jsonb '1', '$' WITH WRAPPER OMIT QUOTES)"])]
    #[TestWith(["SELECT JSON_QUERY(jsonb '1', '$' TRUE ON EMPTY)"])]
    #[TestWith(["SELECT JSON_QUERY(jsonb '1', '$' RETURNING text FORMAT JSON ENCODING UTF8)"])]
    public function testQueryRejectsOptionsPostgreSqlRejects(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        try {
            $binder->bind($sql);
            self::fail('PostgreSQL rejects this JSON_QUERY option.');
        } catch (InvalidSql $error) {
            self::assertSame(InputViolation::JsonOption, $error->violation);
        }
    }

    #[TestWith(['TRUE', ExistsResponse::True])]
    #[TestWith(['FALSE', ExistsResponse::False])]
    #[TestWith(['UNKNOWN', ExistsResponse::Unknown])]
    #[TestWith(['ERROR', ExistsResponse::Error])]
    public function testExistsKeepsTheErrorResponse(string $response, ExistsResponse $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $query = $binder->bind("SELECT JSON_EXISTS(jsonb '1', '$.a' " . $response . ' ON ERROR)');
        self::assertInstanceOf(BoundSelect::class, $query);
        $value = $query->outputs[0]->expression;
        self::assertInstanceOf(JsonExistence::class, $value);
        self::assertSame($expected, $value->onError);
        self::assertSame("SELECT JSON_EXISTS(CAST('1' AS jsonb), '$.a' " . $response . ' ON ERROR)', $query->toString());
    }

    #[TestWith(['NULL'])]
    #[TestWith(['EMPTY ARRAY'])]
    #[TestWith(['DEFAULT true'])]
    public function testExistsRejectsAValueResponse(string $response): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        try {
            $binder->bind("SELECT JSON_EXISTS(jsonb '1', '$' " . $response . ' ON ERROR)');
            self::fail('PostgreSQL rejects this JSON_EXISTS response.');
        } catch (InvalidSql $error) {
            self::assertSame(InputViolation::JsonOption, $error->violation);
        }
    }

    public function testOperandsBindTheDocumentAndAnExpressionPath(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(d jsonb, p jsonpath)'));
        $query = $binder->bind('SELECT JSON_EXISTS(d, p) FROM t');
        self::assertInstanceOf(BoundSelect::class, $query);
        $value = $query->outputs[0]->expression;
        self::assertInstanceOf(JsonExistence::class, $value);
        self::assertSame(['d'], $value->document->expression->referenceParts());
        self::assertSame(['p'], $value->path->referenceParts());
    }
}
