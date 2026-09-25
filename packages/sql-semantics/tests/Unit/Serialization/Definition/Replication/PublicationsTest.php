<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Replication\Publication as Operand;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Serialization\Definition\Replication\Publications::class)]
#[Medium]
final class PublicationsTest extends TestCase
{
    #[TestWith(['CREATE PUBLICATION "p" WITH (publish = \'insert, delete\', publish_via_partition_root = false)'])]
    #[TestWith(['CREATE PUBLICATION "p" FOR ALL TABLES'])]
    #[TestWith(['CREATE PUBLICATION "p" FOR TABLE ONLY "public"."t" WHERE (("a" > 0)), TABLES IN SCHEMA "S"'])]
    #[TestWith(['CREATE PUBLICATION "p" FOR TABLE "public"."t"("a", "b")'])]
    #[TestWith(['ALTER PUBLICATION "p" SET (publish_via_partition_root = true)'])]
    #[TestWith(['ALTER PUBLICATION "p" SET TABLES IN SCHEMA CURRENT_SCHEMA'])]
    public function testWriteIsAFixedPoint(string $sql): void
    {
        self::assertSame($sql, (new \SqlSemantics\SimpleSerializer())->serialize((new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT, b INT)')))->bind($sql)));
    }

    public function testWriteReturnsNullForOtherStatements(): void
    {
        self::assertNull(\SqlSemantics\Serialization\Definition\Replication\Publications::write((new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT, b INT)')))->bind('DROP PUBLICATION p')));
    }

    public function testObjectsPrefixesEveryObject(): void
    {
        self::assertSame('TABLES IN SCHEMA "a", TABLES IN SCHEMA CURRENT_SCHEMA', \SqlSemantics\Serialization\Definition\Replication\Publications::objects([new Operand\PublishedSchema('a'), new Operand\PublishedCurrentSchema()])->toString());
    }

    public function testOptionsIsEmptyWithoutOptions(): void
    {
        self::assertSame([], \SqlSemantics\Serialization\Definition\Replication\Publications::options(new Operand\PublicationOptions(), true));
        self::assertCount(1, \SqlSemantics\Serialization\Definition\Replication\Publications::options(new Operand\PublicationOptions([]), false));
    }
}
