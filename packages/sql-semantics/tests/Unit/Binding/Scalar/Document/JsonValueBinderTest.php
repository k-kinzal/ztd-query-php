<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Scalar\Document;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Scalar\Document\JsonValueBinder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(JsonValueBinder::class)]
#[Medium]
final class JsonValueBinderTest extends TestCase
{
    #[TestWith(['mysql-8.0.44', "SELECT JSON_VALUE('{}', '$.a')", "SELECT JSON_VALUE('{}', '$.a')"])]
    #[TestWith(['mysql-8.4.7', "SELECT JSON_VALUE('{}', '$.a' RETURNING UNSIGNED NULL ON EMPTY)", "SELECT JSON_VALUE('{}', '$.a' RETURNING UNSIGNED NULL ON EMPTY)"])]
    #[TestWith(['mysql-9.1.0', "SELECT JSON_VALUE('{}', '$.a' RETURNING DECIMAL(5,2) DEFAULT -1 ON EMPTY ERROR ON ERROR)", "SELECT JSON_VALUE('{}', '$.a' RETURNING DECIMAL(5, 2) DEFAULT - 1 ON EMPTY ERROR ON ERROR)"])]
    #[TestWith(['mysql-8.0.44', "SHOW CHARSET WHERE JSON_VALUE(USER(), '$.a')", "SHOW CHARACTER SET WHERE JSON_VALUE(USER(), '$.a')"])]
    public function testBindKeepsThePathReturnedTypeAndResponses(string $release, string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $release))->build());
        self::assertSame($expected, $binder->bind($sql)->toString());
        self::assertSame($expected, $binder->bind($expected)->toString());
    }

    public function testBindLeavesPostgreSqlToItsOwnForm(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("SELECT JSON_VALUE('{}', '$.a' PASSING 1 AS x)");
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        $value = $query->outputs[0]->expression;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Document\JsonScalarExtraction::class, $value);
        self::assertSame('x', $value->passing[0]->name);
        self::assertSame('text', $value->type->name);
        self::assertSame(Dialect::PostgreSql, $value->type->dialect);
    }
}
