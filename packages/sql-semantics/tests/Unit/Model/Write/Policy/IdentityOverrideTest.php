<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Write\Policy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\InsertStatement;
use SqlSemantics\Model\Write\Policy\IdentityOverride;
use SqlSemantics\Model\Write\Policy\PostgreSqlInsertion;
use SqlSemantics\SchemaBuilder;

#[CoversClass(IdentityOverride::class)]
#[Medium]
final class IdentityOverrideTest extends TestCase
{
    public function testRepresentsEveryIdentityOverride(): void
    {
        self::assertSame(['', 'SYSTEM', 'USER'], array_column(IdentityOverride::cases(), 'value'));
    }

    #[TestWith(['INSERT INTO t VALUES(1)', IdentityOverride::Default, 'INSERT INTO "public"."t" VALUES (1)'])]
    #[TestWith(['INSERT INTO t OVERRIDING SYSTEM VALUE VALUES(1)', IdentityOverride::System, 'INSERT INTO "public"."t" OVERRIDING SYSTEM VALUE VALUES (1)'])]
    #[TestWith(['INSERT INTO t OVERRIDING USER VALUE VALUES(1)', IdentityOverride::User, 'INSERT INTO "public"."t" OVERRIDING USER VALUE VALUES (1)'])]
    public function testBindsTheOverrideFromTheOverridingClause(string $sql, IdentityOverride $overriding, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)'));
        $statement = $binder->bind($sql);
        self::assertInstanceOf(InsertStatement::class, $statement);
        self::assertInstanceOf(PostgreSqlInsertion::class, $statement->policy);
        self::assertSame($overriding, $statement->policy->overriding);
        self::assertSame($expected, $statement->toString());
        self::assertSame($expected, $binder->bind($expected)->toString());
    }
}
