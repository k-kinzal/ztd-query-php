<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Scalar\Document;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Scalar\Document\JsonConstructorBinder;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Scalar\Document\Construction\JsonArrayConstructor;
use SqlSemantics\Model\Scalar\Document\Construction\JsonArrayQuery;
use SqlSemantics\Model\Scalar\Document\Construction\JsonNullHandling;
use SqlSemantics\Model\Scalar\Document\Construction\JsonObjectConstructor;
use SqlSemantics\Model\Scalar\Document\JsonParse;
use SqlSemantics\Model\Scalar\Document\JsonScalarConversion;
use SqlSemantics\Model\Scalar\Document\JsonSerialization;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(JsonConstructorBinder::class)]
#[Medium]
final class JsonConstructorBinderTest extends TestCase
{
    #[TestWith(["SELECT JSON_OBJECT('a' VALUE 1)", 1, JsonNullHandling::Null, false, "SELECT JSON_OBJECT('a' : 1)"])]
    #[TestWith(["SELECT JSON_OBJECT('a': 1, 'b': NULL NULL ON NULL WITHOUT UNIQUE KEYS)", 2, JsonNullHandling::Null, false, "SELECT JSON_OBJECT('a' : 1, 'b' : NULL)"])]
    #[TestWith(["SELECT JSON_OBJECT('a': 1 ABSENT ON NULL WITH UNIQUE)", 1, JsonNullHandling::Absent, true, "SELECT JSON_OBJECT('a' : 1 ABSENT ON NULL WITH UNIQUE KEYS)"])]
    #[TestWith(['SELECT JSON_OBJECT()', 0, JsonNullHandling::Null, false, 'SELECT JSON_OBJECT()'])]
    #[TestWith(['SELECT JSON_OBJECT(RETURNING jsonb)', 0, JsonNullHandling::Null, false, 'SELECT JSON_OBJECT(RETURNING jsonb)'])]
    public function testObjectKeepsMembersAndOptions(string $sql, int $members, JsonNullHandling $onNull, bool $unique, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $query = $binder->bind($sql);
        self::assertInstanceOf(BoundSelect::class, $query);
        $value = $query->outputs[0]->expression;
        self::assertInstanceOf(JsonObjectConstructor::class, $value);
        self::assertCount($members, $value->members);
        self::assertSame($onNull, $value->onNull);
        self::assertSame($unique, $value->uniqueKeys);
        self::assertSame($expected, $query->toString());
        self::assertSame($expected, $binder->bind($expected)->toString());
    }

    /**
     * @param class-string<\SqlSemantics\Model\Expression> $class
     */
    #[TestWith(['SELECT JSON_ARRAY(1, 2)', JsonArrayConstructor::class, 'SELECT JSON_ARRAY(1, 2)'])]
    #[TestWith(['SELECT JSON_ARRAY(1 ABSENT ON NULL RETURNING text)', JsonArrayConstructor::class, 'SELECT JSON_ARRAY(1 RETURNING text)'])]
    #[TestWith(['SELECT JSON_ARRAY(RETURNING jsonb)', JsonArrayConstructor::class, 'SELECT JSON_ARRAY(RETURNING jsonb)'])]
    #[TestWith(['SELECT JSON_ARRAY(SELECT 1 UNION SELECT 2 RETURNING jsonb)', JsonArrayQuery::class, 'SELECT JSON_ARRAY(SELECT 1 UNION SELECT 2 RETURNING jsonb)'])]
    #[TestWith(["SELECT JSON_ARRAY(VALUES ('{}') FORMAT JSON)", JsonArrayQuery::class, "SELECT JSON_ARRAY(VALUES ('{}') FORMAT JSON)"])]
    public function testArrayKeepsElementsOrItsQuery(string $sql, string $class, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $query = $binder->bind($sql);
        self::assertInstanceOf(BoundSelect::class, $query);
        self::assertInstanceOf($class, $query->outputs[0]->expression);
        self::assertSame($expected, $query->toString());
        self::assertSame($expected, $binder->bind($expected)->toString());
    }

    #[TestWith(['SELECT JSON_ARRAY(SELECT 1, 2)', InputViolation::ScalarQueryWidth])]
    #[TestWith(["SELECT JSON_OBJECT('a': 1 RETURNING text FORMAT JSON ENCODING UTF8)", InputViolation::JsonOption])]
    #[TestWith(['SELECT JSON_ARRAY(1 RETURNING bytea FORMAT JSON ENCODING UTF16)', InputViolation::JsonOption])]
    public function testArrayAndObjectRejectWhatPostgreSqlRejects(string $sql, InputViolation $violation): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        try {
            $binder->bind($sql);
            self::fail('PostgreSQL rejects this constructor.');
        } catch (InvalidSql $error) {
            self::assertSame($violation, $error->violation);
        }
    }

    /**
     * @param class-string<\SqlSemantics\Model\Expression> $class
     */
    #[TestWith(["SELECT JSON('{}')", JsonParse::class, "SELECT JSON('{}')"])]
    #[TestWith(["SELECT JSON('{}' FORMAT JSON WITHOUT UNIQUE KEYS)", JsonParse::class, "SELECT JSON('{}' FORMAT JSON)"])]
    #[TestWith(['SELECT JSON_SCALAR(1.5)', JsonScalarConversion::class, 'SELECT JSON_SCALAR(1.5)'])]
    #[TestWith(["SELECT JSON_SERIALIZE('{}' RETURNING varchar(10))", JsonSerialization::class, "SELECT JSON_SERIALIZE('{}' RETURNING varchar(10))"])]
    public function testConversionClassifiesByKeyword(string $sql, string $class, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $query = $binder->bind($sql);
        self::assertInstanceOf(BoundSelect::class, $query);
        self::assertInstanceOf($class, $query->outputs[0]->expression);
        self::assertSame($expected, $query->toString());
        self::assertSame($expected, $binder->bind($expected)->toString());
    }
}
