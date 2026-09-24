<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition\Trigger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Definition\Trigger\Policies;

#[CoversClass(Policies::class)]
#[Medium]
final class PoliciesTest extends TestCase
{
    #[TestWith(['CREATE POLICY "p" ON "public"."t" AS PERMISSIVE FOR INSERT TO "CURRENT_USER" WITH CHECK(("a" > 0))'])]
    #[TestWith(['ALTER POLICY "p" ON "public"."t" TO SESSION_USER USING(("a" > 0))'])]
    public function testWriteIsAFixedPoint(string $sql): void
    {
        self::assertSame($sql, (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind($sql)->toString());
    }

    public function testWriteReturnsNullForOtherStatements(): void
    {
        self::assertNull(Policies::write((new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('DROP POLICY p ON t')));
    }

    public function testExpressionsIsEmptyWithoutClauses(): void
    {
        self::assertSame([], Policies::expressions(null, null));
    }
}
