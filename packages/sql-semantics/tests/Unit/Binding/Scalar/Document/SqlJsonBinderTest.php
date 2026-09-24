<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Scalar\Document;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Scalar\Document\SqlJsonBinder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SqlJsonBinder::class)]
#[Medium]
final class SqlJsonBinderTest extends TestCase
{
    /**
     * @param class-string<\SqlSemantics\Model\Expression> $class
     */
    #[TestWith(["SELECT JSON_VALUE(d, '$.a')", \SqlSemantics\Model\Scalar\Document\JsonScalarExtraction::class, "SELECT JSON_VALUE(\"d\", '$.a') FROM \"public\".\"t\""])]
    #[TestWith(["SELECT JSON_QUERY(d, '$.a')", \SqlSemantics\Model\Scalar\Document\JsonQueryExtraction::class, "SELECT JSON_QUERY(\"d\", '$.a') FROM \"public\".\"t\""])]
    #[TestWith(["SELECT JSON_EXISTS(d, '$.a')", \SqlSemantics\Model\Scalar\Document\JsonExistence::class, "SELECT JSON_EXISTS(\"d\", '$.a') FROM \"public\".\"t\""])]
    #[TestWith(['SELECT JSON_SERIALIZE(d)', \SqlSemantics\Model\Scalar\Document\JsonSerialization::class, 'SELECT JSON_SERIALIZE("d") FROM "public"."t"'])]
    #[TestWith(['SELECT JSON(k)', \SqlSemantics\Model\Scalar\Document\JsonParse::class, 'SELECT JSON("k") FROM "public"."t"'])]
    #[TestWith(['SELECT JSON_SCALAR(k)', \SqlSemantics\Model\Scalar\Document\JsonScalarConversion::class, 'SELECT JSON_SCALAR("k") FROM "public"."t"'])]
    #[TestWith(['SELECT JSON_OBJECT(k : d)', \SqlSemantics\Model\Scalar\Document\Construction\JsonObjectConstructor::class, 'SELECT JSON_OBJECT("k" : "d") FROM "public"."t"'])]
    #[TestWith(['SELECT JSON_ARRAY(k)', \SqlSemantics\Model\Scalar\Document\Construction\JsonArrayConstructor::class, 'SELECT JSON_ARRAY("k") FROM "public"."t"'])]
    #[TestWith(['SELECT JSON_ARRAY(SELECT 1)', \SqlSemantics\Model\Scalar\Document\Construction\JsonArrayQuery::class, 'SELECT JSON_ARRAY(SELECT 1) FROM "public"."t"'])]
    #[TestWith(['SELECT JSON_OBJECTAGG(k : d)', \SqlSemantics\Model\Scalar\Document\Construction\JsonObjectAggregate::class, 'SELECT JSON_OBJECTAGG("k" : "d") FROM "public"."t"'])]
    #[TestWith(['SELECT JSON_ARRAYAGG(k)', \SqlSemantics\Model\Scalar\Document\Construction\JsonArrayAggregate::class, 'SELECT JSON_ARRAYAGG("k") FROM "public"."t"'])]
    public function testBindClassifiesEachSqlJsonFunction(string $sql, string $class, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(k text, d jsonb)'));
        $query = $binder->bind($sql . ' FROM t');
        self::assertInstanceOf(BoundSelect::class, $query);
        self::assertInstanceOf($class, $query->outputs[0]->expression);
        self::assertSame($expected, $query->toString());
        self::assertSame($expected, $binder->bind($expected)->toString());
    }

    public function testBindLeavesTheLegacyJsonObjectCallToFunctionResolution(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("SELECT JSON_OBJECT('{a,1}')");
        self::assertInstanceOf(BoundSelect::class, $query);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Function\FunctionCall::class, $query->outputs[0]->expression);
        self::assertSame('SELECT "json_object"(\'{a,1}\')', $query->toString());
    }

    #[TestWith(['select json_exists(j, \'$.a\') from t', 'SELECT JSON_EXISTS("j", \'$.a\') FROM "public"."t"'])]
    #[TestWith(['select json_query(j, \'$.a\') from t', 'SELECT JSON_QUERY("j", \'$.a\') FROM "public"."t"'])]
    #[TestWith(['select json_value(j, \'$.a\') from t', 'SELECT JSON_VALUE("j", \'$.a\') FROM "public"."t"'])]
    public function testBindReadsLowerCaseSqlJsonFunctions(string $sql, string $expected): void
    {
        self::assertSame($expected, (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(j jsonb)')))->bind($sql)->toString());
    }
}
