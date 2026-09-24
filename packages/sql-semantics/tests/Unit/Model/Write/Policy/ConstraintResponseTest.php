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
use SqlSemantics\Model\Write\Policy\ConstraintResponse;
use SqlSemantics\Model\Write\Policy\SqliteInsertion;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ConstraintResponse::class)]
#[Medium]
final class ConstraintResponseTest extends TestCase
{
    public function testRepresentsEverySqliteConflictResolution(): void
    {
        self::assertSame(['', 'ROLLBACK', 'ABORT', 'FAIL', 'IGNORE', 'REPLACE'], array_column(ConstraintResponse::cases(), 'value'));
    }

    #[TestWith(['INSERT INTO t VALUES(1)', ConstraintResponse::Default, 'INSERT INTO "main"."t" VALUES (1)'])]
    #[TestWith(['INSERT OR ROLLBACK INTO t VALUES(1)', ConstraintResponse::Rollback, 'INSERT OR ROLLBACK INTO "main"."t" VALUES (1)'])]
    #[TestWith(['INSERT OR ABORT INTO t VALUES(1)', ConstraintResponse::Abort, 'INSERT OR ABORT INTO "main"."t" VALUES (1)'])]
    #[TestWith(['INSERT OR FAIL INTO t VALUES(1)', ConstraintResponse::Fail, 'INSERT OR FAIL INTO "main"."t" VALUES (1)'])]
    #[TestWith(['INSERT OR IGNORE INTO t VALUES(1)', ConstraintResponse::Ignore, 'INSERT OR IGNORE INTO "main"."t" VALUES (1)'])]
    #[TestWith(['INSERT OR REPLACE INTO t VALUES(1)', ConstraintResponse::Replace, 'INSERT OR REPLACE INTO "main"."t" VALUES (1)'])]
    public function testBindsTheResolutionFromTheOrClause(string $sql, ConstraintResponse $response, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER)'));
        $statement = $binder->bind($sql);
        self::assertInstanceOf(InsertStatement::class, $statement);
        self::assertInstanceOf(SqliteInsertion::class, $statement->policy);
        self::assertSame($response, $statement->policy->onViolation);
        self::assertSame($expected, $statement->toString());
        self::assertSame($expected, $binder->bind($expected)->toString());
    }
}
