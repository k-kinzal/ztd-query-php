<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Write\Policy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\InsertStatement;
use SqlSemantics\Model\Write\Policy\IdentityOverride;
use SqlSemantics\Model\Write\Policy\PostgreSqlInsertion;
use SqlSemantics\SchemaBuilder;

#[CoversClass(PostgreSqlInsertion::class)]
#[Medium]
final class PostgreSqlInsertionTest extends TestCase
{
    public function testDialectIsPostgreSql(): void
    {
        self::assertSame(Dialect::PostgreSql, (new PostgreSqlInsertion())->dialect());
    }

    public function testDefaultsToNoIdentityOverride(): void
    {
        self::assertSame(IdentityOverride::Default, (new PostgreSqlInsertion())->overriding);
    }

    public function testBindsTheOverrideFromTheStatement(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)'));
        $statement = $binder->bind('INSERT INTO t OVERRIDING USER VALUE VALUES(1)');
        self::assertInstanceOf(InsertStatement::class, $statement);
        self::assertInstanceOf(PostgreSqlInsertion::class, $statement->policy);
        self::assertSame(IdentityOverride::User, $statement->policy->overriding);
        self::assertSame('INSERT INTO "public"."t" OVERRIDING USER VALUE VALUES (1)', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        $rebound = $binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertInstanceOf(InsertStatement::class, $rebound);
        self::assertInstanceOf(PostgreSqlInsertion::class, $rebound->policy);
        self::assertSame(IdentityOverride::User, $rebound->policy->overriding);
    }
}
