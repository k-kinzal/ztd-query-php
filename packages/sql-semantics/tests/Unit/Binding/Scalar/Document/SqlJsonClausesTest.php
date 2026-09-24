<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Scalar\Document;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Scalar\Document\SqlJsonClauses;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Scalar\Document\Construction\JsonArrayConstructor;
use SqlSemantics\Model\Scalar\Document\Construction\JsonNullHandling;
use SqlSemantics\Model\Scalar\Document\Construction\JsonObjectConstructor;
use SqlSemantics\Model\Scalar\Document\JsonExistence;
use SqlSemantics\Model\Scalar\Document\JsonParse;
use SqlSemantics\Model\TableFunction\Json\Format;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SqlJsonClauses::class)]
#[Medium]
final class SqlJsonClausesTest extends TestCase
{
    #[TestWith(['RETURNING json', 'json', null])]
    #[TestWith(['RETURNING text FORMAT JSON', 'text', Format::Json])]
    #[TestWith(['RETURNING bytea FORMAT JSON ENCODING UTF8', 'bytea', Format::Utf8])]
    public function testReturningReadsTheTypeAndFormat(string $clause, string $type, ?Format $format): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT JSON_ARRAY(1 ' . $clause . ')');
        self::assertInstanceOf(BoundSelect::class, $query);
        $value = $query->outputs[0]->expression;
        self::assertInstanceOf(JsonArrayConstructor::class, $value);
        self::assertNotNull($value->returning);
        self::assertSame($type, $value->returning->type->name);
        self::assertSame($format, $value->returning->format);
    }

    public function testReturningRejectsAnEncodingOfText(): void
    {
        $this->expectException(InvalidSql::class);
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT JSON_ARRAY(1 RETURNING text FORMAT JSON ENCODING UTF8)');
    }

    public function testPassingReadsEachVariableInOrder(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("SELECT JSON_EXISTS(jsonb '1', '$' PASSING 1 AS a, '{}' FORMAT JSON AS b)");
        self::assertInstanceOf(BoundSelect::class, $query);
        $value = $query->outputs[0]->expression;
        self::assertInstanceOf(JsonExistence::class, $value);
        self::assertSame(['a', 'b'], array_map(static fn ($argument): string => $argument->name, $value->passing));
        self::assertSame(Format::Json, $value->passing[1]->input->format);
    }

    #[TestWith(["SELECT JSON_OBJECT('a': 1)", JsonNullHandling::Null])]
    #[TestWith(["SELECT JSON_OBJECT('a': 1 ABSENT ON NULL)", JsonNullHandling::Absent])]
    public function testNullHandlingFallsBackToTheDefault(string $sql, JsonNullHandling $expected): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
        self::assertInstanceOf(BoundSelect::class, $query);
        $value = $query->outputs[0]->expression;
        self::assertInstanceOf(JsonObjectConstructor::class, $value);
        self::assertSame($expected, $value->onNull);
    }

    #[TestWith(["SELECT JSON('{}')", false])]
    #[TestWith(["SELECT JSON('{}' WITHOUT UNIQUE KEYS)", false])]
    #[TestWith(["SELECT JSON('{}' WITH UNIQUE)", true])]
    public function testUniqueKeysReadsOnlyWithUnique(string $sql, bool $expected): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
        self::assertInstanceOf(BoundSelect::class, $query);
        $value = $query->outputs[0]->expression;
        self::assertInstanceOf(JsonParse::class, $value);
        self::assertSame($expected, $value->uniqueKeys);
    }

    #[TestWith(["SELECT JSON_OBJECT('a' VALUE '{}' FORMAT JSON)"])]
    #[TestWith(["SELECT JSON_OBJECT('a' : '{}' FORMAT JSON)"])]
    public function testMemberReadsEachSpellingAlike(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $query = $binder->bind($sql, strict: false);
        self::assertInstanceOf(BoundSelect::class, $query);
        $value = $query->outputs[0]->expression;
        self::assertInstanceOf(JsonObjectConstructor::class, $value);
        self::assertSame(Format::Json, $value->members[0]->value->format);
        self::assertSame("SELECT JSON_OBJECT('a' : '{}' FORMAT JSON)", $query->toString());
    }
}
