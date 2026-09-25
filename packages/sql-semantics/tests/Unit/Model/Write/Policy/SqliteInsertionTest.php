<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Write\Policy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\InsertStatement;
use SqlSemantics\Model\Write\Policy\ConstraintResponse;
use SqlSemantics\Model\Write\Policy\SqliteInsertion;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SqliteInsertion::class)]
#[Medium]
final class SqliteInsertionTest extends TestCase
{
    public function testDialectIsSqlite(): void
    {
        self::assertSame(Dialect::Sqlite, (new SqliteInsertion())->dialect());
    }

    public function testDefaultsToTheDefaultConstraintResponse(): void
    {
        self::assertSame(ConstraintResponse::Default, (new SqliteInsertion())->onViolation);
    }

    public function testBindsTheResponseFromTheStatement(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER)'));
        $statement = $binder->bind('INSERT OR IGNORE INTO t VALUES(1)');
        self::assertInstanceOf(InsertStatement::class, $statement);
        self::assertInstanceOf(SqliteInsertion::class, $statement->policy);
        self::assertSame(ConstraintResponse::Ignore, $statement->policy->onViolation);
        self::assertSame('INSERT OR IGNORE INTO "main"."t" VALUES (1)', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        $rebound = $binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertInstanceOf(InsertStatement::class, $rebound);
        self::assertInstanceOf(SqliteInsertion::class, $rebound->policy);
        self::assertSame(ConstraintResponse::Ignore, $rebound->policy->onViolation);
    }
}
